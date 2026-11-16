# Neiro Brain

**Личная база знаний для LLM.** MCP-сервер, HTTP API и кабинет: агент ищет и
записывает факты о человеке, человек читает их в браузере. Поиск — Okapi BM25 на
PostgreSQL, без векторной базы. Исходящих запросов к моделям нет: ни прокси, ни
ключей провайдеров, ни автоматического сбора переписки.

```
Агент (DSH / Claude Code / Cursor…)
   │  MCP: /mcp/knowledge + личный токен        │  HTTP: /api/v1/*
   ▼                                            ▼
Laravel: MCP-инструменты, API, кабинет /app, админка /admin
   │  владелец берётся из токена (api_tokens, способности read/write)
   ▼
PostgreSQL: карточки → чанки → инвертированный индекс BM25
   │  поиск считает N, avgdl и df только по корпусу владельца
   ▼
Агент получает факты; человек читает те же карточки в /app
```

> Одна страница без теории — **[docs/quickstart.md](docs/quickstart.md)**:
> адреса, команды, токены, MCP и таблица частых ошибок.

## Что это теперь

- **MCP-сервер знаний** `/mcp/knowledge`: `search_knowledge`, `get_knowledge`,
  `remember_many` (до 20 карточек за вызов, `supersedes` для замены),
  `delete_knowledge`, `list_projects`, `recent_knowledge`, `get_project_context`.
- **HTTP API** `/api/v1/*`: поиск, карточки, проекты, статистика. Только чтение
  и ручная запись — приёма диалогов (`POST /ingest`) больше нет.
- **Кабинет** `/app`: поиск и карточки только своего владельца, проекты, граф,
  выпуск и отзыв личных токенов, профиль.
- **Админка** `/admin` (MoonShine): все карточки с фильтром по владельцу, люди,
  токены, настройки, поиск по индексу.
- **Владелец обязателен.** У карточки есть `owner_id`; контекст `OwnerContext`
  заполняется middleware из токена или сессии. Пустой контекст — исключение, а не
  «все карточки».
- **BM25 по своему корпусу**: `N`, `avgdl` и `df` считаются внутри `$base` с
  фильтром владельца, поэтому чужие карточки не меняют IDF.
- **История версий**: `remember_many` с `supersedes` помечает старую карточку
  `superseded_by`; она остаётся в базе, но исчезает из поиска и из контекста.
- **Личные токены** в таблице `api_tokens` (Sanctum не используется): в базе
  SHA-256, плейн показывается один раз, способности `read` и `write` (`write`
  подразумевает `read`).
- **Секреты вырезаются** `SecretMasker` до записи; настройки spatie/laravel-settings
  шифруются `APP_KEY`.
- **Скилл `/brain`** ставится командой `php artisan brain:skill`; полный протокол
  сервер отдаёт ресурсом `knowledge://skill/brain`, поэтому копия на диске не
  устаревает.

## Чего больше нет

- Прозрачного прокси `/v1/*` и подмешивания памяти в system prompt.
- Захвата переписки, таблиц диалогов и источников, `brain:sync` и cron.
- Суммаризации, AI-агента извлечения знаний и любых ключей провайдеров в настройках.
- `POST /api/v1/ingest`, глобального «одного токена на всё», stdio-режима MCP.
- `remember_knowledge`: его заменил `remember_many`.

## Быстрый старт (OpenServer, Windows)

Проект лежит в `C:\OSPanel65\home\neiro-brain` и рассчитан на PHP 8.4 из OpenServer.

```powershell
$env:Path = 'C:\OSPanel65\modules\PHP-8.4;' + $env:Path
cd C:\OSPanel65\home\neiro-brain

composer install
copy .env.example .env
php artisan key:generate
php artisan migrate            # без --seed: демо-данные не нужны
php artisan brain:user --email=owner@example.com --name="Владелец" --password="пароль"
```

База PostgreSQL 14+ (`CREATE DATABASE neiro_brain;`), в PHP включены `pdo_pgsql` и
`pgsql`. Домен `http://neiro-brain.local` уже настроен в `.osp\project.ini`.
Подробно: [docs/openserver.md](docs/openserver.md).

Затем:

1. Открыть `http://neiro-brain.local/app`, войти созданным пользователем.
2. **Токены → Выпустить**: выбрать `read` (чтение) или `read + write` (MCP и
   запись через API), скопировать `nb-…` — он показывается один раз.
3. Подключить MCP (ниже), проверить `search_knowledge`.
4. Поставить скилл:

```powershell
php artisan brain:skill            # ~/.agents/skills/brain/SKILL.md
php artisan brain:skill --force    # перезаписать уже установленный
```

## Подключение харнесса по MCP

Сервер: `http://neiro-brain.local/mcp/knowledge`, транспорт streamable-http,
заголовок `Authorization: Bearer nb-…`. Токен только в заголовке.

```json
{
  "mcpServers": {
    "neiro-brain": {
      "url": "http://neiro-brain.local/mcp/knowledge",
      "headers": { "Authorization": "Bearer nb-…" }
    }
  }
}
```

Claude Code:

```bash
claude mcp add --transport http neiro-brain http://neiro-brain.local/mcp/knowledge \
  --header "Authorization: Bearer nb-…"
```

DeepSeek Harness: плагин `@deepseek-ai/dsh-mcp-client`, `transport: streamable-http`
и тот же URL. Модель DSH настраивает сам, обычным способом: через Neiro Brain
модели больше не идут. Готовый конфиг: [docs/deepseek-harness.md](docs/deepseek-harness.md).

MCP-клиент кэширует `instructions` на сессию. Текст собирается
`InstructionsBuilder` из базовых правил, правил владельца (`RulesSettings`) и
медленной динамики (имя, язык, число карточек, до пяти проектов). Свежие данные
приходят только из инструментов; после смены правил начните новую сессию.

## HTTP API

```bash
curl "http://neiro-brain.local/api/v1/search?q=стек&limit=5" \
  -H "Authorization: Bearer nb-…"

curl -X POST http://neiro-brain.local/api/v1/entries \
  -H "Authorization: Bearer nb-…" \
  -H "Content-Type: application/json" \
  -d '{"title":"Стиль кода","content":"Пользователь пишет тесты на PHPUnit.","kind":"style"}'
```

Маршруты, способности и коды ошибок: [docs/api.md](docs/api.md).

## Команды

```powershell
php artisan brain:user --email=... --name=... --password=...   # создать человека
php artisan brain:user --list                                  # список людей
php artisan brain:user --disable=email                         # отключить
php artisan brain:skill                                        # поставить скилл /brain
php artisan brain:stats --user=owner@example.com               # статистика владельца
php artisan brain:search "стек" --user=owner@example.com        # поиск из консоли
php artisan brain:reindex --user=owner@example.com              # пересобрать индекс
php artisan test
vendor\bin\pint
```

`--user` у `brain:stats`, `brain:search`, `brain:reindex` обязателен: владелец не
угадывается. Регистрации в кабинете нет — людей заводит админ командой.

## Как работает BM25

```
score(D, Q) = Σ IDF(q) · (tf · (k1 + 1)) / (tf + k1 · (1 − b + b · |D| / avgdl))
IDF(q)      = ln(1 + (N − df(q) + 0.5) / (df(q) + 0.5))
```

`N` — число чанков владельца, `df` — в скольких его чанках есть терм, `avgdl` —
средняя длина его чанка. После BM25 применяются множители: покрытие термов,
точная фраза, важность, тип знания и свежесть. Параметры `k1`, `b`, `top_k` и
веса меняются в админке → **Настройки**. Русская и английская морфология —
Snowball-стеммер.

## Структура

```
app/
  Console/Commands/      brain:user, brain:skill, brain:stats, brain:search, brain:reindex
  Enums/                 типы, статусы и области знаний
  Http/Controllers/      API, кабинет, админ-действия
  Http/Middleware/       личный токен (VerifyBrainToken), владелец из сессии
  Mcp/                   сервер, инструменты, ресурсы, промпт
  Models/                пользователи, токены, карточки, чанки, термы
  MoonShine/             админка: люди, токены, карточки, настройки
  Services/Brain/        BM25, токенизатор, чанкер, маскировка, контекст, инструкции
  Services/Auth/         выпуск и хеширование токенов
  Settings/              настройки и инициализация
  Support/               OwnerContext, OwnerResolver
docs/                    быстрый старт, MCP, API, архитектура, эксплуатация
resources/brain/skill.md тело скилла, которое отдаёт MCP-ресурс
```

## Тесты

```powershell
php artisan test --compact
vendor\bin\pint --dirty --format agent
```

Покрыто: BM25 (редкость, длина, фильтры, морфология, изоляция корпуса),
маскировка секретов, MCP-инструменты и ресурсы, динамические `instructions`,
кабинет, админка, личные токены и способности, изоляция владельца во всех входах,
каскадное удаление, настройки.

## Ограничения

- BM25 без эмбеддингов: семантические перефразировки ловятся хуже, зато всё
  объяснимо и не требует внешнего сервиса.
- `instructions` кэшируются клиентом на сессию; быстрая динамика — только через
  инструменты.
- Нет OAuth для MCP: личный bearer-токен. Для публичного доступа — HTTPS и
  ограничение IP.
- Карточки в кабинете только читаются: правка и удаление — через агента.
- Общих знаний и workspace пока нет: у каждой карточки ровно один владелец.

## Лицензия

MIT. Проект вдохновлён идеей «второго мозга» из [neiro-brain](https://github.com/skolosov/neiro-brain)
и статьёй [Okapi BM25](https://en.wikipedia.org/wiki/Okapi_BM25).
