---
name: brain
description: Работа с личной базой знаний neiro-brain — установка, поиск, запись фактов, слияние дублей.
argument-hint: "[help | --help | init | doctor | compact --project=<имя> | --theme=<тема>]"
disable-model-invocation: true
---

# /brain

Личная база знаний пользователя живёт на MCP-сервере `neiro-brain`. Список команд:

| Команда | Что делает |
|---|---|
| `/brain` | Загружает протокол работы с базой и работает по нему |
| `/brain help` | Показывает эту справку, в базу не ходит |
| `/brain --help` | То же самое, что `/brain help` |
| `/brain init` | Подключает MCP-сервер: спрашивает адрес и токен, правит конфиг харнесса |
| `/brain doctor` | Проверяет, что скилл, конфиг, токен и ресурс на месте |
| `/brain compact --project=<имя>` | Сливает дубли в карточках одного проекта |
| `/brain compact --theme=<тема>` | Сливает дубли в карточках одной темы |
| `/brain compact` | Сливает дубли по всей базе |

## `/brain help`

`help` и `--help` — одно и то же: оба пишут справку. Ничего не вызывай, в MCP не ходи,
просто напечатай таблицу команд из этого файла и две строки под ней:

- для работы нужен MCP-сервер `neiro-brain` и личный токен из кабинета `/app/tokens`;
- если MCP отвечает ошибкой авторизации, дело в токене, а не в базе.

Если пользователь спросил справку посреди другой команды — сначала коротко ответь
справкой, потом спроси, продолжать ли прерванное.

## `/brain init`

Подключение MCP-сервера. Работай по шагам и не выдумывай пути: сначала посмотри, что есть.

1. **Спроси две вещи**, если они не даны аргументами: адрес сервера (например
   `https://brain.example.ru`) и личный токен из `<адрес>/app/tokens`.
   Токен не печатай в вывод и не повторяй в ответе.
2. **Определи харнесс** по признакам, а не по догадке:
   - есть каталог `~/.dsh` (Windows `%USERPROFILE%\.dsh`, macOS/Linux `$HOME/.dsh`) —
     это DeepSeek Harness, конфиг `~/.dsh/profiles/<профиль>/cordis.patch.yml`;
   - команда `claude` отвечает в PATH — это Claude Code, подключай родной командой;
   - macOS и Claude Desktop (`~/Library/Application Support/Claude/claude_desktop_config.json`) —
     HTTP напрямую не умеет, нужен мост `npx -y mcp-remote <адрес>/mcp/knowledge --header "…"`,
     предложи его и объясни, что нужен Node;
   - ничего не подошло — напечатай фрагмент конфига и спроси, куда его положить.
3. **DSH**: прочитай `cordis.patch.yml`, сделай копию рядом (`cordis.patch.yml.bak-<дата>`),
   покажи, что собираешься вставить, и дождись подтверждения. Блок:

   ```yaml
   - insert:
       - id: mcp-neiro-brain
         name: "@deepseek-ai/dsh-mcp-client"
         config:
           serverName: neiro-brain
           transport: streamable-http
           url: <адрес>/mcp/knowledge
           headers:
             Authorization: Bearer <токен>
   ```

   Если в файле уже есть `serverName: neiro-brain` — обнови `url` и `headers` в нём,
   второй блок не добавляй.
4. **Claude Code**: `claude mcp add --transport http neiro-brain <адрес>/mcp/knowledge
   --header "Authorization: Bearer <токен>"`. Формат конфига не правь руками.
5. **Проверь связь** до того, как сказать «готово»: `POST <адрес>/mcp/knowledge` с
   заголовками `Authorization: Bearer <токен>`, `Content-Type: application/json`,
   `Accept: application/json, text/event-stream` и телом
   `{"jsonrpc":"2.0","id":1,"method":"tools/list"}`. Ждём 200 и `remember_many` в ответе.
   401 — токен неверный или отозван; 403 — у токена нет права записи.
6. **Скажи про перезапуск**: харнесс читает список MCP-серверов при старте, поэтому
   до перезапуска `/brain` в этой сессии работать не будет. После перезапуска — `/brain doctor`.

## `/brain doctor`

Проверка без починки. Собери таблицу и одну рекомендацию на каждую поломку:

| Что проверяем | Как |
|---|---|
| Скилл на месте | файл `~/.agents/skills/brain/SKILL.md` существует |
| MCP прописан | в конфиге харнесса есть `neiro-brain` |
| Токен живой | `tools/list` отвечает 200 |
| Протокол читается | ресурс `knowledge://skill/brain` отдаёт markdown |

Ничего не исправляй без явной просьбы: `doctor` — это диагноз, `init` — лечение.

## `/brain`

1. Прочитай ресурс `knowledge://skill/brain` (MCP-сервер `neiro-brain`).
2. Дальше действуй строго по нему: поиск перед ответом, запись пачкой, дубли через
   `supersedes`, удаление через архив.

## `/brain compact`

С охватом: проект (`--project=<имя>`), тема (`--theme=<тема>`) или вся база.
Прочитай `knowledge://skill/brain`, раздел «Команда `/brain compact`», и выполни его:
найди дубли, покажи план слияния, дождись подтверждения, запиши объединённое
через `remember_many`, а старые карточки перечисли в поле `supersedes`.
