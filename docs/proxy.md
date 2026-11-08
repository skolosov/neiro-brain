# Прокси

Прокси — это обычный OpenAI/Anthropic API, который дополнительно знает о пользователе.
Клиент не должен знать, что перед ним Neiro Brain.

## Endpoints

| Метод | Путь | Протокол |
|---|---|---|
| POST | `/v1/chat/completions` | OpenAI Chat Completions |
| POST | `/v1/messages` | Anthropic Messages |
| GET | `/v1/models` | список моделей upstream/mock |

Тело запроса и ответа не модифицируется. В ответ добавляются только заголовки
`X-Neiro-Memory-Hits` и `X-Neiro-Conversation`.

## Настройка upstream

Админка → **Настройки** → **Прокси**:

| Поле | Значение |
|---|---|
| Upstream | `mock`, `openai`, `anthropic`, `deepseek`, `openrouter`, `ollama` |
| Base URL | переопределяет стандартный адрес (например, свой шлюз) |
| API key | ключ провайдера; клиенту его знать не нужно |
| Модель | модель по умолчанию, если клиент не передал |
| Bearer-токен | токен, который предъявляет харнесс |

Стандартные base URL: OpenAI `https://api.openai.com/v1`, DeepSeek
`https://api.deepseek.com/v1`, OpenRouter `https://openrouter.ai/api/v1`,
Ollama `http://127.0.0.1:11434/v1`, Anthropic `https://api.anthropic.com/v1`.

`mock` работает без сети: отвечает в формате выбранного протокола и перечисляет
найденную память. Удобно для демо и проверки интеграции.

## Инъекция памяти

1. Берётся последнее сообщение с ролью `user`.
2. BM25 ищет `proxy.top_k` записей (по умолчанию 5).
3. Блок «Долговременная память» добавляется:
   - в OpenAI — к первому `system`-сообщению или новым `system` в начало;
   - в Anthropic — к `system` (строка или массив блоков).
4. Блок ограничен `proxy.max_context_chars` (по умолчанию 4000 символов).

Отключить на один запрос: `X-Neiro-Memory: off`.  
Отключить глобально: настройка «Подмешивать память».

## Захват диалога

- Включается настройкой «Записывать диалоги».
- Сессия определяется по `X-Neiro-Session`, `X-Session-Id`, `metadata.session_id`,
  `metadata.user_id`; иначе — по user-agent + system prompt + дата.
- В базу попадают только `user` и `assistant`, без system и tool.
- Дубликаты отсекаются по `sha256(role + content)`.
- Streaming-ответ аккумулируется из SSE и сохраняется после завершения потока.
- Новые сообщения переводят диалог в `pending`; `brain:sync` суммаризирует его
  после периода тишины (`sync.quiet_minutes`).

## Streaming

- SSE проходит без изменений, включая `[DONE]` (OpenAI) и `message_stop` (Anthropic).
- `Content-Type: text/event-stream`, `X-Accel-Buffering: no`.
- При ошибке upstream до начала потока клиент получает исходный статус и JSON.
- `ignore_user_abort(true)` — ответ сохраняется, даже если клиент отключился.

## Ошибки

| Ситуация | Ответ |
|---|---|
| Неверный токен | 401 |
| Нет `messages` | 422 |
| Не задан upstream key/url | 503 с понятным сообщением |
| Ошибка upstream | статус и тело upstream без изменений |

## Пример

```bash
curl http://neiro-brain.local/v1/chat/completions \
  -H "Authorization: Bearer dev-proxy-token" \
  -H "Content-Type: application/json" \
  -H "X-Neiro-Session: demo" \
  -d '{
    "model": "gpt-4o-mini",
    "messages": [{"role":"user","content":"Какой у меня стек?"}]
  }'
```
