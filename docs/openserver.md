# OpenServer 6: локальный запуск и доступ снаружи

Проект уже настроен как домен OpenServer:

| Что | Значение |
|---|---|
| Домен | `http://neiro-brain.local` |
| Админка | `http://neiro-brain.local/admin` |
| Локальная сеть | `http://192.168.0.107` |
| HTTPS | `https://neiro-brain.local` (локальный сертификат, предупреждение браузера) |
| Внешний IP | `95.79.97.24` (добавлен алиасом) |
| HTTP-движок | Apache 2.4 |
| PHP | PHP-8.4 (php-fpm, `127.0.1.40:9000`) |
| PostgreSQL | 14 (OpenServer, `127.0.1.46:5432`) |

## Файлы проекта

```
.osp/
├── project.ini   # домен, bind_ip, алиасы, PHP/Apache, TLS
├── tasks.ini     # Laravel Schedule каждую минуту
├── env.ini       # переменные окружения проекта
└── bin/
    └── brain.cmd # brain sync/stats/search внутри среды проекта
```

### `project.ini`

Ключевые параметры:

```ini
[neiro-brain.local]
bind_ip        = 192.168.0.107 127.0.0.1
http_engine    = Apache
php_engine     = PHP-8.4
project_root   = {base_dir}
web_root       = {base_dir}\public
server_aliases = www.{host} 127.0.0.1 192.168.0.107 95.79.97.24
tls_enabled    = on
```

- `bind_ip` — адреса, на которых Apache слушает порт 80/443. Первый адрес (`192.168.0.107`)
  прописывается в HOSTS.
- `server_aliases` — по какому `Host` открывать проект. Сюда добавлены LAN и внешний IP,
  поэтому `http://192.168.0.107/` и запросы с внешнего IP попадают в проект, а не на
  страницу «Как вы здесь оказались?».
- `web_root` указывает на `public`, а не на корень Laravel — это обязательно.

### `tasks.ini`

```ini
[neiro-brain.local]
Laravel Schedule = * * * * * * * cmd.exe /c "osp project neiro-brain.local & php artisan schedule:run >> {base_dir}\storage\logs\scheduler.log 2>&1"
```

OpenServer сам вызывает Laravel-планировщик каждую минуту; отдельный Task Scheduler
для `brain:sync` не нужен. `brain:sync` выполняется по расписанию каждые 15 минут.

Проверка:

```powershell
php artisan schedule:list
Get-Content storage\logs\scheduler.log -Tail 20
```

## Важно: pgsql для PHP-8.4

OpenServer генерирует `modules\PHP-8.4\php.ini` из шаблона
`config\PHP-8.4\default\templates\php.ini`. Если включить `pdo_pgsql` только в
сгенерированном файле, после перезапуска OpenServer настройка пропадёт.

В шаблоне включены строки:

```ini
extension = pdo_pgsql
extension = pgsql
```

Резервная копия шаблона: `php.ini.neiro-brain-backup`.

Если OpenServer переустановят или откатят шаблон, повторите правку и перезапустите
модуль PHP-8.4 (меню OpenServer → PHP-8.4 → Перезапустить). Симптом без расширения:
`could not find driver` в `storage/logs/laravel.log`.

## Как применить изменения

1. Правьте `.osp\project.ini` / `tasks.ini`.
2. Перезапустите OpenServer (меню в трее → **Перезапустить**) или закройте и снова
   запустите `bin\ospanel.exe`.
3. Проверьте, что домен появился:

```powershell
Select-String C:\OSPanel65\modules\Apache\conf\httpd.conf -Pattern 'neiro-brain'
Get-Content C:\Windows\System32\drivers\etc\hosts | Select-String neiro
```

## Доступ из локальной сети

1. Убедитесь, что компьютер имеет постоянный LAN-IP `192.168.0.107`
   (DHCP reservation на роутере или статический IP в Windows).
2. Разрешите входящие TCP 80/443 в файрволе. Сейчас профили Windows Firewall
   отключены; если включите — запустите от администратора:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\open-firewall.ps1
```

3. С другого устройства: `http://192.168.0.107/`.

## Доступ из интернета по статическому IP

Схема: роутер получает статический IP `95.79.97.24`, пробрасывает 80/443 на
`192.168.0.107`, Apache отвечает по алиасу `95.79.97.24`.

### 1. Проброс портов на роутере

В настройках роутера (обычно `192.168.0.1`) → Port Forwarding / NAT:

| Внешний порт | Внутренний адрес | Внутренний порт |
|---|---|---|
| 80 | 192.168.0.107 | 80 |
| 443 | 192.168.0.107 | 443 |

Если провайдер блокирует 80, используйте внешний порт 8080 → `192.168.0.107:80`;
тогда сайт будет доступен как `http://95.79.97.24:8080/`. Алиас `95.79.97.24`
уже добавлен, порт в Host не входит, поэтому ничего менять не нужно.

### 2. Файрвол Windows

Сейчас все профили Windows Firewall выключены, дополнительных правил не требуется.
Если включите — выполните `scripts\open-firewall.ps1` от администратора.

### 3. Проверка снаружи

С другого интернета/мобильного (не из этой LAN):

```
http://95.79.97.24/
http://95.79.97.24/admin
```

Локально `http://95.79.97.24` может не открываться из-за отсутствия NAT hairpin —
это нормально, проверяйте с внешней сети.

### 4. Если внешний IP изменится

Публичный IP может быть динамическим. При смене:

1. Узнайте новый IP: `scripts\network-info.ps1`.
2. Замените `95.79.97.24` в `server_aliases` файла `.osp\project.ini`.
3. Перезапустите OpenServer.

Либо настройте DDNS и добавьте DDNS-имя в алиасы вместо IP.

## Безопасность при публикации

Наружу открываются админка, прокси и MCP. Перед публикацией:

- смените пароль администратора MoonShine;
- смените `proxy_access_token`, MCP- и API-токены в админке (Настройки → Безопасность);
- включите HTTPS и по возможности ограничьте доступ по IP в роутере/файрволе;
- не оставляйте `APP_DEBUG=true` в публичном окружении;
- помните, что локальный TLS-сертификат вызовет предупреждение у внешних клиентов;
  для нормального HTTPS используйте домен + Let's Encrypt/Caddy или reverse-proxy.

Альтернатива пробросу портов — Cloudflare Tunnel/ngrok: не требует статического IP
и не открывает порты роутера, но внешний URL будет другим.

## Диагностика

| Симптом | Причина / решение |
|---|---|
| 503 от Apache | не запущен PHP-8.4; меню OpenServer → PHP-8.4 → Включить |
| `could not find driver` | в шаблоне PHP-8.4 не включён `pdo_pgsql`, см. выше |
| «Как вы здесь оказались?» | запрошен IP/домен, которого нет в `server_aliases` |
| Домен не резолвится | проверьте HOSTS, перезапустите OpenServer |
| `http://95.79.97.24` не открывается | нет проброса портов или IP сменился |
| Cron не срабатывает | `storage\logs\scheduler.log`, `php artisan schedule:list`, статус задачи в OpenServer |
| 500 после смены домена | `APP_URL`, `storage/logs/laravel.log`, `php artisan optimize:clear` |
