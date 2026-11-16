# Быстрая справка

Одна страница: что где лежит, какие команды запускать, куда вставлять токен.
Подробности — в остальных файлах `docs/`.

## Что где

| Что | Значение |
|---|---|
| Папка проекта | `C:\OSPanel65\home\neiro-brain` |
| Кабинет | `http://neiro-brain.local/app` |
| Админка | `http://neiro-brain.local/admin` (из сети — `http://192.168.0.107/`, снаружи — `http://95.79.97.24:7777/`) |
| MCP | `http://neiro-brain.local/mcp/knowledge` (streamable-http) |
| HTTP API | `http://neiro-brain.local/api/v1/*` |
| Логи | `storage\logs\laravel.log` |
| Конфиг DSH | `C:\Users\Admin\.dsh\profiles\desktop\cordis.patch.yml` |

Прокси и приёма диалогов нет: знания пишутся только через MCP, API и админку.

## Диагностика за 30 секунд

```powershell
$env:Path = 'C:\OSPanel65\modules\PHP-8.4;' + $env:Path
cd C:\OSPanel65\home\neiro-brain

php artisan test --compact                     # тесты
php artisan brain:user --list                  # люди и число токенов
php artisan brain:stats --user=owner@example.com
php artisan brain:search "стек" --user=owner@example.com
php artisan brain:reindex --user=owner@example.com
php artisan optimize:clear                     # после правки настроек/меню
```

Если `brain:stats` показывает `Knowledge entries 0` — база пустая: карточек ещё
не записал агент. Проверьте, что MCP отвечает и токен имеет право `write`.

## Шаг 1. Поднять сервер

Нужны PHP 8.4 с `pdo_pgsql`/`pgsql`, PostgreSQL 14+ и домен OpenServer.

```powershell
cd C:\OSPanel65\home\neiro-brain
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
```

`APP_URL` должен совпадать с доменом, иначе ссылки в кабинете и админке будут
неверными. Домен, порты и расширения PHP — в [openserver.md](openserver.md).
Docker на macOS — в [docker-macos.md](docker-macos.md).

Сидер — демо, на рабочей базе его не запускайте: `php artisan migrate` без `--seed`.

## Шаг 2. Создать человека

Регистрации нет: пользователя заводит админ.

```powershell
php artisan brain:user --email=owner@example.com --name="Владелец" --password="пароль"
```

Полезное:

```powershell
php artisan brain:user --list
php artisan brain:user --email=owner@example.com --name="Новое имя"   # обновить
php artisan brain:user --disable=owner@example.com                    # отключить
php artisan brain:user --admin                                        # доступ в MoonShine
```

Пароль нужен только для входа в кабинет. Токены человек выпускает сам.

## Шаг 3. Выпустить личный токен

1. Открыть `http://neiro-brain.local/app` и войти email + паролем.
2. **Токены → Выпустить**: имя, способности `read` или `read + write`.
3. Скопировать `nb-…` — значение показывается один раз, в базе лежит только
   SHA-256.

`read` — поиск и чтение API. `write` нужен MCP (`remember_many`,
`delete_knowledge`) и `POST /api/v1/entries`; `write` подразумевает `read`.
Отзыв — там же, кнопкой «Отозвать»: удалённый токен перестаёт работать сразу.

Токен передаётся только в заголовке:

```
Authorization: Bearer nb-…
```

Дев-токена и токена в адресе нет: оба удалены вместе с прокси.

## Шаг 4. Поставить скилл `/brain`

Скилл публичный: токен для скачивания не нужен, иначе первый шаг невыполним.
Скрипты установки делает сам сервер, они ставят тот адрес, с которого скачаны.

Windows (PowerShell):

```powershell
irm http://neiro-brain.local/skill/install.ps1 | iex
```

macOS и Linux:

```bash
curl -fsSL http://neiro-brain.local/skill/install.sh | sh
```

Вручную, если скриптам не доверяете — это ровно то же самое в одну строку:

```bash
mkdir -p ~/.agents/skills/brain
curl -fsSL http://neiro-brain.local/skill/brain -o ~/.agents/skills/brain/SKILL.md
```

На macOS тот же путь: `~/.agents/skills/brain/SKILL.md` (в Finder —
`Cmd+Shift+G` → `~/.agents/skills`).

У кого есть доступ к серверу — та же установка без сети:

```powershell
php artisan brain:skill            # копирует в ~/.agents/skills/brain/SKILL.md
php artisan brain:skill --force    # перезаписать установленный
php artisan brain:skill --path=D:\skills
```

Файл на диске тонкий: он велит прочитать ресурс `knowledge://skill/brain`, а полный
протокол живёт на сервере и меняется вместе с кодом. В харнессе сразу проверьте
`/brain help`.

## Шаг 5. Подключить MCP

Проще всего отдать это скиллу: в харнессе наберите `/brain init`, он спросит адрес
и токен, покажет, что вставит в конфиг, сделает бэкап и проверит связь. После
перезапуска харнесса — `/brain doctor`.

Руками, если `init` не подошёл.

Claude Code (Windows, macOS, Linux):

```bash
claude mcp add --transport http neiro-brain http://neiro-brain.local/mcp/knowledge \
  --header "Authorization: Bearer nb-…"
```

DeepSeek Harness — плагин `@deepseek-ai/dsh-mcp-client`, `transport: streamable-http`
и тот же URL; готовый YAML в [deepseek-harness.md](deepseek-harness.md).

Claude Desktop (macOS и Windows) HTTP напрямую не умеет: нужен мост `mcp-remote`
и установленный Node. В `claude_desktop_config.json` (macOS —
`~/Library/Application Support/Claude/claude_desktop_config.json`, Windows —
`%APPDATA%\Claude\claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "neiro-brain": {
      "command": "npx",
      "args": [
        "-y", "mcp-remote", "http://neiro-brain.local/mcp/knowledge",
        "--header", "Authorization: Bearer nb-…"
      ]
    }
  }
}
```

Инструменты: `search_knowledge`, `get_knowledge`, `remember_many` (`items`,
`supersedes`), `delete_knowledge`, `list_projects`, `recent_knowledge`,
`get_project_context`.

Проверка без харнесса:

```bash
curl -sS http://neiro-brain.local/mcp/knowledge \
  -H "Authorization: Bearer nb-…" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{
        "name":"search_knowledge","arguments":{"query":"стек","limit":3}}}'
```

Пустой ответ `Ничего не найдено` — норма для новой базы: сначала попросите агента
запомнить факт через `remember_many`.

## Шаг 6. Проверить запись и чтение

В сессии с MCP:

> Запомни: предпочитаю модульный монолит и PHPUnit.

> Что ты знаешь о моём стеке?

Агент вызовет `remember_many`, затем `search_knowledge`. Карточка появится в
кабинете (`/app/search`) и в админке (`/admin`). То же через API:

```bash
curl -X POST http://neiro-brain.local/api/v1/entries \
  -H "Authorization: Bearer nb-…" \
  -H "Content-Type: application/json" \
  -d '{"title":"Стиль кода","content":"Пользователь пишет тесты на PHPUnit.","kind":"style"}'
```

## Как искать в кабинете

| Страница | Для чего |
|---|---|
| **Обзор** | профиль, выпущенные токены и их последнее использование |
| **Поиск** | BM25 по своим карточкам; пустой запрос — последние |
| **Карточка** | полный текст, теги, история версий через `superseded_by` |
| **Граф** | проекты → темы → карточки |
| **Проекты** | проекты и число карточек в каждом |

Карточки в кабинете только читаются: исправления формулируются агенту, он
вызывает `remember_many`/`delete_knowledge`.

## Частые ошибки

| Симптом | Причина |
|---|---|
| `401` от MCP/API | нет `Authorization: Bearer nb-…`, токен отозван или истёк |
| `403 insufficient_ability` | у токена только `read`, а нужна запись: выпустите с `write` |
| `503 no_owner` | в базе нет активного пользователя: `php artisan brain:user …` |
| `could not find driver` | в PHP 8.4 не включён `pdo_pgsql` |
| MCP-инструментов нет | неверный `url`/токен или харнесс не перезапущен |
| Поиск пуст | база пустая, другое имя проекта или карточки в архиве |
| 419 на логине | сменился `APP_KEY`/сессия — очистите cookies и `optimize:clear` |
| `instructions` старые | клиент кэширует их на сессию: начните новую |

## Где читать дальше

| Файл | О чём |
|---|---|
| `docs/mcp.md` | инструменты, ресурсы, промпт, инструкции |
| `docs/api.md` | `/api/v1/*`, способности, ошибки |
| `docs/architecture.md` | слои, владелец, BM25, история версий |
| `docs/operations.md` | команды, бэкап, диагностика |
| `docs/deepseek-harness.md` | подключение DSH по MCP |
| `docs/openserver.md` | домен, порты, PHP, PostgreSQL |
