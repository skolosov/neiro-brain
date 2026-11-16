# Neiro Brain в Docker + DeepSeek Harness на macOS

Пошаговая инструкция: поднять Neiro Brain на macOS в Docker, подключить к нему
DeepSeek Harness как MCP-клиент и отдать агенту базу знаний. Модель DSH
настраивает сам: через Neiro Brain она больше не идёт.

Инструкция написана под репозиторий `skolosov/neiro-brain` (Laravel 13, PHP 8.4,
PostgreSQL, MoonShine 4, Laravel MCP) и DSH 0.2.x. Docker-обвязка в репозитории
не лежит — она создаётся в шаге 2.

## Коротко

1. `git clone` репозитория в `~/www/neiro-brain`.
2. Создать `Dockerfile`, `docker-compose.yml`, `docker/*` и `.dockerignore` (раздел 4).
3. В `.env`: `APP_KEY`, `DB_*`, `APP_URL=http://localhost:8080`, `LOG_CHANNEL=stderr`.
4. `docker compose up -d --build`, затем `migrate`, `brain:user`.
5. Поставить Node ≥ 24.9 и `@deepseek-ai/dsh`, запустить `dsh web`.
6. В `~/.dsh/profiles/web/cordis.patch.yml` добавить MCP-сервер `neiro-brain`.
7. Выпустить личный токен в кабинете и вставить его в конфиг MCP.

Дальше — подробно, с проверками после каждого шага.

---

## 1. Что получится

```
DSH на macOS (~/.dsh)                         Docker Desktop
┌───────────────────────────────┐      ┌──────────────────────────────────────────┐
│ модель: провайдер DSH ────────┼─────►│ web (nginx :8080)                        │
│   (напрямую, без Neiro Brain) │      │   └─► app (php-fpm):                     │
│                               │      │        /mcp/knowledge — MCP-сервер знаний │
│ MCP «neiro-brain»             │      │        /app         — кабинет             │
│   127.0.0.1:8080/mcp/knowledge├─────►│        /admin       — MoonShine           │
│                               │      │        /api/v1/*    — свой HTTP API       │
└───────────────────────────────┘      │                                          │
                                       │ db: postgres:16 + volume pgdata           │
                                       └──────────────────────────────────────────┘
```

Один поток: агент ищет и записывает знания через MCP, человек читает их в
кабинете. Знания пишутся только по запросу агента — расписания и фоновой
суммаризации нет, ключей провайдеров в Neiro Brain нет.

### Что в Docker, а что нативно

| Компонент | Где | Почему |
|---|---|---|
| Neiro Brain: nginx + php-fpm + PostgreSQL | Docker | Ничего не требует от macOS, кроме порта 8080 |
| DeepSeek Harness | macOS, нативно (`dsh web`) | `dsh web` по умолчанию слушает только loopback и **отклоняет** `--host 0.0.0.0`; харнессу нужны ваши файлы, shell и ключи. Подробнее — раздел 13 |
| Модель | DSH | Ключ провайдера живёт в DSH; в Neiro Brain его нет |

---

## 2. Требования

| Что | Минимум | Проверка |
|---|---|---|
| macOS | 13 Ventura+ (Apple Silicon или Intel) | `sw_vers` |
| Docker Desktop | 4.30+ | `docker --version`, `docker compose version` |
| Ресурсы Docker | 4 GB RAM, 2 CPU | Docker Desktop → Settings → Resources |
| Node.js | **≥ 24.9** (требование DSH 0.2.x) | `node -v` |
| Ключ модели | — | нужен только DSH для выбранного провайдера; Neiro Brain его не хранит |
| Доступ к репозиторию | репозиторий приватный | `gh auth status` или SSH-ключ |
| Свободные порты | 8080 (Neiro Brain), 3080 (DSH) | `lsof -nP -iTCP:8080 -sTCP:LISTEN` |

Docker Desktop под Apple Silicon собирает образы под `arm64` — официальные образы
`php`, `nginx`, `postgres` это поддерживают, эмуляция не нужна.

---

## 3. Шаг 1. Клонировать репозиторий

Рекомендуемое место — `~/www`: Docker Desktop по умолчанию имеет доступ к домашнему
каталогу. Если хотите держать проект рядом с остальными в `~/Desktop/www`, разрешите
Docker доступ к «Рабочему столу»: **System Settings → Privacy & Security → Files and
Folders → Docker**.

```bash
mkdir -p ~/www
cd ~/www

# если репозиторий приватный — сначала авторизуйтесь
gh auth login

git clone https://github.com/skolosov/neiro-brain.git
cd neiro-brain
git log -1 --oneline
```

Если `gh` не установлен: `brew install gh`. Альтернатива — SSH:
`git clone git@github.com:skolosov/neiro-brain.git`.

Клонируется ветка по умолчанию. Если нужна конкретная:

```bash
git branch -a
git switch main    # или master
```

Если у вас уже есть общий `~/Desktop/www/docker` с другими проектами, этот
`docker-compose.yml` можно держать отдельно: у него своё имя проекта
(`neiro-brain`) и свои тома, он не трогает соседние контейнеры. Порт 8080 при
конфликте меняется через `APP_PORT`.

---

## 4. Шаг 2. Добавить Docker-обвязку

В корне репозитория появятся шесть файлов:

| Файл | Зачем |
|---|---|
| `Dockerfile` | PHP 8.4 + pdo_pgsql + Composer-зависимости |
| `docker-compose.yml` | app, web, db, scheduler |
| `docker/nginx.conf` | nginx → php-fpm, SSE без буферизации |
| `docker/php.ini` | лимиты, opcache, логи в stderr |
| `docker/entrypoint.sh` | права на storage, `.env`, `storage:link` |
| `.dockerignore` | чтобы в образ не попали `.env`, `vendor`, `.git` |

```bash
mkdir -p docker
```

### 4.1. `Dockerfile`

```dockerfile
# syntax=docker/dockerfile:1

# 1. Зависимости Composer собираются отдельным слоем: переживают правки кода.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-scripts

# 2. Runtime: PHP-FPM 8.4 + pdo_pgsql + mbstring + opcache.
FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libonig-dev \
    && docker-php-ext-install -j"$(nproc)" \
        mbstring \
        pdo_pgsql \
        pgsql \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-neiro-brain.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
```

Почему так:

- `composer install` выполняется **до** копирования кода и без скриптов — иначе
  `artisan package:discover` упадёт, пока приложения ещё нет в образе.
- В runtime-образе есть `composer` — пригодится для `docker compose exec app composer …`.
- Каталоги `storage` и `bootstrap/cache` создаются и отдаются `www-data`; в compose
  они закрыты именованными томами, поэтому права не зависят от macOS.

### 4.2. `docker/php.ini`

```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 600
max_input_time = 600
display_errors = Off
log_errors = On
error_log = /proc/self/fd/2
date.timezone = UTC

opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0
```

`validate_timestamps=1` + `revalidate_freq=0` важны для режима разработки: правки
PHP-файлов на хосте подхватываются сразу. В проде можно поставить `0` и `0`.

### 4.3. `docker/entrypoint.sh`

```sh
#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Тома создаются от root: отдаём их PHP-FPM.
chown -R www-data:www-data storage bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
    echo "entrypoint: создан .env из .env.example — заполните APP_KEY и DB_*" >&2
fi

php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
```

Сделайте файл исполняемым (на всякий случай — `chmod +x` есть и в Dockerfile):

```bash
chmod +x docker/entrypoint.sh
```

### 4.4. `docker/nginx.conf`

```nginx
server {
    listen 80;
    server_name _;

    root /var/www/html/public;
    index index.php;

    charset utf-8;
    client_max_body_size 64m;

    access_log /var/log/nginx/access.log;
    error_log  /var/log/nginx/error.log warn;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $realpath_root;
        fastcgi_param HTTP_PROXY      "";

        fastcgi_read_timeout 600s;
        fastcgi_send_timeout 600s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`fastcgi_read_timeout` с запасом: поиск и запись пачкой карточек могут быть
небыстрыми, но стриминга здесь больше нет.

### 4.5. `docker-compose.yml`

```yaml
name: neiro-brain

x-app-volumes: &app-volumes
  - .:/var/www/html                 # код с macOS, правки видны сразу
  - vendor:/var/www/html/vendor     # зависимости — в томе, не на хосте
  - storage:/var/www/html/storage   # логи, кэш, шаблоны
  - bootstrap-cache:/var/www/html/bootstrap/cache

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: neiro-brain/app:local
    restart: unless-stopped
    volumes: *app-volumes
    depends_on:
      db:
        condition: service_healthy

  web:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "${APP_PORT:-8080}:80"
    volumes:
      - .:/var/www/html:ro
      - storage:/var/www/html/storage:ro
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      app:
        condition: service_started
    healthcheck:
      test: ["CMD-SHELL", "wget -qO- http://127.0.0.1/up >/dev/null || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3

  db:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-neiro_brain}
      POSTGRES_USER: ${DB_USERNAME:-neiro}
      POSTGRES_PASSWORD: ${DB_PASSWORD:?DB_PASSWORD must be set in .env}
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 20

volumes:
  pgdata:
  vendor:
  storage:
  bootstrap-cache:
```

Ключевые решения:

- **Код монтируется с хоста**, `vendor`/`storage`/`bootstrap-cache` — именованные
  тома. Docker сам наполняет пустой том содержимым образа, поэтому `vendor`
  появляется из сборки, а не с macOS.
- **Права**: `storage` и `bootstrap/cache` принадлежат `www-data` внутри тома,
  поэтому FPM и CLI не спорят с macOS за владельца файлов.
- **Планировщика нет**: знания пишутся по запросу агента; отдельный контейнер
  `scheduler` не нужен.
- **`${DB_PASSWORD:?…}`** — compose упадёт с понятной ошибкой, если пароль не задан.

### 4.6. `.dockerignore`

```
.git
.gitignore
.idea
.vscode
.env
.env.*
!.env.example
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*
public/storage
.phpunit.result.cache
.phpunit.cache
```

---

## 5. Шаг 3. Настроить `.env`

```bash
cp .env.example .env
```

Сгенерируйте ключ приложения (он шифрует токены и ключи в таблице `settings`,
потеряете `APP_KEY` — потеряете секреты):

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
```

Вставьте строку в `.env` и приведите к виду:

```dotenv
APP_NAME="Neiro Brain"
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:ВАШ_СГЕНЕРИРОВАННЫЙ_КЛЮЧ
APP_URL=http://localhost:8080
APP_PORT=8080

# Логи в stdout/stderr контейнера: смотреть docker compose logs
LOG_CHANNEL=stderr
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=neiro_brain
DB_USERNAME=neiro
DB_PASSWORD=neiro-local-password

MOONSHINE_TITLE="Neiro Brain"
```

Требования к паролю БД: только `a-z A-Z 0-9 - _` — `$`, `#`, пробелы и кавычки
ломают и `.env`, и подстановку в compose.

Таблица переменных:

| Переменная | Значение | Комментарий |
|---|---|---|
| `DB_HOST` | `db` | имя сервиса в compose |
| `DB_PASSWORD` | придумайте | тот же пароль получит контейнер `db` при первом старте |
| `APP_URL` | `http://localhost:8080` | по нему открывается админка |
| `APP_PORT` | `8080` | порт на macOS; меняйте, если занят |
| `LOG_CHANNEL` | `stderr` | логи в `docker compose logs`, без файлов |

> **Важно.** Личные токены выпускаются в кабинете (`/app/tokens`), а не задаются
> в `.env`. `BRAIN_DEV_TOKEN` — только обход для `localhost` при `APP_ENV=local`;
> на публичном сервере он бесполезен.

---

## 6. Шаг 4. Собрать и запустить

```bash
docker compose build
docker compose up -d --wait db
docker compose run --rm app php artisan migrate --force
docker compose up -d
docker compose ps
```

Первый запуск занимает 2–5 минут: собирается PHP-образ и инициализируется PostgreSQL.
Дальше — человек и его токен:

```bash
docker compose exec app php artisan brain:user \
  --email=owner@example.com \
  --name="Владелец" \
  --password='ЗаменитеЭтотПароль'

docker compose exec app php artisan brain:stats --user=owner@example.com
```

`moonshine:user` нужен только для доступа в админку MoonShine; его можно завести
отдельно или через `brain:user --admin`.

Проверки:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/up
# 200

curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/admin
# 302 (редирект на /admin/login)

docker compose logs --tail=50 app web
```

Админка: <http://localhost:8080/admin> — логин `admin@neiro-brain.local`.

Если `brain:stats` падает с `could not find driver` — собран не тот образ
(в нём нет `pdo_pgsql`). Пересоберите: `docker compose build --no-cache app`.

### Демо-данные не нужны

Не запускайте `migrate --seed` на рабочей базе: сидер создаёт демо-диалоги,
вызывает реальную модель и записывает выдуманные факты. Всё, что нужно для старта:
миграции, администратор, токены.

---

## 7. Шаг 5. Кабинет и личный токен

1. Откройте `http://localhost:8080/app` и войдите email + паролем человека,
   созданного `brain:user`.
2. **Токены → Выпустить**: имя, способности `read` или `read + write`.
3. Скопируйте `nb-…` — он показывается один раз; в базе лежит SHA-256.

`read` — поиск и чтение API. `write` нужен MCP (`remember_many`,
`delete_knowledge`) и `POST /api/v1/entries`; `write` подразумевает `read`.
Отзыв — там же, действует сразу. Токен передаётся только заголовком
`Authorization: Bearer nb-…`; дев-токена и токена в адресе нет.

Админка `/admin` (MoonShine) нужна для людей, токенов, всех карточек и правил
записи. Провайдера и ключа модели в ней нет и не должно быть.

---

## 8. Шаг 6. DeepSeek Harness на macOS

DSH ставится нативно, в Docker он не нужен (раздел 13).

### 8.1. Node.js и CLI

```bash
brew install node pnpm
node -v        # нужен v24.9.0 или новее
npm install -g @deepseek-ai/dsh
dsh --version
```

`pnpm` нужен CLI для управления профилями и плагинами. Если `node -v` старее —
`brew upgrade node`. Если `dsh` не находится после установки — проверьте
`npm config get prefix` и что его `bin` есть в `PATH`.

### 8.2. Первый запуск

```bash
mkdir -p ~/www
cd ~/www
dsh web --no-open
```

В терминале появится адрес вида `http://127.0.0.1:3080` — откройте его в браузере.
При первом запуске создаётся окружение:

```text
~/.dsh/
├── settings.yaml              # модель и reasoning по умолчанию
├── profiles/
│   └── web/
│       ├── package.json       # плагины профиля
│       └── cordis.patch.yml   # ваш слой настроек
├── sessions/
└── storages/
```

Ключ DeepSeek положите в `~/.dsh/.env` (DSH читает его при старте):

```bash
echo 'DEEPSEEK_API_KEY=sk-ВАШ_КЛЮЧ' >> ~/.dsh/.env
chmod 600 ~/.dsh/.env
```

Альтернатива — ввести ключ в UI: **Settings → Models**. Тогда он ляжет в
`~/.dsh/.credentials.yaml`.

### 8.3. Какой профиль редактировать

| Как запущен DSH | Файл настроек |
|---|---|
| `dsh web` (CLI, рекомендуется) | `~/.dsh/profiles/web/cordis.patch.yml` |
| DeepSeek Harness Desktop.app | `~/.dsh/profiles/desktop/cordis.patch.yml` |

Дальше используется `web`.

---

## 9. Шаг 7. Модель в DSH

Модель к Neiro Brain отношения не имеет: выберите провайдера и модель в DSH
обычным способом (**Settings → Models**) или своим блоком `llm-pi-ai` в
`cordis.patch.yml`. Ключ провайдера остаётся в DSH (`~/.dsh/.env` или
credentials); в Neiro Brain его нет, и запросы к моделям через него не идут.

Если раньше в профиле был провайдер `neiro-brain` с `baseURL …/v1` — удалите его:
такого endpoint больше нет. Подробно о DSH и MCP: [deepseek-harness.md](deepseek-harness.md).

---

## 10. Шаг 8. Подключить MCP

Тот же файл, отдельный блок `insert`:

```yaml
- insert:
    - id: mcp-neiro-brain
      name: '@deepseek-ai/dsh-mcp-client'
      config:
        serverName: neiro-brain
        transport: streamable-http
        url: http://127.0.0.1:8080/mcp/knowledge
        headers:
          Authorization: Bearer nb-…
```

Пакет `@deepseek-ai/dsh-mcp-client` уже входит в поставку CLI — отдельно ставить
ничего не нужно.

После перезапуска `dsh web` в новой сессии появятся инструменты:

- `mcp__neiro-brain__get_project_context`
- `mcp__neiro-brain__search_knowledge`
- `mcp__neiro-brain__get_knowledge`
- `mcp__neiro-brain__remember_many`
- `mcp__neiro-brain__list_projects`
- `mcp__neiro-brain__recent_knowledge`
- `mcp__neiro-brain__delete_knowledge`

Проверка MCP без DSH:

```bash
curl -sS -X POST http://127.0.0.1:8080/mcp/knowledge \
  -H "Authorization: Bearer nb-…" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{
        "name":"search_knowledge",
        "arguments":{"query":"какой у меня стек","limit":3}
      }}'
```

Если ответ `401` — токен неверный, отозван или истёк: выпустите новый в
`/app/tokens`. Токен из `.env` больше не используется.

### Другие харнессы (опционально)

Тот же MCP можно отдать Claude Code, Cursor или Claude Desktop — это тот же
endpoint с тем же токеном:

```json
{
  "mcpServers": {
    "neiro-brain": {
      "url": "http://127.0.0.1:8080/mcp/knowledge",
      "headers": { "Authorization": "Bearer nb-…" }
    }
  }
}
```

```bash
claude mcp add --transport http neiro-brain http://127.0.0.1:8080/mcp/knowledge \
  --header "Authorization: Bearer nb-…"
```

Другим харнессам нужен только этот MCP-endpoint: модели через Neiro Brain не
идут, поэтому base URL менять не нужно.

---

## 11. Шаг 9. Сквозная проверка

1. **MCP отвечает.** В новой сессии DSH:

   > Найди в базе знаний, какой у меня стек и что мы решили по проекту.

   Агент вызовет `mcp__neiro-brain__search_knowledge` и покажет карточки.
   Пустая база — честное «знаний нет».

2. **Запись через MCP.** Попросите:

   > Запомни: предпочитаю модульный монолит и PHPUnit для тестов.

   Агент вызовет `remember_many`; карточка появится в кабинете (`/app/search`) и
   в админке → **Знания**.

3. **Проверка из консоли.**

   ```bash
   docker compose exec app php artisan brain:stats --user=owner@example.com
   docker compose exec app php artisan brain:search "стек" --user=owner@example.com
   ```

   `brain:stats` покажет карточки, чанки и термины индекса. Если карточек `0` —
   проверьте ответ MCP и `docker compose logs app`.

Цикл: MCP → PostgreSQL → BM25 → MCP; человек читает те же карточки в кабинете.

---

## 12. Шаг 10. Эксплуатация

### Логи и состояние

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f web
docker compose logs -f db

docker compose exec app php artisan about
```

Логи Laravel идут в stderr контейнера (`LOG_CHANNEL=stderr`), nginx — в свой stdout.

### Перезапуск после правок

```bash
docker compose restart app   # после .env, конфигов, кода
docker compose exec app php artisan optimize:clear
```

Код на хосте подхватывается сразу (bind mount + opcache с валидацией timestamp).
`composer.json`/`composer.lock` — нет:

```bash
docker compose exec app composer install --no-interaction
```

### Обновление проекта

```bash
git pull
docker compose build
docker compose up -d
docker compose exec app composer install --no-interaction
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
```

### Бэкап

Данные — в томе `neiro-brain_pgdata`, секреты — в `.env` (`APP_KEY`). Бэкапить
нужно **и то, и другое**: без `APP_KEY` зашифрованные настройки не восстановить.

```bash
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' \
  > "neiro-brain-$(date +%F).dump"

cp .env "neiro-brain-env-$(date +%F).backup"
```

Восстановление (остановите `app`, чтобы не держал соединения):

```bash
docker compose stop app
cat neiro-brain-2026-11-09.dump | \
  docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists'
docker compose start app
```

### Смена токенов

1. Кабинет → **Токены** → «Выпустить» (или «Отозвать» старый).
2. Обновить значение в `~/.dsh/profiles/web/cordis.patch.yml`.
3. Перезапустить `dsh web`.

### Остановка

```bash
docker compose down          # контейнеры и сеть, данные сохраняются
docker compose down -v       # ВНИМАНИЕ: удаляет pgdata, storage, vendor
```

---

## 13. Вариант «всё в Docker»: что возможно, а что нет

Запустить **DSH с веб-интерфейсом** в контейнере и открыть его с macOS не выйдет
без костылей: `dsh web` принимает только конкретный локальный IP и отклоняет
wildcard-адреса (`--host 0.0.0.0` → usage error), а по умолчанию слушает
`127.0.0.1:3080`. Проброс порта Docker в такой контейнер не попадает.

Практический расклад:

| Сценарий | Решение |
|---|---|
| Ежедневная работа с GUI | DSH нативно на macOS (эта инструкция) |
| Автоматизация, cron, CI | DSH в контейнере в режиме `headless` — ему не нужен HTTP-сервер |
| Сервер без GUI | `dsh --profile headless` + MCP на `http://host.docker.internal:8080/mcp/knowledge` |

Пример headless-запуска (одноразовая задача, без публикации портов):

```bash
docker run --rm \
  -e DEEPSEEK_API_KEY \
  -v "$HOME/.dsh:/root/.dsh" \
  -v "$PWD:/workspace" -w /workspace \
  node:24-bookworm \
  sh -lc 'npm i -g @deepseek-ai/dsh >/dev/null && \
          dsh --profile headless --patch /root/.dsh/headless-neiro.patch.yml "собери отчёт по git log"'
```

В `headless-neiro.patch.yml` — обычный провайдер модели DSH (ключ в окружении) и
MCP-сервер (`http://host.docker.internal:8080/mcp/knowledge`). Учтите: в контейнере
DSH видит только смонтированные каталоги, а не весь ваш Mac.

---

## 14. Диагностика

| Симптом | Причина и решение |
|---|---|
| `Bind for 0.0.0.0:8080 failed: port is already allocated` | Порт занят. Поменяйте `APP_PORT` и `APP_URL` в `.env`, затем `docker compose up -d` |
| `502 Bad Gateway` от nginx | `app` не запущен или упал: `docker compose logs app` |
| `SQLSTATE[08006]` / `password authentication failed` | Пароль в `.env` не совпадает с тем, с которым создан том `pgdata`. Либо верните старый пароль, либо `docker compose down -v` (удалит базу) |
| `No application encryption key has been specified` | Пустой `APP_KEY`: сгенерируйте и перезапустите |
| `419 Page Expired` на логине | Сменился `APP_KEY` или остались старые cookies. Очистите cookies для `localhost:8080`, `docker compose exec app php artisan optimize:clear` |
| `401 invalid_token` от MCP | Токен неверный, отозван или истёк: выпустите новый в `/app/tokens` |
| `403 insufficient_ability` | У токена только `read`, а MCP требует `write` |
| `503 no_owner` | В базе нет активного пользователя: `brain:user …` |
| `400 model not found` | Id модели не совпал с тем, что отдаёт провайдер. Проверьте `models[].id` в патче или выберите другую модель в UI |
| DSH: `MISSING_CREDENTIAL` | Нет ключа провайдера в окружении и `~/.dsh/.env`; добавьте и перезапустите |
| MCP-инструментов нет | Неверный `url`/`serverName`/токен, либо DSH не перезапущен. Проверьте `curl` из шага 10 |
| `dsh: command not found` | `npm config get prefix`/`PATH`; переустановите `npm i -g @deepseek-ai/dsh` |
| Порт 3080 занят | `dsh web --port 3081 --no-open`, открыть новый адрес |
| `npm error engine Unsupported engine` | Node старее 24.9: `brew upgrade node` |
| `permission denied` в `storage/` | `docker compose restart app` (entrypoint переставит владельца) или `docker compose exec app chown -R www-data:www-data storage bootstrap/cache` |
| `Permission denied` при чтении `.env` | Файл закрыт правами: `chmod 644 .env` — его читает `www-data` внутри контейнера |
| Медленные ответы или таймаут 120 c | Понизьте reasoning в DSH (`low`): модель думает перед каждым tool-call |
| Карточек нет после записи | MCP вернул `rejected`: смотрите причину в ответе и `docker compose logs app` |

---

## 15. Чек-лист

- [ ] Docker Desktop запущен, ресурсов ≥ 4 GB
- [ ] Репозиторий склонирован в `~/www/neiro-brain`
- [ ] Созданы `Dockerfile`, `docker-compose.yml`, `docker/` (3 файла), `.dockerignore`
- [ ] В `.env` заданы `APP_KEY`, `DB_*`, `APP_URL`, `APP_PORT`, `LOG_CHANNEL=stderr`
- [ ] `docker compose up -d` — сервисы `app`, `web`, `db` в состоянии `running`
- [ ] `php artisan migrate --force` выполнен
- [ ] Человек создан `brain:user`, вход в `/app` работает; вход в `/admin` — если нужен
- [ ] `brain:stats --user=…` отвечает без ошибок
- [ ] Личный токен выпущен в кабинете (`read + write`) и вставлен в конфиг MCP
- [ ] Node ≥ 24.9, `dsh --version` работает
- [ ] `~/.dsh/profiles/web/cordis.patch.yml` содержит MCP-сервер `neiro-brain`
- [ ] MCP-инструменты `mcp__neiro-brain__*` видны в сессии
- [ ] `remember_many` создаёт карточку, `search_knowledge` её находит

---

## Ссылки

- Документация репозитория: [`docs/quickstart.md`](quickstart.md),
  [`docs/deepseek-harness.md`](deepseek-harness.md), [`docs/mcp.md`](mcp.md),
  [`docs/operations.md`](operations.md)
- DSH CLI и профили: <https://deepseek-harness.github.io/>
- Пакет CLI: <https://www.npmjs.com/package/@deepseek-ai/dsh>
- MCP-клиент DSH: <https://www.npmjs.com/package/@deepseek-ai/dsh-mcp-client>
