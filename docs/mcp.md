# MCP-сервер

Endpoint: `POST /mcp/knowledge`  
Авторизация: `Authorization: Bearer <MCP-токен>` (настраивается в админке)  
Local: `php artisan mcp:start knowledge`

## Инструменты

### `search_knowledge`

Поиск по базе BM25. Возвращает markdown + structured content.

| Аргумент | Тип | Описание |
|---|---|---|
| `query` | string, required | Запрос |
| `project` | string | Фильтр по проекту |
| `kinds` | string[] | `profile`, `preference`, `project`, `decision`, `person`, `company`, `stack`, `style`, `process`, `fact`, `other` |
| `limit` | int 1–50 | По умолчанию 8 |

### `get_knowledge`

Полная запись по `id` (из результатов поиска): контент, детали, теги, проект,
важность, даты.

### `remember_knowledge`

Создаёт или обновляет запись. Дубликаты определяются по нормализованному тексту.
Полезно, когда пользователь явно просит «запомни».

| Аргумент | Тип |
|---|---|
| `title` | string, required |
| `content` | string, required |
| `kind` | string |
| `project` | string |
| `scope` | `profile` \| `project` \| `environment` |
| `theme` | string, до 64 символов |
| `tags` | string[] |
| `importance` | int 1–5 |

`scope` решает, куда карточка дойдёт: `profile` и `environment` видны в чате о
любом проекте, `project` — только в своём (и тогда `project` обязателен). Если
`scope` не передан: с проектом — `project`, без проекта — `profile`.

### `delete_knowledge`

Убирает карточки по id (из `search_knowledge` или `recent_knowledge`).

| Аргумент | Тип |
|---|---|
| `ids` | int[], 1–50, required |
| `mode` | `archive` (по умолчанию) \| `delete` |

`archive` прячет карточку из поиска и контекста, но оставляет её в админке —
её можно вернуть, поменяв статус. `delete` стирает запись навсегда вместе с
чанками и терминами индекса.

### `list_projects`

Проекты и количество записей.

### `recent_knowledge`

Последние знания за `days` (по умолчанию 30), с фильтром `project` и `limit`.

## Ресурсы

- `knowledge://index` — статистика, проекты, последние записи, профиль.
- `knowledge://entry/{id}` — запись в JSON (URI-шаблон).

## Промпт

- `about-me` — собранный профиль пользователя (важные `profile`, `preference`,
  `company`, `stack`, `style`, `decision`). Удобно вставлять в system prompt
  в начале сессии.

## Клиенты

### Claude Desktop / Cursor / Continue

```json
{
  "mcpServers": {
    "neiro-brain": {
      "url": "http://neiro-brain.local/mcp/knowledge",
      "headers": {
        "Authorization": "Bearer dev-brain-token"
      }
    }
  }
}
```

### Claude Code

```bash
claude mcp add --transport http neiro-brain http://neiro-brain.local/mcp/knowledge \
  --header "Authorization: Bearer dev-brain-token"
```

### stdio

```json
{
  "mcpServers": {
    "neiro-brain": {
      "command": "php",
      "args": ["C:\\OSPanel65\\home\\neiro-brain\\artisan", "mcp:start", "knowledge"]
    }
  }
}
```

## Проверка вручную

```bash
curl -X POST http://neiro-brain.local/mcp/knowledge \
  -H "Authorization: Bearer dev-brain-token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{
        "name":"search_knowledge",
        "arguments":{"query":"какой у меня стек","limit":3}
      }}'
```

Или интерактивно:

```bash
php artisan mcp:inspector mcp/knowledge
```
