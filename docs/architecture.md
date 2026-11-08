# Архитектура Neiro Brain

## Компоненты

| Компонент | Ответственность | Код |
|---|---|---|
| Прокси | Принимает OpenAI/Anthropic-запросы, подмешивает память, проксирует поток, пишет диалог | `app/Http/Controllers/Proxy/ProxyController.php` |
| Захват | Идемпотентно сохраняет сессии и сообщения | `app/Services/Brain/ConversationIngestor.php` |
| Суммаризация | Превращает диалог в атомарные знания (AI SDK или эвристика) | `app/Services/Brain/ExtractionPipeline.php` |
| Запись знаний | Маскировка, дедупликация по хешу, статус, проект, теги | `app/Services/Brain/KnowledgeWriter.php` |
| Индекс | Чанки + частоты термов | `app/Services/Brain/Indexer.php` |
| Поиск | Okapi BM25 + бусты + сниппеты | `app/Services/Brain/Bm25Search.php` |
| MCP | Инструменты/ресурсы/промпт для агентов | `app/Mcp/*` |
| Админка | Просмотр, правка, настройки, запуски | `app/MoonShine/*` |
| Планировщик | `brain:sync` каждые 15 минут | `routes/console.php` |

## Модель данных

```
knowledge_sources 1─* conversations 1─* messages
        │                  │
        │                  └──────* knowledge_entries
        └───────────────────────────────┘
knowledge_entries 1─* knowledge_chunks 1─* knowledge_terms
```

- `knowledge_entries.content_hash` — sha256 нормализованного текста, защита от дублей.
- `knowledge_chunks` — документы BM25; `token_count` — длина документа `|D|`.
- `knowledge_terms (chunk_id, term, tf)` — инвертированный индекс; `df` считается
  запросом `COUNT(DISTINCT chunk_id)`.
- `conversations.status`: `pending` → `processing` → `summarized` / `failed` / `skipped`.
- `search_logs` — журнал поисков (MCP, proxy, API, admin, cli).

## Поток запроса через прокси

1. `VerifyBrainToken` проверяет bearer-токен (или `x-api-key` для Anthropic).
2. Контроллер определяет upstream по настройкам (`mock`, `openai`, `anthropic`, …).
3. Последний user-message нормализуется и ищется в BM25 (top K, только `approved`).
4. Найденные факты собираются в блок «Долговременная память» и добавляются в system.
5. Запрос уходит в upstream **с ключом из настроек**, а не клиентским.
6. Ответ (JSON или SSE) проходит байт-в-байт; тело не переписывается.
7. Пока клиент получает ответ, прокси сохраняет user/assistant сообщения и usage.

Если upstream не настроен, `mock` отвечает в формате выбранного протокола и цитирует
найденную память — это позволяет проверять всю цепочку без ключей.

## Суммаризация

```
messages → mask → transcript → [AI agent | heuristic] → entries
        → KnowledgeWriter (mask/dedupe/status) → observer → Indexer
```

- AI-провайдер создаётся `AiProviderFactory` из настроек (ключ/URL/модель).
- Схема ответа жёсткая: `entries[]` с `kind`, `title`, `content`, `details`,
  `project`, `tags`, `importance`, `confidence`.
- При ошибке провайдера или пустом ответе — fallback на `HeuristicExtractor`
  (если включён `heuristic_fallback`).
- Эвристика берёт **только сообщения пользователя**, отбрасывает вопросы, код и
  служебные фразы, классифицирует по ключевым словам.
- Статус: `approved` при `sync.auto_approve = true`, иначе `draft`.

## BM25

```
N      = число чанков (approved, с учётом фильтров)
avgdl  = средняя длина чанка
df(q)  = число чанков с термом q
IDF(q) = ln(1 + (N − df + 0.5) / (df + 0.5))
score  = Σ IDF(q) · tf·(k1+1) / (tf + k1·(1 − b + b·|D|/avgdl))
```

Пост-обработка:

```
score *= 0.4 + 0.6·coverage          # сколько термов запроса покрыто
score *= 1.25                        # точная фраза в чанке
score *= 1 + 0.08·(importance − 3)   # важность
score *= kind_weight[kind]           # тип знания
score *= 1 + 0.15·exp(−age/90)       # свежесть
```

Кандидаты ограничены `retrieval.candidate_limit` (20 000 строк термов), чтобы
запрос оставался предсказуемым на большой базе. Для персональной базы этого
достаточно; при десятках тысяч документов стоит перейти на `tsvector`/`ts_stat`
или материализованное `df`.

## Отказоустойчивость

- Ошибка AI → `failed` у диалога + fallback; `brain:sync` продолжает остальные.
- Ошибка индексации → `indexed_at = null`, следующий sync переиндексирует.
- Ошибка источника → `last_error` у источника, остальные источники обрабатываются.
- `withoutOverlapping(20)` не даёт запускам наслаиваться.
- Прокси не падает, если upstream вернул 4xx/5xx: статус и тело передаются клиенту.
