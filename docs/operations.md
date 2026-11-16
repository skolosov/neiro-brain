# Эксплуатация

## Окружение

- PHP 8.4, расширения `pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `openssl`, `curl`.
- PostgreSQL 14+.
- Домен `neiro-brain.local` → `C:\OSPanel65\home\neiro-brain\public`.
- `APP_URL` должен совпадать с доменом, иначе ссылки в админке будут
  неверными.

Если OpenServer слушает PostgreSQL на `127.0.1.46`, оставьте `DB_HOST=127.0.1.46`.
На обычном сервере — `127.0.0.1`.

## Планировщик и очередь

Не нужны. Расписания нет: знания пишутся только по запросу агента. Задания в
очередь не ставятся, воркер не требуется. Расписания в `routes/console.php`
больше нет.

## Команды

```powershell
# люди
php artisan brain:user --email=owner@example.com --name="Владелец" --password="пароль"
php artisan brain:user --list
php artisan brain:user --disable=owner@example.com

# знания, всегда с обязательным владельцем
php artisan brain:stats   --user=owner@example.com
php artisan brain:search  "стек" --user=owner@example.com --project=neiro-brain
php artisan brain:reindex --user=owner@example.com

# скилл /brain на диск харнесса
php artisan brain:skill
php artisan brain:skill --force
```

`--user` принимает id или email. Без него команда завершается ошибкой: владелец
не угадывается, чтобы не пересчитать чужой индекс.

## Бэкапы

База + `.env` (там `APP_KEY`, без которого не расшифруются секреты настроек):

```powershell
pg_dump -h 127.0.1.46 -U postgres -Fc neiro_brain > C:\backup\neiro_brain.dump
```

Восстановление:

```powershell
pg_restore -h 127.0.1.46 -U postgres -d neiro_brain C:\backup\neiro_brain.dump
```

Не храните дампы в публичной папке: в карточках могут быть чувствительные данные,
пусть и с маскировкой.

## Обновление схемы

```bash
php artisan migrate
```

Миграции `2026_11_16_*` создают владельцев и `api_tokens`; на боевой базе их
запускает человек после проверки, а не деплой-скрипт. Бэкап — до миграции.

Новые настройки: `SettingsInitializer` дописывает отсутствующие свойства при
следующем сохранении/запуске. Если добавляете поле в `app/Settings/*`, обновите
форму в `resources/views/moonshine/brain/settings.blade.php` и контроллер.

## Диагностика

| Симптом | Что проверить |
|---|---|
| 500 на `/admin` | `storage/logs/laravel.log`, `php artisan optimize:clear` |
| 401 от MCP/API | токен в админке, отозван ли, не истёк ли, не отключён ли человек |
| 403 `insufficient_ability` | у токена только `read`, а нужна запись: выпустить с `write` |
| 503 `no_owner` | нет активного пользователя: `php artisan brain:user …` |
| MCP-инструментов нет | `url`, заголовок `Authorization`, перезапуск харнесса |
| `instructions` старые | клиент кэширует их на сессию: начать новую |
| Поиск пуст | `php artisan brain:search "..." --user=…`, `indexed_at`, статус `approved` |
| Секреты в базе | `security.mask_secrets`, переиндексировать карточки |
| Долгий поиск | `retrieval.candidate_limit`, размер корпуса владельца |

Полезные команды:

```bash
php artisan brain:stats --user=owner@example.com
php artisan brain:search "запрос" --user=owner@example.com
php artisan brain:reindex --user=owner@example.com
php artisan optimize:clear
```

## Безопасность

- Админка — отдельная сессия MoonShine; смените пароль сидера.
- Сидер — демо: не запускайте `db:seed` на рабочей базе.
- Токены — личные, в таблице `api_tokens`; отзыв в админке (раздел «Токены») действует сразу.
  Способность `write` даёт право записи и удаления — выдавайте её осознанно.
- Дев-токен `BRAIN_DEV_TOKEN` работает только при `APP_ENV=local` и только с
  localhost; на публичном сервере он бесполезен. Не полагайтесь на него в проде.
- Секреты в настройках шифруются `APP_KEY`; бэкапьте `.env`.
- Маскировка секретов работает до записи, но не заменяет политику доступа.
- Публичный доступ — только HTTPS, желательно с ограничением IP.
- `APP_DEBUG=false` в продакшене.
