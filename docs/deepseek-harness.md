# Подключение DeepSeek Harness по MCP

DSH — обычный MCP-клиент Neiro Brain: инструменты `mcp__neiro-brain__*` дают
агенту поиск и запись знаний. Модель к этому отношения не имеет — провайдер,
ключ и reasoning настраиваются в DSH как всегда, через Neiro Brain запросы к
моделям больше не идут.

```
DeepSeek Harness
   ├─ модель: провайдер DSH ──────────► API провайдера (напрямую)
   └─ MCP: neiro-brain ───────────────► http://127.0.0.1/mcp/knowledge
                                         Authorization: Bearer nb-…
```

## 1. Личный токен

1. В базе должен быть активный пользователь:

   ```powershell
   php artisan brain:user --email=owner@example.com --name="Владелец" --password="пароль"
   ```

2. Войти в `http://127.0.0.1/app` и открыть **Токены → Выпустить**.
3. Дать право `read + write` (MCP умеет писать и удалять), скопировать `nb-…`.

Токен показывается один раз, в базе лежит только SHA-256. Отзыв — там же.
Дев-токена и токена в адресе нет.

## 2. MCP-сервер в DSH

Файл профиля: `C:\Users\Admin\.dsh\profiles\desktop\cordis.patch.yml` (для CLI —
`~/.dsh/profiles/web/cordis.patch.yml`). Добавьте блок:

```yaml
- insert:
    - id: mcp-neiro-brain
      name: "@deepseek-ai/dsh-mcp-client"
      config:
        serverName: neiro-brain
        transport: streamable-http
        url: http://127.0.0.1/mcp/knowledge
        headers:
          Authorization: Bearer nb-…
```

- Пакет `@deepseek-ai/dsh-mcp-client` входит в поставку DSH — ставить отдельно
  не нужно.
- `streamable-http` — единственный транспорт: stdio у Neiro Brain удалён.
- Токен лежит в YAML открытым текстом; это локальный профиль, не коммитьте его.
- Если блок `insert` уже есть, добавьте элемент в существующий список, а не
  создавайте второй.

После перезапуска DSH (`Ctrl+C`, затем снова `dsh web --no-open` или перезапуск
приложения) в сессии появятся инструменты:

- `mcp__neiro-brain__get_project_context`
- `mcp__neiro-brain__search_knowledge`
- `mcp__neiro-brain__get_knowledge`
- `mcp__neiro-brain__remember_many`
- `mcp__neiro-brain__delete_knowledge`
- `mcp__neiro-brain__list_projects`
- `mcp__neiro-brain__recent_knowledge`

## 3. Модель

Ничего специального для Neiro Brain не нужно: выберите провайдера и модель в
DSH обычным способом (**Settings → Models**) или своим блоком `llm-pi-ai`.
Ключ провайдера остаётся в DSH (`~/.dsh/.env` или credentials), в Neiro Brain его
нет.

Проверка, что MCP подключён, а не модель:

> Найди в базе знаний, какой у меня стек и что мы решили по проекту.

Агент вызовет `search_knowledge`; при пустой базе честно скажет, что знаний нет.

## 4. Скилл `/brain`

```powershell
php artisan brain:skill            # ~/.agents/skills/brain/SKILL.md
php artisan brain:skill --force    # перезаписать
```

Тонкий файл на диске велит прочитать ресурс `knowledge://skill/brain` — полный
протокол сервер отдаёт сам, поэтому правила не отстают от версии кода.
`/brain compact` — слияние дублей: план, подтверждение, `remember_many` с
`supersedes`.

`instructions` MCP-сервер собирает на каждый запрос (базовые правила, правила
владельца, имя/язык/проекты), но **клиент кэширует их на сессию**. После смены
правил или профиля начните новую сессию.

## 5. Проверка без DSH

```bash
curl -sS http://127.0.0.1/mcp/knowledge \
  -H "Authorization: Bearer nb-…" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{
        "name":"search_knowledge","arguments":{"query":"стек","limit":3}}}'
```

Список инструментов:

```bash
curl -sS http://127.0.0.1/mcp/knowledge \
  -H "Authorization: Bearer nb-…" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

## 6. Если не работает

| Симптом | Причина |
|---|---|
| `401 invalid_token` | токен неверный, отозван или истёк; выпустите новый в `/app/tokens` |
| `403 insufficient_ability` | у токена только `read`; для MCP нужен `write` |
| `503 no_owner` | в базе нет активного пользователя: `php artisan brain:user …` |
| MCP-инструментов нет | неверный `url`/`serverName`, DSH не перезапущен |
| Инструменты есть, поиск пуст | база пустая или другой проект; сначала `remember_many` |
| `instructions` старые | клиент кэширует на сессию — начните новую |
| Модель не отвечает | это не Neiro Brain: проверьте провайдера и ключ в Settings → Models |
