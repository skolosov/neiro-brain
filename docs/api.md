# HTTP API

База: `http://neiro-brain.local/api/v1`  
Авторизация: `Authorization: Bearer <API-токен>` (админка → Настройки → Безопасность).

## POST /ingest

Принимает диалог и (опционально) сразу суммаризирует его.

```json
{
  "external_id": "chat-2026-11-08",
  "title": "Обсуждение архитектуры",
  "project": "neiro-brain",
  "harness": "custom",
  "metadata": {"source": "my-app"},
  "summarize": true,
  "messages": [
    {"role": "user", "content": "Я предпочитаю модульный монолит.", "occurred_at": "2026-11-08T12:00:00+03:00"},
    {"role": "assistant", "content": "Понял."}
  ]
}
```

Ответ `201`:

```json
{
  "conversation_id": 42,
  "messages_added": 2,
  "status": "summarized",
  "summary": {"status": "summarized", "created": 1, "updated": 0, "engine": "heuristic"}
}
```

Идемпотентность: повторный `external_id` дополняет диалог, одинаковые сообщения
не дублируются.

## GET /search

Параметры: `q` (обязательно), `project`, `kinds[]`, `limit` (1–50).

```bash
curl "http://neiro-brain.local/api/v1/search?q=стек&project=neiro-brain&limit=3" \
  -H "Authorization: Bearer dev-api-token"
```

Ответ: `data[]` с `id`, `title`, `content`, `kind`, `project`, `tags`, `importance`,
`score`, `snippet`, `matched_terms`; `meta` с термами, числом кандидатов и временем.

## GET /entries/{id}

Полная запись.

## POST /entries

Ручная запись.

```json
{
  "title": "Стиль кода",
  "content": "Пользователь пишет тесты на Pest.",
  "details": "Покрывает граничные случаи.",
  "kind": "style",
  "status": "approved",
  "project": "neiro-brain",
  "tags": ["testing"],
  "importance": 4
}
```

`201` при создании, `200` при обновлении существующей записи (по хешу текста).

## GET /projects

Список проектов с количеством записей.

## GET /stats

Количество записей, черновиков, диалогов, поисков.

## Коды ошибок

| Код | Причина |
|---|---|
| 401 | Нет/неверный токен |
| 422 | Ошибка валидации |
| 404 | Запись не найдена |
