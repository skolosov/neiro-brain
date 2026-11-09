# Эксплуатация

## OpenServer

- PHP 8.4, расширения `pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `openssl`, `curl`.
- PostgreSQL 14+.
- Домен `neiro-brain.local` → `C:\OSPanel65\home\neiro-brain\public`.
- `APP_URL` должен совпадать с доменом, иначе ссылки в админке и MCP будут неверными.

Если OpenServer слушает PostgreSQL на `127.0.1.46`, оставьте `DB_HOST=127.0.1.46`.
На обычном сервере — `127.0.0.1`.

## Планировщик

`brain:sync` объявлен на каждые 15 минут. Планировщик Laravel нужно вызывать раз в минуту.

Windows, Task Scheduler:

```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "Neiro Brain Scheduler" ^
  /TR "\"C:\OSPanel65\modules\PHP-8.4\php.exe\" \"C:\OSPanel65\home\neiro-brain\artisan\" schedule:run" /F
```

Либо `scripts\scheduler.cmd` (отредактируйте путь к PHP) как задачу «каждую минуту».

Linux:

```cron
* * * * * cd /var/www/neiro-brain && php artisan schedule:run >> /dev/null 2>&1
```

Проверка:

```bash
php artisan schedule:list
php artisan brain:sync --force
```

## Очередь

По умолчанию `QUEUE_CONNECTION=sync` — суммаризация идёт в процессе cron.
Это надёжно для персональной базы и не требует воркера.

Для больших объёмов:

```env
QUEUE_CONNECTION=database
```

```bash
php artisan queue:work --sleep=3 --tries=3
```

`brain:sync` сейчас вызывает суммаризацию напрямую; воркер нужен, если вы
переведёте `SummaryService` на `dispatch()` или добавите свои задания.

## Бэкапы

База + `.env` (там `APP_KEY`, без которого не расшифруются секреты):

```powershell
pg_dump -h 127.0.1.46 -U postgres -Fc neiro_brain > C:\backup\neiro_brain.dump
```

Восстановление:

```powershell
pg_restore -h 127.0.1.46 -U postgres -d neiro_brain C:\backup\neiro_brain.dump
```

Не храните дампы в публичной папке: в знаниях могут быть чувствительные данные,
пусть и с маскировкой.

## Обновление схемы

```bash
php artisan migrate
```

Новые настройки: `SettingsInitializer` дописывает отсутствующие свойства при
следующем сохранении/запуске. Если добавляете поле в `app/Settings/*`, обновите
форму в `resources/views/moonshine/brain/settings.blade.php` и контроллер.

## Диагностика

| Симптом | Что проверить |
|---|---|
| 500 на `/admin` | `storage/logs/laravel.log`, кэш MoonShine: `php artisan optimize:clear` |
| Прокси 401 | токен в админке vs `Authorization`/`x-api-key` |
| Прокси 503 | upstream URL/ключ в настройках |
| Память не подмешивается | `X-Neiro-Memory: off`, `inject_memory`, пустая база, `min_coverage` |
| Поиск пуст | `php artisan brain:search "..."`, `indexed_at`, статус `approved` |
| Cron не идёт | Task Scheduler, `schedule:list`, `withoutOverlapping` |
| Секреты в базе | проверить `security.mask_secrets`, переиндексировать записи |

Полезные команды:

```bash
php artisan brain:stats
php artisan brain:search "запрос" --project=Sklad
php artisan brain:reindex
php artisan brain:sync --force --json
php artisan mcp:inspector mcp/knowledge
```

## Безопасность

- Админка — отдельная сессия MoonShine; смените пароль сидера.
- Сидер — демо: не запускайте `db:seed` на рабочей базе (он вызывает AI и пишет
  выдуманные факты). См. README, раздел «Что реально нужно из сидера».
- Прокси/MCP/API — разные bearer-токены, меняйте их при компрометации.
- Секреты в настройках шифруются `APP_KEY`; бэкапьте `.env`.
- Маскировка секретов работает до записи, но не заменяет политику доступа:
  не отправляйте в базу то, что нельзя хранить.
- Публичный доступ — только HTTPS, желательно за nginx с ограничением IP.
- `APP_DEBUG=false` в продакшене.
