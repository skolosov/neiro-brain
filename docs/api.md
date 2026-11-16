# HTTP API

База: `http://neiro-brain.local/api/v1`
Авторизация: `Authorization: Bearer nb-…` — личный токен из админки
(раздел «Токены»). Владелец берётся из токена: чужие карточки недоступны так же,
как несуществующие.

Способности:

- `read` — `GET /search`, `GET /entries/{id}`, `GET /projects`, `GET /stats`;
- `write` — `POST /entries`. Право `write` подразумевает `read`.

Приёма диалогов (`POST /ingest`) больше нет: знания пишутся через MCP, этот API
и админку.

## GET /search

Параметры: `q` (обязательно, 2–2000 символов), `project`, `kinds[]`, `limit`
(1–50, по умолчанию 8).

```bash
curl "http://neiro-brain.local/api/v1/search?q=стек&project=neiro-brain&limit=3" \
  -H "Authorization: Bearer nb-…"
```

```json
{
  "data": [
    {"id": 42, "title": "Стек", "content": "Laravel 13…", "kind": "stack",
     "project": "neiro-brain", "tags": ["laravel"], "importance": 4,
     "score": 3.71, "snippet": "…", "matched_terms": ["laravel"]}
  ],
  "meta": {"terms": ["laravel"], "candidates": 1, "took_ms": 4}
}
```

## GET /entries/{id}

Полная карточка владельца.

```bash
curl http://neiro-brain.local/api/v1/entries/42 \
  -H "Authorization: Bearer nb-…"
```

Ответ: `{"data": {…}}`. Чужой или несуществующий id — `404`.

## POST /entries

Ручная запись. Обязательны `title` и `content`.

```json
{
  "title": "Стиль кода",
  "content": "Пользователь пишет тесты на PHPUnit.",
  "details": "Покрывает граничные случаи.",
  "kind": "style",
  "project": "neiro-brain",
  "tags": ["testing"],
  "importance": 4
}
```

`201` при создании, `200` при обновлении карточки с тем же нормализованным
текстом в пределах владельца. Ответ: `{"data": {…}, "created": true}`.
`scope` и `status` через API не задаются: карточка сразу опубликована, область
определяется по `kind` и `project`.

## GET /projects

Проекты владельца с числом опубликованных карточек:

```json
{"data": [{"project": "neiro-brain", "entries": 12}]}
```

## GET /stats

```json
{"data": {"entries": 42}}
```

Считаются только опубликованные карточки владельца.

## Коды ошибок

| Код | Причина |
|---|---|
| 401 | Нет токена, токен отозван/истёк или пользователь отключён |
| 403 | У токена нет нужной способности (`insufficient_ability`) |
| 404 | Карточка не найдена или принадлежит другому владельцу |
| 422 | Ошибка валидации; пустая карточка после вырезания секретов |
| 503 | Нет активного пользователя-владельца (`no_owner`, только дев-токен) |

Тело ошибки: `401` и `403` — `{"error": {"type", "code", "message"}}`;
`404` и `422` — стандартный ответ Laravel (`{"message", "errors"}`).

## Токены

Выпуск и отзыв — в админке, раздел «Токены». Токен показывается один раз; в базе
лежит только SHA-256. `last_used_at` обновляется не чаще раза в минуту.
