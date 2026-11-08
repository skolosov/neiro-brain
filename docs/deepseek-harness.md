# DeepSeek Harness + Neiro Brain

Подключение проверено на DeepSeek Harness (`DSH_HOME=C:\Users\Admin\.dsh`, профиль `desktop`).

## Схема

```
DeepSeek Harness
   ├─ model: Neiro Brain (DeepSeek) ──► http://127.0.0.1/v1 ──► DeepSeek API
   │                                      (memory + capture)
   └─ MCP: neiro-brain ───────────────► http://127.0.0.1/mcp/knowledge
```

- Все запросы DSH идут через Neiro Brain: пользовательская память подмешивается в prompt,
  диалог сохраняется в PostgreSQL.
- MCP-инструменты `mcp__neiro-brain__*` позволяют агенту искать и запоминать знания.
- **Провайдер, ключ, модель и reasoning выбираются в DSH.** Neiro Brain не хранит
  ключ DeepSeek; он получает провайдерский `Authorization` от харнесса и передаёт его
  upstream. В Neiro Brain остаётся только собственный proxy-токен.

## 1. Neiro Brain: без ключа провайдера

Админка → **Настройки → Прокси для харнессов**: там есть только

- Bearer-токен для харнесса (`dev-proxy-token`);
- top K и лимит блока памяти;
- переключатели памяти/захвата.

Провайдера, Base URL и API-ключа DeepSeek в админке нет. Харнесс присылает:

| Заголовок | Значение |
|---|---|
| `Authorization` | `Bearer <DEEPSEEK_API_KEY>` — ключ провайдера |
| `X-Neiro-Proxy-Token` | `dev-proxy-token` — доступ к Neiro Brain |
| `X-Neiro-Upstream` | `deepseek` — какой upstream использовать |

Проверка (ключ подставьте свой):

```bash
curl http://127.0.0.1/v1/chat/completions \
  -H "Authorization: Bearer $DEEPSEEK_API_KEY" \
  -H "X-Neiro-Proxy-Token: dev-proxy-token" \
  -H "X-Neiro-Upstream: deepseek" \
  -H "Content-Type: application/json" \
  -d '{"model":"deepseek-flash","reasoning_effort":"high","messages":[{"role":"user","content":"Какой у меня стек?"}]}'
```

Старый режим тоже работает: если `X-Neiro-Proxy-Token` не передан, `Authorization`
считается токеном Neiro Brain, а ключ провайдера берётся из `DEEPSEEK_API_KEY`
в окружении PHP. Но для DSH используется режим выше — ключ остаётся в харнессе.

## 2. DSH: провайдер модели

Файл: `C:\Users\Admin\.dsh\profiles\desktop\cordis.patch.yml`

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
            name: DeepSeek Flash via Neiro Brain
            reasoningEfforts:
              off:
              low: low
              medium: high
              high: max
          - id: deepseek-v4-pro
            name: DeepSeek V4 Pro via Neiro Brain
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
```

В UI: **Settings → Models → Neiro Brain (DeepSeek)** → выбрать `deepseek-flash`
и уровень размышления. Новые сессии пойдут через прокси; старые сохраняют свою модель.

Base URL и модели можно менять в UI, но **кастомные заголовки**
(`X-Neiro-Proxy-Token`, `X-Neiro-Upstream`) задаются только в `cordis.patch.yml`
(в Settings есть кнопка **Open configuration file**). Без них прокси не поймёт,
что `Authorization` — это ключ провайдера.

`reasoningEfforts` — это отображение уровней DSH на то, что понимает DeepSeek:
`low → low`, `medium → high`, `high → max`. Выбор остаётся в харнессе; прокси
ничего не подставляет.

`compat` нужен, потому что прокси — не OpenAI: без него pi-ai может отправить
`developer`-роль и `max_completion_tokens`, которые gateway отвергнет.

## 3. DSH: MCP-сервер

Тот же файл, отдельный `insert`:

```yaml
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

После перезагрузки страницы DSH появляются инструменты:

- `mcp__neiro-brain__search_knowledge`
- `mcp__neiro-brain__get_knowledge`
- `mcp__neiro-brain__remember_knowledge`
- `mcp__neiro-brain__list_projects`
- `mcp__neiro-brain__recent_knowledge`

Проверка в сессии:

> Найди в памяти, какой у меня стек и что мы решили по проекту Sklad.

## 4. Токены

- `dev-proxy-token` — токен прокси из админки (Настройки → Прокси → Bearer-токен).
- `dev-mcp-token` — токен MCP (Настройки → Безопасность).

Если меняете токены в админке, обновите их в `cordis.patch.yml` и перезагрузите DSH.
Токены в YAML лежат открыто — это локальный профиль; не коммитьте его в публичный репозиторий.

## 5. Если не работает

| Симптом | Причина |
|---|---|
| `MISSING_CREDENTIAL` | DSH не нашёл `DEEPSEEK_API_KEY`; добавьте ключ в Settings → Models |
| 401 от прокси | неверный `X-Neiro-Proxy-Token` (или `Authorization`, если режим старый) |
| 503 от прокси | харнесс не прислал `Authorization`/`X-Neiro-Upstream`, либо upstream недоступен |
| Нет провайдера в Models | DSH не перечитал `cordis.patch.yml`; обновите страницу/перезапустите DSH |
| MCP-инструментов нет | проверьте `serverName` и `url`; DSH должен быть запущен после правки |
| Долгий ответ | DeepSeek V4 Flash думает; для коротких задач выбирайте уровень `low` |
