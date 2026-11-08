# Neiro Brain — инструкции для агентов

## Что это

Laravel 13 + PostgreSQL + MoonShine + Laravel AI SDK + Laravel MCP.
База знаний для LLM: BM25-поиск, прозрачный OpenAI/Anthropic-прокси, MCP, админка.

## Команды

```powershell
$env:Path = 'C:\OSPanel65\modules\PHP-8.4;' + $env:Path
php artisan test
vendor\bin\pint
php artisan brain:stats
php artisan brain:search "запрос"
php artisan brain:sync --force
php artisan optimize:clear
```

## Правила

- PHP 8.3+, `declare(strict_types=1)` в новых файлах.
- Деньги/секреты не логировать; секреты — только `#[ShouldBeEncrypted]` в `app/Settings`.
- Поиск: `KnowledgeSearch` (не вызывать `Bm25Search` напрямую из контроллеров).
- Запись знаний: `KnowledgeWriter`, чтобы работали маскировка/дедуп/индексация.
- Новые поля знаний требуют миграцию, `$casts`, форму MoonShine и обновление индексатора.
- Прокси не должен менять тело ответа; только заголовки и system prompt.
- Тесты — PHPUnit, `RefreshDatabase`; настройки инициализируются в `Tests\TestCase`.
- UI-тексты — по-русски, код и имена — по-английски.

## Важные пути

- `app/Services/Brain/Bm25Search.php` — формула и бусты.
- `app/Services/Brain/Indexer.php` — чанки/термы.
- `app/Http/Controllers/Proxy/ProxyController.php` — прокси и SSE.
- `app/Mcp/Servers/KnowledgeServer.php` — регистрация MCP.
- `resources/views/moonshine/brain/*` — дашборд и настройки.
