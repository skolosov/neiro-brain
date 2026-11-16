# Neiro Brain — инструкции для агентов

## Что это

Laravel 13 + PostgreSQL + MoonShine + Laravel MCP.
База знаний для LLM: BM25-поиск, MCP, HTTP API, админка. Знания пишутся
только через MCP, API и админку — исходящих запросов к моделям нет.

## Команды

```powershell
$env:Path = 'C:\OSPanel65\modules\PHP-8.4;' + $env:Path
php artisan test
vendor\bin\pint
php artisan brain:stats
php artisan brain:search "запрос"
php artisan optimize:clear
```

## Правила

- PHP 8.3+, `declare(strict_types=1)` в новых файлах.
- Деньги/секреты не логировать; секреты — только `#[ShouldBeEncrypted]` в `app/Settings`.
- Поиск: `KnowledgeSearch` (не вызывать `Bm25Search` напрямую из контроллеров).
- Запись знаний: `KnowledgeWriter`, чтобы работали маскировка/дедуп/индексация.
- Новые поля знаний требуют миграцию, `$casts`, форму MoonShine и обновление индексатора.
- Знания пишутся только через MCP, HTTP API и админку.
- Тесты — PHPUnit, `RefreshDatabase`; настройки инициализируются в `Tests\TestCase`.
- UI-тексты — по-русски, код и имена — по-английски.

## Важные пути

- `app/Services/Brain/Bm25Search.php` — формула и бусты.
- `app/Services/Brain/Indexer.php` — чанки/термы.
- `app/Mcp/Servers/KnowledgeServer.php` — регистрация MCP.
- `resources/views/moonshine/brain/*` — дашборд и настройки.
