# Архитектура Neiro Brain

## Слои

```
Вход            MCP /mcp/knowledge   HTTP /api/v1/*   Кабинет /app   Админка /admin
Личность        VerifyBrainToken     VerifyBrainToken SetOwnerFromAuth  (сессия MoonShine)
Владелец        OwnerContext (scoped) ────────────────────────────────────────────────
Сервисы         KnowledgeSearch, Bm25Search, KnowledgeWriter, ProjectContext,
                Indexer, SecretMasker, InstructionsBuilder, TokenIssuer
Модели          User, ApiToken, KnowledgeEntry, KnowledgeChunk, KnowledgeTerm
База            PostgreSQL: карточки → чанки → инвертированный индекс
```

| Компонент | Ответственность | Код |
|---|---|---|
| MCP | Инструменты, ресурсы, промпт, динамические `instructions` | `app/Mcp/*` |
| API | Поиск, карточки, проекты, статистика | `app/Http/Controllers/Api/BrainApiController.php` |
| Кабинет | Свои карточки, проекты, граф, токены, профиль | `app/Http/Controllers/App/*` |
| Админка | Все карточки, люди, токены, настройки | `app/MoonShine/*` |
| Токены | Выпуск `nb-…`, SHA-256, способности `read`/`write` | `app/Services/Auth/TokenIssuer.php`, `app/Models/ApiToken.php` |
| Владелец | Контекст запроса, `fail-closed` | `app/Support/OwnerContext.php` |
| Запись | Маскировка, дедуп по `(owner_id, content_hash)`, замена версий | `app/Services/Brain/KnowledgeWriter.php` |
| Поиск | BM25 по корпусу владельца, бусты, сниппеты | `app/Services/Brain/KnowledgeSearch.php`, `Bm25Search.php` |
| Индекс | Чанки и частоты термов | `app/Services/Brain/Indexer.php` |
| Инструкции | Базовые правила + правила владельца + медленная динамика | `app/Services/Brain/InstructionsBuilder.php` |

Слоёв «прокси», «захват переписки», «суммаризация» и «AI-провайдер» больше нет:
сервер не делает исходящих запросов и не хранит ключей провайдеров.

## Владелец — fail-closed

- `OwnerContext` — scoped-синглтон на запрос/команду/тест.
- `owner()` бросает `LogicException`, если контекст пуст. Это главная страховка:
  забыть фильтр нельзя, потому что без владельца код падает.
- `KnowledgeEntry` имеет глобальный скоуп `owner`: при заполненном контексте
  выборка ограничена `owner_id`. Пустой контекст не накладывает скоуп — это
  осознанный обход для админки MoonShine, миграций и консоли без `--user`.
- Владелец никогда не приходит аргументом инструмента или контроллера. Для мест,
  где он известен явно (дедуп, поиск по id в API, команды), есть
  `scopeOwnedBy()` — лучше сказать это вслух, чем полагаться на невидимое
  состояние контейнера.
- `POST /api/v1/entries`, `GET /api/v1/entries/{id}` и кабинет поэтому отдают 404
  на чужой id, а не 403: чужая карточка неотличима от несуществующей.

## Модель данных

```
users 1─* api_tokens
  │
  └─1─* knowledge_entries 1─* knowledge_chunks 1─* knowledge_terms
            │
            └─ superseded_by → knowledge_entries.id   (история версий)
```

- `knowledge_entries.owner_id` — not null, `cascadeOnDelete`.
- `unique(owner_id, content_hash)`: одинаковый текст у двух владельцев — две
  карточки. `content_hash` — sha256 нормализованного content.
- `knowledge_chunks` — документы BM25, `token_count` — длина `|D|`.
- `knowledge_terms (chunk_id, term, tf)` — инвертированный индекс; `df` считается
  запросом `COUNT(DISTINCT chunk_id)`.
- `api_tokens` — `token_hash` (sha256, unique), `abilities` json, `last_used_at`,
  `expires_at`. Sanctum не используется: одна таблица, один формат, две
  способности.
- `knowledge_entries.superseded_by` смотрит вперёд: старая версия → замена.
  Глобальный скоуп `current` (`whereNull(superseded_by)`) прячет историю везде,
  кроме экранов истории (`KnowledgeEntry::withHistory`, `historyOf`).

## Запись

`remember_many` — единственный массовый вход из MCP (1–20 карточек). Дальше всё
идёт через один `KnowledgeWriter::write()`:

```
items → валидация по каждой → SecretMasker → дедуп (owner_id, content_hash)
      → knowledge_entries → Indexer → чанки + термы
      → supersedes: старые карточки получают superseded_by и переиндексируются
```

- Дубль не создаёт вторую строку: обновляются теги, важность, project,
  `last_verified_at`, ответ помечается `updated`.
- `supersedeWith()` снимает скоуп владельца только чтобы отличить «чужой id» от
  «несуществующего»: чужие id попадают в `ignored`, но не меняются.
- Частичный успех: отклонённая карточка не отменяет остальные, ответ содержит
  статус и причину по каждой.
- Новые карточки всегда `approved`; «убрать с глаз» — архив через
  `delete_knowledge.mode=archive`.

## Поиск

```
N      = число чанков владельца (approved)
avgdl  = средняя длина его чанка
df(q)  = число его чанков с термом q
IDF(q) = ln(1 + (N − df + 0.5) / (df + 0.5))
score  = Σ IDF(q) · tf·(k1+1) / (tf + k1·(1 − b + b·|D|/avgdl))
```

Фильтр владельца стоит **внутри `$base`-замыкания**, до расчёта `N`, `avgdl` и
`df`. Если перенести его на выдачу, IDF начнёт считаться по чужому корпусу:
редкий термин соседа изменит оценки, а `candidates` включит чужие чанки. Тест
`OwnerIsolationTest` фиксирует это через `candidates` и неизменность score.

После BM25: покрытие термов (`min_coverage`), точная фраза, важность, тип знания,
свежесть. Параметры — `RetrievalSettings`. Кандидаты ограничены
`retrieval.candidate_limit`.

## MCP

- Инструменты читают и пишут только через сервисы, то есть только в пределах
  владельца из токена.
- `InstructionsBuilder` собирает `instructions` на каждый запрос: базовые
  правила, `RulesSettings::instructions_extra`, имя/язык/число карточек/до пяти
  проектов. Клиент кэширует текст на сессию — это нормально, быстрая динамика
  приходит из инструментов.
- Ресурсы: `knowledge://index`, `knowledge://entry/{id}`, `knowledge://skill/brain`.
  Скилл читается с диска без кэша, чтобы правила не отставали от кода.
- stdio удалён: он не проходил через токен и писал знания случайному владельцу.

## Отказоустойчивость

- Пустой `OwnerContext` — исключение, а не выборка «всех».
- Ошибка индексации оставляет `indexed_at = null`; `brain:reindex --user`
  пересобирает индекс владельца.
- Отключённый пользователь не проходит токен-проверку, даже если токен цел.
- Удаление пользователя каскадом уносит его карточки, чанки, термы и токены;
  чужие остаются.

## Устаревшая схема

`docs/knowledge-model.svg` описывает прежнюю модель с диалогами и источниками и
больше не соответствует коду. Файл оставлен как история, перерисовывать его не
нужно; в документации на него не ссылаемся.
