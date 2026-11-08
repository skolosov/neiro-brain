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
- Upstream — DeepSeek (`deepseek-flash`), ключ хранится в настройках Neiro Brain
  в зашифрованном виде (`APP_KEY`).

## 1. Neiro Brain: upstream DeepSeek

Админка → **Настройки → Прокси для харнессов**:

| Поле | Значение |
|---|---|
| Upstream | `deepseek` |
| Base URL | `https://api.deepseek.com` |
| Модель | `deepseek-flash` |
| API-ключ upstream | ключ DeepSeek |

Проверка:

```bash
curl http://127.0.0.1/v1/models \
  -H "Authorization: Bearer dev-proxy-token"
```

и реальный запрос:

```bash
curl http://127.0.0.1/v1/chat/completions \
  -H "Authorization: Bearer dev-proxy-token" \
  -H "Content-Type: application/json" \
  -d '{"model":"deepseek-flash","messages":[{"role":"user","content":"Какой у меня стек?"}]}'
```

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
        baseURL: http://127.0.0.1/v1
        headers:
          Authorization: Bearer dev-proxy-token
        models:
          - id: deepseek-flash
            name: DeepSeek Flash via Neiro Brain
          - id: deepseek-v4-pro
            name: DeepSeek V4 Pro via Neiro Brain
        compat:
          supportsDeveloperRole: false
          maxTokensField: max_tokens
```

В UI: **Settings → Models → Neiro Brain (DeepSeek)** → выбрать `deepseek-flash`.
Новые сессии пойдут через прокси; старые сохраняют свою модель.

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
| `MISSING_CREDENTIAL` | в provider нет `headers`/`apiKeyEnv` |
| 401 от прокси | неверный `dev-proxy-token` |
| 503 от прокси | не задан ключ/URL DeepSeek в админке |
| Нет провайдера в Models | DSH не перечитал `cordis.patch.yml`; обновите страницу/перезапустите DSH |
| MCP-инструментов нет | проверьте `serverName` и `url`; DSH должен быть запущен после правки |
| Долгий ответ | DeepSeek V4 Flash думает; для коротких задач выбирайте `reasoningEffort: off` |
