# Быстрая справка

Одна страница: что где лежит, какие команды запускать, куда вставлять токены.
Подробности — в остальных файлах `docs/`.

## Что где

| Что | Значение |
|---|---|
| Папка проекта | `C:\OSPanel65\home\neiro-brain` |
| Админка | `http://neiro-brain.local/admin` (из сети — `http://192.168.0.107/`, снаружи — `http://95.79.97.24:7777/`) |
| Обзор | `/admin` — что лежит в базе, темы, здоровье источников |
| Карточки | `/admin/page/search-page` — поиск и карточки по темам |
| Граф | `/admin/page/graph-page` — проекты → темы → карточки |
| Проекты | `/admin/page/projects-page` — карточки проектов |
| Прокси (OpenAI) | `http://neiro-brain.local/v1` → `/v1/chat/completions`, `/v1/responses`, `/v1/models` |
| Прокси (Anthropic) | `http://neiro-brain.local` → `/v1/messages`, `/v1/messages/count_tokens` |
| MCP | `http://neiro-brain.local/mcp/knowledge` |
| Свой API | `http://neiro-brain.local/api/v1/*` |
| Логи | `storage\logs\laravel.log`, `storage\logs\scheduler.log` |
| Конфиг DSH | `C:\Users\Admin\.dsh\profiles\desktop\cordis.patch.yml` |

## Диагностика за 30 секунд

```powershell
$env:Path = 'C:\OSPanel65\modules\PHP-8.4;' + $env:Path
cd C:\OSPanel65\home\neiro-brain

php artisan test                   # 52 теста
php artisan brain:stats            # диалоги, знания, чанки, индекс
php artisan brain:search "стек"    # поиск напрямую по BM25
php artisan brain:sync --force     # принудительная суммаризация
php artisan brain:reindex          # пересобрать индекс из записей
php artisan schedule:list          # расписание cron
php artisan optimize:clear         # после правки настроек/меню
```

Если `brain:stats` показывает `Knowledge entries 0` — база пустая: либо диалоги
ещё не проходили через прокси, либо их суммаризация не удалась. Смотрите
**Диалоги** в админке (колонка статуса и ошибки) и `storage\logs\laravel.log`.

## Шаг 1. Админка

1. Открыть `http://neiro-brain.local/admin` (логин — из `BRAIN_ADMIN_EMAIL`,
   по умолчанию `admin@neiro-brain.local`).
2. **Настройки → Токен доступа**: «Сгенерировать новый» + «Скопировать».
   Один токен действует сразу для прокси, MCP и API (`X-Neiro-Proxy-Token` для
   `/v1/*`, `Authorization: Bearer` для `/mcp/knowledge` и `/api/v1/*`). В лог
   токен не пишется; после смены все клиенты нужно обновить. Своё значение можно
   вписать в поле «Заменить своим значением» — оно попадёт во все три канала.
   Альтернатива для `.env`: `BRAIN_TOKEN=...`, после `php artisan optimize:clear`.
3. **Настройки → Суммаризация**: ключ DeepSeek, модель `deepseek-flash`.
   Без ключа работает детерминированный эвристический экстрактор (хуже).
4. **Настройки → Поиск**: `k1`, `b`, top K — значения по умолчанию подходят.
5. **Настройки → Синхронизация**: «Хранить переписку, часов» (по умолчанию 24;
   `0` — не удалять). Переписка нужна только для суммаризации, дальше это мусор.

## Как читать базу

| Страница | Для чего |
|---|---|
| **Обзор** | сколько карточек, проектов и тем; график по неделям; здоровье источников |
| **Карточки** | поиск BM25 (пустой запрос — последние), фильтр по типу и проекту, чипы тем |
| **Граф** | узлы: проекты, темы, карточки; клик — карточка в панели, двойной — открыть |
| **Проекты** | карточки проектов с их темами |

Темы строятся из тегов карточки (`bm25` → «Поиск»); если тегов нет — из типа
знания («Решения», «Стек», «Стиль работы»). Поэтому на графе каждая карточка
привязана хотя бы к одной теме.

## Что попадает в базу

Суммаризация извлекает только долговременные факты. Перед записью `EntryQuality`
отбрасывает:

- пересказ задачи («пользователь попросил распознать 9 видео», «сделай мне…»);
- вопросы и гипотезы, код, команды, пути к файлам;
- слишком короткое, `importance < 3`, `confidence < 0.35`;
- обрезанные заголовки-фрагменты и дубли по нормализованному тексту.

Пустой результат синхронизации — нормальный: значит новых знаний в диалоге нет.
Без AI-ключа работает эвристика, но она берёт только явные «я/мой»,
«предпочитаю», «решили» — иначе лучше ноль карточек, чем мусор.

## Шаг 2. Подключить харнесс к модели

Правило: **провайдер, ключ, модель и reasoning выбираются в харнессе.** Neiro Brain
только пропускает запрос и добавляет память.

| Харнесс | Что поменять |
|---|---|
| OpenAI SDK / Codex / Cursor / Continue | base URL → `http://neiro-brain.local/v1` |
| Claude Code / Anthropic SDK | base URL → `http://neiro-brain.local` |
| DeepSeek Harness | провайдер в `cordis.patch.yml` (ниже) |

Дополнительные заголовки клиента:

```
X-Neiro-Proxy-Token: <прокси-токен>
X-Neiro-Upstream: deepseek        # openai | deepseek | anthropic | openrouter | ollama | mock
```

Если клиент не умеет задавать заголовки: положите ключ провайдера в
`DEEPSEEK_API_KEY` окружения PHP и передавайте `Authorization: Bearer <прокси-токен>`
как раньше.

Проверка:

```bash
curl http://neiro-brain.local/v1/chat/completions \
  -H "Authorization: Bearer $DEEPSEEK_API_KEY" \
  -H "X-Neiro-Proxy-Token: dev-proxy-token" \
  -H "X-Neiro-Upstream: deepseek" \
  -H "Content-Type: application/json" \
  -d '{"model":"deepseek-flash","messages":[{"role":"user","content":"Какой у меня стек?"}]}'
```

Без ключа провайдера: `X-Neiro-Upstream: mock` — ответил без сети, в теле видна
найденная память.

## Шаг 3. Подключить MCP

```json
{
  "mcpServers": {
    "neiro-brain": {
      "url": "http://neiro-brain.local/mcp/knowledge",
      "headers": { "Authorization": "Bearer dev-mcp-token" }
    }
  }
}
```

Claude Code:

```bash
claude mcp add --transport http neiro-brain http://neiro-brain.local/mcp/knowledge \
  --header "Authorization: Bearer dev-mcp-token"
```

Инструменты: `search_knowledge`, `get_knowledge`, `remember_knowledge`,
`list_projects`, `recent_knowledge`.

## Шаг 4. DeepSeek Harness

`cordis.patch.yml` (два блока):

```yaml
- id: llm-pi-ai
  name: "@deepseek-ai/dsh-llm-pi-ai"
  config:
    providers:
      neiro-brain:
        displayName: Neiro Brain (DeepSeek)
        api: openai-completions
        apiKeyEnv: DEEPSEEK_API_KEY
        baseURL: http://127.0.0.1/v1
        headers:
          X-Neiro-Proxy-Token: dev-proxy-token
          X-Neiro-Upstream: deepseek
        models:
          - id: deepseek-flash
            reasoningEfforts:
              off:
              low: low
              medium: high
              high: max
        compat:
          supportsDeveloperRole: false
          maxTokensField: max_tokens
          supportsReasoningEffort: true
          thinkingFormat: deepseek

- insert:
    - id: mcp-neiro-brain
      name: "@deepseek-ai/dsh-mcp-client"
      config:
        serverName: neiro-brain
        transport: streamable-http
        url: http://127.0.0.1/mcp/knowledge
        headers:
          Authorization: Bearer dev-mcp-token
```

Затем в DSH: **Settings → Models → Neiro Brain (DeepSeek) → deepseek-flash**,
уровень размышления — там же.

## Частые ошибки

| Симптом | Причина |
|---|---|
| `404 chat/completions could not be found` | в base URL забыли `/v1` |
| `401` от прокси | неверный `X-Neiro-Proxy-Token` |
| `503` от прокси | нет `Authorization`/`X-Neiro-Upstream` или upstream недоступен |
| `400 ... [] is not of type "object"` | старый билд без нормализации схем инструментов |
| Память не подмешивается | пустая база, `X-Neiro-Memory: off`, выключено «Подмешивать память» |
| `could not find driver` | в PHP 8.4 не включён `pdo_pgsql` |
| 419 на логине | сменился `APP_KEY`/сессия — очистите cookies и `optimize:clear` |
| Cron не идёт | задача `Laravel Schedule` в `.osp\tasks.ini`, `storage\logs\scheduler.log` |

## Где читать дальше

| Файл | О чём |
|---|---|
| `docs/deepseek-harness.md` | DSH целиком, с проверками |
| `docs/proxy.md` | протоколы, инъекция памяти, захват, SSE |
| `docs/mcp.md` | инструменты, ресурсы, промпт, клиенты |
| `docs/api.md` | `/api/v1/*` |
| `docs/operations.md` | cron, бэкапы, диагностика |
| `docs/architecture.md` | устройство и поток данных |
| `docs/openserver.md` | домен, порты, PHP, PostgreSQL |
