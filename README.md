# Neiro Brain

**Личная база знаний для LLM.** Прозрачный прокси записывает переписку с AI-харнессами,
суммирует её в атомарные факты и отдаёт обратно через MCP. Поиск — Okapi BM25 на PostgreSQL,
без векторной базы и без изменения клиентов.

```
Claude Code / Codex / Cursor / OpenAI SDK
                │  OpenAI- или Anthropic-совместимый API
                ▼
      Laravel proxy  /v1/*          ──► upstream LLM (OpenAI, Anthropic, DeepSeek…)
                │                         ответ проходит без изменений
                ├── пишет диалог в PostgreSQL
                ├── подмешивает релевантную память в system prompt
                ▼
      brain:sync каждые 15 минут
                ├── суммаризация диалога (Laravel AI SDK)
                ├── вырезание секретов
                └── BM25-индексация
                ▼
      MCP /mcp/knowledge  ──► search_knowledge, get_knowledge, remember_knowledge…
                │
                ▼
      MoonShine /admin    ──► читать, править, подтверждать, смотреть источники
```

> Нужна одна страница без теории — **[docs/quickstart.md](docs/quickstart.md)**:
> адреса, команды, токены, YAML для DSH и таблица частых ошибок.
> macOS + Docker — **[docs/docker-macos.md](docs/docker-macos.md)**: nginx, php-fpm,
> PostgreSQL и планировщик в контейнерах, DSH нативно.

## Что уже есть

- **Прозрачный прокси** `/v1/chat/completions`, `/v1/responses`, `/v1/messages` и
  `/v1/messages/count_tokens`: харнессу нужно поменять только base URL и токен.
  Тело ответа и SSE-поток не переписываются.
- **Память в промпте**: BM25-поиск по базе, компактный блок фактов в system prompt,
  заголовок `X-Neiro-Memory-Hits` для отладки, `X-Neiro-Memory: off` для отключения.
- **Захват диалогов**: сессии, сообщения, харнесс, проект, модель, usage — всё в PostgreSQL.
- **Суммаризация**: агент на Laravel AI SDK с JSON-схемой; без API-ключа работает
  детерминированный эвристический экстрактор, поэтому демо и тесты не требуют сети.
- **BM25**: русская и английская морфология, IDF, насыщение TF, нормализация длины,
  бонусы за важность/свежесть/тип, фильтры по проекту и типу, сниппеты с подсветкой.
- **MCP-сервер**: `search_knowledge`, `get_knowledge`, `remember_knowledge`,
  `list_projects`, `recent_knowledge`, ресурсы `knowledge://index` и `knowledge://entry/{id}`,
  промпт `about-me`. Web (HTTP) и local (stdio) варианты.
- **MoonShine-админка**: обзор с графиком и темами, карточки знаний с поиском,
  граф «проекты → темы → карточки», страница проектов, диалоги, источники, журнал
  поисков, настройки.
- **Карточки и темы**: единица базы — карточка (краткий ответ, факты, теги, проект,
  важность); карточки раскладываются по темам из тегов, а без тегов — по типу знания.
- **Переписка — сырьё**: сообщения живут только до суммаризации и удаляются
  (`Транскрипт переписки, часов`, по умолчанию 24), остаются карточки и метаданные.
- **spatie/laravel-settings**: типизированные настройки, секреты шифруются `APP_KEY`
  (`#[ShouldBeEncrypted]`), меняются из админки без деплоя.
- **Cron каждые 15 минут**: ingest → summarize → reindex → prune.
- **Секреты вырезаются** до записи: OpenAI/Anthropic ключи, AWS, GitHub/GitLab, JWT,
  private keys, `PASSWORD=…`, URL с паролем и пользовательские regex.

## Быстрый старт (OpenServer, Windows)

Проект лежит в `C:\OSPanel65\home\neiro-brain` и рассчитан на PHP 8.4 из OpenServer.

### 1. PHP и PostgreSQL

В PHP 8.4 должны быть включены `pdo_pgsql` и `pgsql` (в OpenServer: модуль PHP → расширения).
Проверка:

```powershell
php -m | findstr pgsql
```

PostgreSQL 14+ из OpenServer, база:

```sql
CREATE DATABASE neiro_brain;
```

По умолчанию OpenServer слушает `127.0.1.46:5432`, пользователь `postgres`, без пароля.
Это значения `DB_*` в `.env`.

### 2. Установка

```powershell
cd C:\OSPanel65\home\neiro-brain
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Сидер — демо, а не обязательный шаг. Подробнее: [раздел «Что реально нужно из сидера»](#что-реально-нужно-из-сидера).

### 3. Запуск

Домен OpenServer уже настроен в `.osp\project.ini`:

- `http://neiro-brain.local/`
- `http://neiro-brain.local/admin` — админка;
- `http://192.168.0.107/` — из локальной сети;
- `https://neiro-brain.local/` — локальный сертификат (предупреждение браузера).

Проверка:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\network-info.ps1
```

Если нужен доступ из интернета по статическому IP, настройте проброс 80/443 на
`192.168.0.107` в роутере. Подробно: [docs/openserver.md](docs/openserver.md).

Резервный вариант, без OpenServer:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Тогда откройте `http://127.0.0.1:8000/admin` и временно поставьте
`APP_URL=http://127.0.0.1:8000`.

### 4. Проверка

```powershell
php artisan brain:stats
php artisan brain:search "какой у меня стек"
php artisan brain:sync --force
```

## Подключение харнессов

Главное требование: **со стороны агента ничего не меняется, кроме base URL и токена.**

### OpenAI-совместимые клиенты (Codex, Cursor, Continue, SDK)

Подключение модели — в харнессе. Клиент отправляет **свой** ключ провайдера
в `Authorization`, а Neiro Brain открывает отдельный `X-Neiro-Proxy-Token`:

```bash
OPENAI_BASE_URL=http://neiro-brain.local/v1
OPENAI_API_KEY=<ключ провайдера, например DeepSeek>
# дополнительные заголовки клиента:
# X-Neiro-Proxy-Token: dev-proxy-token
# X-Neiro-Upstream: deepseek
```

`X-Neiro-Upstream` выбирает провайдера; `model`, `reasoning_effort`, `thinking`,
`temperature` и остальные параметры передаются без изменений. Codex и другие клиенты
на OpenAI Responses API работают через `/v1/responses` — base URL тот же.

Если клиент не умеет задавать дополнительные заголовки, используйте fallback:
положите ключ провайдера в `DEEPSEEK_API_KEY` окружения PHP и передавайте
`Authorization: Bearer dev-proxy-token` как раньше.

### Claude Code / Anthropic SDK

```bash
export ANTHROPIC_BASE_URL=http://neiro-brain.local
export ANTHROPIC_API_KEY=<ключ провайдера>
# X-Neiro-Proxy-Token: dev-proxy-token
# X-Neiro-Upstream: anthropic или deepseek
```

Anthropic-клиент сам добавит `/v1/messages`. Прокси принимает токен Neiro Brain
в `X-Neiro-Proxy-Token`, а провайдерский ключ — в `x-api-key`/`Authorization`
и передаёт его upstream.

### Заголовки (необязательные)

| Заголовок | Назначение |
|---|---|
| `X-Neiro-Proxy-Token` | Токен доступа к Neiro Brain (когда провайдерский ключ идёт в `Authorization`) |
| `X-Neiro-Upstream` | `deepseek`, `openai`, `anthropic`, `openrouter`, `ollama` |
| `X-Neiro-Session: my-session` | Стабильный id диалога (иначе вычисляется по system prompt + user-agent + дата) |
| `X-Neiro-Project: Sklad` | Проект для фильтрации и админки |
| `X-Neiro-Harness: codex` | Имя харнесса (иначе определяется по User-Agent) |
| `X-Neiro-Memory: off` | Не подмешивать память в этот запрос |

### MCP

HTTP-сервер: `http://neiro-brain.local/mcp/knowledge`, заголовок
`Authorization: Bearer dev-mcp-token`.

Пример для клиентов с JSON-конфигом (Claude Desktop, Cursor, Continue):

```json
{
  "mcpServers": {
    "neiro-brain": {
      "url": "http://neiro-brain.local/mcp/knowledge",
      "headers": {
        "Authorization": "Bearer dev-mcp-token"
      }
    }
  }
}
```

Claude Code:

```bash
claude mcp add --transport http neiro-brain http://neiro-brain.local/mcp/knowledge \
  --header "Authorization: Bearer dev-mcp-token"
```

stdio-вариант (без HTTP):

```bash
php artisan mcp:start knowledge
```

Подробнее: [docs/mcp.md](docs/mcp.md).

### DeepSeek Harness

DSH подключается двумя блоками в `~/.dsh/profiles/<profile>/cordis.patch.yml`:
кастомный OpenAI-провайдер `neiro-brain` (base URL `http://127.0.0.1/v1`) и
MCP-сервер `neiro-brain` (`http://127.0.0.1/mcp/knowledge`). Модель в UI —
`Neiro Brain (DeepSeek)` → `deepseek-flash`.

Пошагово и готовый YAML: [docs/deepseek-harness.md](docs/deepseek-harness.md).

## Что реально нужно из сидера

Сидер — демонстрационный: он выдумывает личность («Алексей из Москвы, компания
«Северный код»») и подмешивает её в каждую сессию. Для рабочей базы он не нужен.

**Обязательно:** ничего. Настройки (`SettingsInitializer`) подтягиваются сами при
старте и первом сохранении в админке, источник `proxy` создаётся автоматически
при первом запросе через `/v1/*`.

**Ручками один раз:** администратор MoonShine — либо сидером, либо командой:

```powershell
php artisan moonshine:user
```

**Только для демонстрации** (машина заказчику, скриншоты, тесты руками):
9 демо-диалогов, демо-источники, 4 записи «от себя», журнал поисков и запись
синхронизации.

| Блок сидера | Что делает | Нужно в проде |
|---|---|---|
| `ensure()` | записывает дефолты настроек в таблицу `settings` | да, но это делает middleware и форма |
| `seedAdmin()` | админ `admin@neiro-brain.local` / `password` | только для первого входа |
| `seedSources()` | источники `proxy`/`manual`/`api`, JSONL-демо, Markdown-заготовка | `proxy`/`manual`/`api` создаются кодом; демо-файлы — нет |
| `seedConversations()` | 9 демо-диалогов с **реальными** вызовами AI | нет |
| `seedManualKnowledge()` | 4 готовые записи «пользователь Алексей…» | нет |
| `seedSearchLogs()` | 4 фейковые строки журнала поисков | нет |
| `seedSyncRun()` | фейковая запись успешной синхронизации | нет |

### Осторожно

`DatabaseSeeder` вызывает `SummaryService` на каждом демо-диалоге. На копии
`.env` с реальным ключом `php artisan migrate --seed` или `db:seed` **потратит
деньги на API и запишет выдуманные факты в рабочую базу**. На рабочей базе
сидер не запускайте.

Демо-диалог `demo-009-secret` намеренно содержит «ключи» — он проверяет маскировку.
Если они случайно попадут в настоящую базу, удалите записи с проектом `neiro-brain`
и темой «Проверка маскировки секретов».

### Как поднять рабочую базу правильно

```powershell
php artisan migrate            # без --seed
php artisan moonshine:user     # создать админа
php artisan brain:sync --force # наполнить из реальных источников
php artisan brain:reindex
php artisan brain:stats
```

## Как работает BM25

Для каждого чанка `D` и терма запроса `q`:

```
score(D, Q) = Σ IDF(q) · (tf · (k1 + 1)) / (tf + k1 · (1 − b + b · |D| / avgdl))
IDF(q)      = ln(1 + (N − df(q) + 0.5) / (df(q) + 0.5))
```

- **редкое слово дороже**: IDF растёт, когда терм встречается в немногих чанках;
- **повторы с насыщением**: `tf` даёт прирост, но `k1` ограничивает его;
- **поправка на длину**: `b` штрафует длинные чанки, чтобы короткая точная заметка
  весила больше огромной статьи.

После BM25 применяются множители: важность записи, тип знания, свежесть,
точное вхождение фразы и покрытие термов запроса. Параметры `k1`, `b`, `top_k`,
размер чанка и веса меняются в админке → **Настройки**.

Русские и английские слова приводятся к основе Snowball-стеммером: «борща» и «борщ» —
один терм, «приготовил» и «приготовить» — один терм.

## Источники знаний

| Тип | Что делает | Пример конфига |
|---|---|---|
| `proxy` | Пишет диалоги, прошедшие через `/v1/*` | создаётся автоматически |
| `jsonl` | Читает транскрипты Claude Code / Codex (JSONL) | `{"path":"C:\\Users\\...\\.claude\\projects","project":"neiro-brain"}` |
| `chatgpt` | Импортирует `conversations.json` из экспорта ChatGPT | `{"path":"C:\\export\\conversations.json"}` |
| `markdown` | Папка заметок `.md` — один диалог на файл | `{"path":"C:\\notes"}` |
| `manual` | Записи из админки, API и MCP | создаётся автоматически |
| `api` | Приём через `POST /api/v1/ingest` | создаётся автоматически |

Источники редактируются в админке → **Источники**. Для JSONL достаточно указать папку:
файлы обходятся рекурсивно, дубликаты сообщений отсекаются по хешу.

## Cron каждые 15 минут

Расписание уже объявлено в `routes/console.php`:

```php
Schedule::command('brain:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->onOneServer()
    ->runInBackground();
```

Нужен только запуск планировщика раз в минуту. В OpenServer это уже настроено в
`.osp\tasks.ini` — задача `Laravel Schedule` вызывает `php artisan schedule:run`
каждую минуту. Отдельный Task Scheduler не требуется.

Проверка:

```powershell
Get-Content storage\logs\scheduler.log -Tail 20
```

Резервный вариант для Windows (если OpenServer-планировщик отключён):

```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "Neiro Brain Scheduler" ^
  /TR "\"C:\OSPanel65\modules\PHP-8.4\php.exe\" \"C:\OSPanel65\home\neiro-brain\artisan\" schedule:run" /F
```

Или используйте `scripts\scheduler.cmd` (поправьте путь к PHP) и добавьте его в Task Scheduler.

**Linux:**

```cron
* * * * * cd /path/to/neiro-brain && php artisan schedule:run >> /dev/null 2>&1
```

`brain:sync` делает: чтение источников → суммаризация «тихих» диалогов →
переиндексация изменённых знаний → очистка старых логов поиска.

Очередь по умолчанию `sync` (всё в одном процессе, удобно для OpenServer).
Для больших объёмов: `QUEUE_CONNECTION=database`, `php artisan queue:work`.

## API

Токен: `Authorization: Bearer dev-api-token` (меняется в админке).

```bash
# Приём диалога и немедленная суммаризация
curl -X POST http://neiro-brain.local/api/v1/ingest \
  -H "Authorization: Bearer dev-api-token" \
  -H "Content-Type: application/json" \
  -d '{
    "external_id":"chat-2026-11-08",
    "title":"Обсуждение архитектуры",
    "project":"neiro-brain",
    "summarize":true,
    "messages":[
      {"role":"user","content":"Я предпочитаю модульный монолит."}
    ]
  }'

# Поиск
curl "http://neiro-brain.local/api/v1/search?q=архитектура&limit=5" \
  -H "Authorization: Bearer dev-api-token"

# Ручная запись
curl -X POST http://neiro-brain.local/api/v1/entries \
  -H "Authorization: Bearer dev-api-token" \
  -H "Content-Type: application/json" \
  -d '{"title":"Стиль кода","content":"Пользователь пишет тесты на Pest.","kind":"style"}'
```

Полное описание: [docs/api.md](docs/api.md).

## Админка

`/admin` (MoonShine):

- **Обзор** — карточки, проекты, темы, график по неделям, здоровье источников,
  последние карточки и поиски; кнопки запуска.
- **Поиск** — карточки знаний: BM25-запрос, фильтры по типу и проекту, чипы тем,
  панель выбранной карточки с фактами и связями. Пустой запрос — последние карточки.
- **Граф** — проекты, темы и карточки; поиск подсвечивает найденное, клик открывает
  карточку, двойной клик — на страницу карточки.
- **Проекты** — карточки проектов с их темами и важностью.
- **Знания** — табличный CRUD для правки полей карточки.
- **Диалоги** — транскрипт (пока не удалён), статус, ошибки, «Суммаризировать».
- **Источники** — JSONL/ChatGPT/Markdown, пути, включение, последняя синхронизация.
- **Поисковые запросы** — что искали агенты, сколько результатов, latency.
- **Настройки** — токены доступа (с генерацией и копированием), память и прокси,
  параметры BM25, ключ для суммаризации, синхронизация и срок хранения переписки,
  маскировка. Провайдер модели и ключ здесь не настраиваются: они приходят из харнесса.

Секреты в форме показываются как `••••••••`; пустое поле сохраняет старое значение.

## Конфигурация и секреты

`app/Settings/*` — типизированные настройки spatie/laravel-settings.
Секретные свойства помечены `#[ShouldBeEncrypted]` и лежат в таблице `settings`
в зашифрованном виде. Ключ шифрования — `APP_KEY`: **без него секреты не восстановить**,
делайте бэкап `.env` вместе с базой.

При первом запуске `SettingsInitializer` записывает значения по умолчанию, чтобы
админка могла сохранять настройки без ошибки `MissingSettings`.

## Тесты

```powershell
php artisan test
vendor\bin\pint
```

64 теста покрывают: BM25 (редкость, длина, фильтры, черновики, морфологию),
маскировку секретов, прокси (mock/stream/Anthropic, инъекцию памяти, захват),
MCP-инструменты, админку, шифрование настроек, JSONL-коннектор, AI-пайплайн
(через `KnowledgeExtractionAgent::fake()`), фильтр качества знаний, темы, граф,
карточки и удаление переписки после суммаризации.

## Структура

```
app/
  Ai/                    агент извлечения знаний + фабрика провайдера
  Console/Commands/      brain:sync, brain:search, brain:reindex, brain:stats
  Enums/                 типы знаний, статусы, источники
  Http/Controllers/      прокси, API, админ-действия
  Http/Middleware/       bearer-токены прокси/MCP/API
  Mcp/                   сервер, инструменты, ресурсы, промпт
  Models/                знания, чанки, термы, диалоги, источники, логи
  MoonShine/             ресурсы, страницы, layout
  Services/Brain/        BM25, токенизатор, чанкер, маскировка, экстракция
  Services/Connectors/   JSONL, ChatGPT export, Markdown
  Services/Proxy/        upstream, mock, конфиг
  Settings/              настройки и инициализация
docs/                    быстрый старт, архитектура, MCP, прокси, API, эксплуатация, OpenServer
scripts/                 планировщик для Windows
```

## Ограничения и что дальше

- Прокси не транслирует OpenAI ↔ Anthropic: клиент и upstream должны говорить
  на одном протоколе (OpenAI-совместимый upstream для `/v1/chat/completions`,
  Anthropic — для `/v1/messages`).
- BM25 без эмбеддингов: семантические перефразировки ловятся хуже, зато всё
  объяснимо и не требует внешнего сервиса.
- Суммаризация — пакетная, раз в 15 минут; для мгновенной памяти используйте
  MCP `remember_knowledge`.
- Нет встроенного OAuth для MCP: bearer-токен. Для публичного доступа ставьте
  прокси за HTTPS и ограничьте IP.
- Идеи: pgvector как второй канал, дедупликация по эмбеддингам, экспорт в Obsidian,
  вебхуки источников, тонкая настройка промптов из админки.

## Лицензия

MIT. Проект вдохновлён идеей «второго мозга» из [neiro-brain](https://github.com/skolosov/neiro-brain)
и статьёй [Okapi BM25](https://en.wikipedia.org/wiki/Okapi_BM25).
