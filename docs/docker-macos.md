# Neiro Brain в Docker + DeepSeek Harness на macOS

Пошаговая инструкция: поднять Neiro Brain на macOS в Docker, подключить к нему
DeepSeek Harness, провести модель через прозрачный прокси и отдать агенту
MCP-сервер знаний.

Инструкция написана под репозиторий `skolosov/neiro-brain` (Laravel 13, PHP 8.4,
PostgreSQL, MoonShine 4, Laravel MCP) и DSH 0.2.x. Docker-обвязка в репозитории
не лежит — она создаётся в шаге 2.

## Коротко

1. `git clone` репозитория в `~/www/neiro-brain`.
2. Создать `Dockerfile`, `docker-compose.yml`, `docker/*` и `.dockerignore` (раздел 4).
3. В `.env`: `APP_KEY`, `DB_*`, `APP_URL=http://localhost:8080`, `LOG_CHANNEL=stderr`.
4. `docker compose up -d --build`, затем `migrate`, `moonshine:user`, `brain:stats`.
5. Поставить Node ≥ 24.9 и `@deepseek-ai/dsh`, запустить `dsh web`.
6. В `~/.dsh/profiles/web/cordis.patch.yml` добавить провайдера и MCP-сервер.
7. В админке проверить токены и указать ключ для суммаризации.

Дальше — подробно, с проверками после каждого шага.

---

## 1. Что получится

```
DSH на macOS (~/.dsh)                         Docker Desktop
┌───────────────────────────────┐      ┌──────────────────────────────────────────┐
│ модель «Neiro Brain»          │      │ web (nginx :8080)                        │
│   baseURL 127.0.0.1:8080/v1   ├─────►│   └─► app (php-fpm):                      │
│   заголовки X-Neiro-*         │      │        /v1/*        — прокси + память     │
│                               │      │        /mcp/knowledge — MCP-сервер знаний │
│ MCP «neiro-brain»             │      │        /admin       — MoonShine           │
│   127.0.0.1:8080/mcp/knowledge├─────►│        /api/v1/*    — свой API            │
└───────────────────────────────┘      │                                          │
                                       │ scheduler: brain:sync каждые 15 минут     │
                                       │ db: postgres:16 + volume pgdata           │
                                       └───────────────────┬──────────────────────┘
                                                           │ HTTPS
                                                           ▼
                                                   https://api.deepseek.com
```

Два независимых потока:

1. **Чат** идёт из DSH в Neiro Brain (`/v1/chat/completions`), прокси подмешивает
   память в system prompt, передаёт запрос в DeepSeek и записывает диалог в PostgreSQL.
2. **Знания** возвращаются агенту через MCP (`/mcp/knowledge`) и подмешиваются в
   промпт на каждом запросе. Раз в 15 минут `brain:sync` суммирует «тихие» диалоги
   в карточки знаний.

### Что в Docker, а что нативно

| Компонент | Где | Почему |
|---|---|---|
| Neiro Brain: nginx + php-fpm + PostgreSQL + планировщик | Docker | Ничего не требует от macOS, кроме порта 8080 |
| DeepSeek Harness | macOS, нативно (`dsh web`) | `dsh web` по умолчанию слушает только loopback и **отклоняет** `--host 0.0.0.0`; харнессу нужны ваши файлы, shell и ключи. Подробнее — раздел 13 |
| DeepSeek API | облако | Ключ чата живёт в DSH; для суммаризации в админке хранится отдельный ключ |

---

## 2. Требования

| Что | Минимум | Проверка |
|---|---|---|
| macOS | 13 Ventura+ (Apple Silicon или Intel) | `sw_vers` |
| Docker Desktop | 4.30+ | `docker --version`, `docker compose version` |
| Ресурсы Docker | 4 GB RAM, 2 CPU | Docker Desktop → Settings → Resources |
| Node.js | **≥ 24.9** (требование DSH 0.2.x) | `node -v` |
| DeepSeek API-ключ | — | [platform.deepseek.com](https://platform.deepseek.com) |
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

        # Прокси Neiro Brain отдаёт SSE — буферизация ломает стриминг.
        fastcgi_buffering    off;
        fastcgi_read_timeout 600s;
        fastcgi_send_timeout 600s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`fastcgi_buffering off` + заголовок `X-Accel-Buffering: no`, который прокси уже
отдаёт, обеспечивают потоковый ответ модели.

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

  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
    image: neiro-brain/app:local
    restart: unless-stopped
    command: ["php", "artisan", "schedule:work"]
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
- **Планировщик** — отдельный контейнер `scheduler` с тем же образом:
  `php artisan schedule:work` крутит `schedule:run` раз в минуту.
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

# Прокси по умолчанию ходит в DeepSeek, а не в mock
BRAIN_PROXY_UPSTREAM=deepseek
BRAIN_PROXY_TOKEN=dev-proxy-token
BRAIN_MCP_TOKEN=dev-mcp-token
BRAIN_API_TOKEN=dev-api-token

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
| `BRAIN_PROXY_UPSTREAM` | `deepseek` | fallback, если харнесс не прислал `X-Neiro-Upstream` |
| `BRAIN_*_TOKEN` | `dev-*` | стартовые токены; дальше меняются в админке |

> **Важно.** `BRAIN_*_TOKEN` и `BRAIN_PROXY_UPSTREAM` копируются в таблицу
> `settings` при первом обращении (`SettingsInitializer`). После первого запуска
> правка `.env` **не** меняет токены — менять только в админке. То же с
> `BRAIN_PROXY_UPSTREAM`: после инициализации он живёт в настройках.

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
Миграции выполняются до старта `scheduler` — чтобы `brain:sync` не попал в
несуществующие таблицы. Дальше — администратор:

```bash
docker compose exec app php artisan moonshine:user \
  --username=admin@neiro-brain.local \
  --name=Admin \
  --password='ЗаменитеЭтотПароль'

docker compose exec app php artisan brain:stats
```

Проверки:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/up
# 200

curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/admin
# 302 (редирект на /admin/login)

docker compose logs --tail=50 app web scheduler
```

Админка: <http://localhost:8080/admin> — логин `admin@neiro-brain.local`.

Если `brain:stats` падает с `could not find driver` — собран не тот образ
(в нём нет `pdo_pgsql`). Пересоберите: `docker compose build --no-cache app`.

### Демо-данные не нужны

Не запускайте `migrate --seed` на рабочей базе: сидер создаёт демо-диалоги,
вызывает реальную модель и записывает выдуманные факты. Всё, что нужно для старта:
миграции, администратор, токены.

---

## 7. Шаг 5. Админка: токены и суммаризация

Откройте **Настройки** (`/admin/page/settings-page`) и проверьте блоки.

### 7.1. Токен доступа

Один токен на всё: прокси, MCP, API. Для локальной машины можно оставить `dev-*`;
если генерируете новый — сразу копируйте в конфиг DSH (шаги 7–8) и перезапускайте
харнесс. Токен из админки перекрывает значение из `.env`.

### 7.2. Память и прокси

- «Прокси включён» — включено;
- «Подмешивать память» — включено;
- «Записывать диалоги» — включено;
- Top K и лимит блока памяти можно не трогать.

### 7.3. Суммаризация

Это **второй, независимый ключ** — он нужен только cron-задаче `brain:sync`,
прокси его не использует.

| Поле | Значение |
|---|---|
| Провайдер | DeepSeek |
| Модель | `deepseek-flash` |
| API-ключ | ключ DeepSeek (сохраняется зашифрованным `APP_KEY`) |
| Base URL | пусто (по умолчанию `https://api.deepseek.com`) |
| Temperature | `0.2` |
| Max tokens | `4000` |

Без ключа синхронизация работает в режиме эвристики: карточки почти не появляются.
Ключ из DSH сюда не подставляется автоматически — это разные процессы.

### 7.4. Синхронизация

- «Тишина перед суммаризацией» — 10–15 минут;
- «Хранить переписку, часов» — `24` (переписка нужна только для суммаризации);
- «Cron включён» — включено.

Планировщик уже работает в контейнере `scheduler`; проверка:

```bash
docker compose exec app php artisan schedule:list
# */15 * * * *  php artisan brain:sync ... (каждые 15 минут)
```

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

## 9. Шаг 7. Подключить модель

Модель подключается в DSH, а не в админке Neiro Brain: харнесс отправляет свой
ключ DeepSeek в `Authorization`, а Neiro Brain узнаёт себя по
`X-Neiro-Proxy-Token` и выбирает апстрим по `X-Neiro-Upstream`.

Создайте/дополните `~/.dsh/profiles/web/cordis.patch.yml`:

```yaml
# 1. Провайдер модели: всё идёт через прокси Neiro Brain.
- id: llm-pi-ai
  config:
    providers:
      neiro-brain:
        displayName: Neiro Brain (DeepSeek)
        api: openai-completions
        apiKeyEnv: DEEPSEEK_API_KEY
        baseURL: http://127.0.0.1:8080/v1
        headers:
          X-Neiro-Proxy-Token: dev-proxy-token
          X-Neiro-Upstream: deepseek
        models:
          - id: deepseek-flash
            name: DeepSeek Flash via Neiro Brain
            reasoningEfforts:
              off:
              low: low
              medium: high
              high: max
          - id: deepseek-v4-pro
            name: DeepSeek V4 Pro via Neiro Brain
            reasoningEfforts:
              off:
              low: low
              medium: high
              high: max
        compat:
          supportsDeveloperRole: false
          maxTokensField: max_tokens
          supportsReasoningEffort: true
          thinkingFormat: deepseek

# 2. Новые сессии по умолчанию — через Neiro Brain.
- id: agent-default-model
  config:
    provider: neiro-brain
    model: deepseek-flash
```

Пояснения:

- **Патч заменяет всю строку `config`, а не сливает ключи.** `llm-pi-ai` в базовом
  профиле смонтирован без провайдеров, поэтому замена безопасна. Если вы уже
  добавляли туда свои маршруты — перенесите их в этот же блок.
- `baseURL` обязательно с `/v1`: без него прокси ответит
  `404 chat/completions could not be found`.
- `compat` нужен, потому что прокси — не OpenAI: иначе pi-ai может отправить роль
  `developer` и `max_completion_tokens`, которые DeepSeek не понимает.
- `reasoningEfforts` — это отображение уровней DSH на то, что понимает DeepSeek:
  `low → low`, `medium → high`, `high → max`. Уровень выбирается в UI.
- `agent-default-model` избавляет от ручного выбора модели в каждой новой сессии.

Проверьте, что патч применился:

```bash
dsh --profile web --dump-config | grep -A 8 'llm-pi-ai'
```

Патч рассчитан на DSH 0.2.x: строки `llm-pi-ai` и `agent-default-model` есть в
базовом профиле. Если в вашей версии id строк другие — сверьтесь с выводом
`--dump-config`. Если блок `agent-default-model` вызывает ошибку схемы, удалите
его и выберите модель в UI вручную.

Перезапустите харнесс (`Ctrl+C`, затем снова `dsh web --no-open`) и в UI:
**Settings → Models → Neiro Brain (DeepSeek) → deepseek-flash**, уровень
размышления — там же.

Проверка прокси в обход DSH:

```bash
export DEEPSEEK_API_KEY='sk-...'

curl -i -sS http://127.0.0.1:8080/v1/chat/completions \
  -H "Authorization: Bearer $DEEPSEEK_API_KEY" \
  -H "X-Neiro-Proxy-Token: dev-proxy-token" \
  -H "X-Neiro-Upstream: deepseek" \
  -H "Content-Type: application/json" \
  -d '{"model":"deepseek-flash","messages":[{"role":"user","content":"Ответь одним словом: работает?"}]}'
```

Ожидаемо: JSON с `choices[].message.content` и заголовок `X-Neiro-Memory-Hits`
в ответе. Без ключа провайдера можно проверить сам прокси: `X-Neiro-Upstream: mock` —
ответит без сети и покажет найденную память в теле.

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
          Authorization: Bearer dev-mcp-token
```

Пакет `@deepseek-ai/dsh-mcp-client` уже входит в поставку CLI — отдельно ставить
ничего не нужно.

После перезапуска `dsh web` в новой сессии появятся инструменты:

- `mcp__neiro-brain__get_project_context`
- `mcp__neiro-brain__search_knowledge`
- `mcp__neiro-brain__get_knowledge`
- `mcp__neiro-brain__remember_knowledge`
- `mcp__neiro-brain__list_projects`
- `mcp__neiro-brain__recent_knowledge`
- `mcp__neiro-brain__delete_knowledge`

Проверка MCP без DSH:

```bash
curl -sS -X POST http://127.0.0.1:8080/mcp/knowledge \
  -H "Authorization: Bearer dev-mcp-token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{
        "name":"search_knowledge",
        "arguments":{"query":"какой у меня стек","limit":3}
      }}'
```

Если ответ `401` — токен не совпал. Помните: после инициализации настроек токен
берётся из админки, а не из `.env`.

### Другие харнессы (опционально)

Тот же MCP можно отдать Claude Code, Cursor или Claude Desktop — это тот же
endpoint с тем же токеном:

```json
{
  "mcpServers": {
    "neiro-brain": {
      "url": "http://127.0.0.1:8080/mcp/knowledge",
      "headers": { "Authorization": "Bearer dev-mcp-token" }
    }
  }
}
```

```bash
claude mcp add --transport http neiro-brain http://127.0.0.1:8080/mcp/knowledge \
  --header "Authorization: Bearer dev-mcp-token"
```

OpenAI-совместимым клиентам достаточно поменять base URL на
`http://127.0.0.1:8080/v1` и добавить те же два заголовка
(`X-Neiro-Proxy-Token`, `X-Neiro-Upstream`).

---

## 11. Шаг 9. Сквозная проверка

1. **Диалог записан.** В DSH отправьте сообщение через модель Neiro Brain.
   В админке → **Диалоги** появится сессия с сообщениями и статусом.

2. **Память подмешивается.** В ответе на запрос есть заголовок
   `X-Neiro-Memory-Hits` (виден в логах/через curl). Пока база пуста — `0`.

3. **Знания извлекаются.**

   ```bash
   docker compose exec app php artisan brain:sync --force
   docker compose exec app php artisan brain:stats
   docker compose exec app php artisan brain:search "стек"
   ```

   `brain:stats` покажет диалоги, карточки, чанки и термины индекса. Если карточек
   `0` — смотрите «Диалоги» в админке (статус и ошибка) и
   `docker compose logs app`.

4. **MCP отвечает.** В новой сессии DSH:

   > Найди в базе знаний, какой у меня стек и что мы решили по проекту.

   Агент вызовет `mcp__neiro-brain__search_knowledge` и покажет карточки.

5. **Запись через MCP.** Попросите:

   > Запомни: предпочитаю модульный монолит и Pest для тестов.

   Агент вызовет `remember_knowledge`; карточка появится в админке → **Знания**.

Цикл замкнут: чат → прокси → PostgreSQL → `brain:sync` → карточки → MCP → промпт.

---

## 12. Шаг 10. Эксплуатация

### Логи и состояние

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f web
docker compose logs -f scheduler
docker compose logs -f db

docker compose exec app php artisan about
docker compose exec app php artisan schedule:list
```

Логи Laravel идут в stderr контейнера (`LOG_CHANNEL=stderr`), nginx — в свой stdout.

### Перезапуск после правок

```bash
docker compose restart app scheduler   # после .env, конфигов, кода
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
нужно **и то, и другое**: без `APP_KEY` зашифрованные токены и ключ суммаризации
не восстановить.

```bash
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' \
  > "neiro-brain-$(date +%F).dump"

cp .env "neiro-brain-env-$(date +%F).backup"
```

Восстановление (остановите `app` и `scheduler`, чтобы не держали соединения):

```bash
docker compose stop app scheduler
cat neiro-brain-2026-11-09.dump | \
  docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists'
docker compose start app scheduler
```

### Смена токенов

1. Админка → **Настройки → Токен доступа** → «Сгенерировать новый» → «Скопировать».
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
| Сервер без GUI | `dsh --profile headless` + прокси на `http://host.docker.internal:8080/v1` |

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

В `headless-neiro.patch.yml` — те же блоки, что в шаге 7 (`llm-pi-ai` и
`agent-default-model`), но с `baseURL: http://host.docker.internal:8080/v1`; MCP-сервер
туда же (`http://host.docker.internal:8080/mcp/knowledge`). Учтите: в контейнере DSH
видит только смонтированные каталоги, а не весь ваш Mac.

---

## 14. Диагностика

| Симптом | Причина и решение |
|---|---|
| `Bind for 0.0.0.0:8080 failed: port is already allocated` | Порт занят. Поменяйте `APP_PORT` и `APP_URL` в `.env`, затем `docker compose up -d` |
| `502 Bad Gateway` от nginx | `app` не запущен или упал: `docker compose logs app` |
| `SQLSTATE[08006]` / `password authentication failed` | Пароль в `.env` не совпадает с тем, с которым создан том `pgdata`. Либо верните старый пароль, либо `docker compose down -v` (удалит базу) |
| `No application encryption key has been specified` | Пустой `APP_KEY`: сгенерируйте и перезапустите |
| `419 Page Expired` на логине | Сменился `APP_KEY` или остались старые cookies. Очистите cookies для `localhost:8080`, `docker compose exec app php artisan optimize:clear` |
| `401 invalid_token` от прокси | Не совпал `X-Neiro-Proxy-Token`. Токен берётся из админки; после первого старта `.env` его уже не меняет |
| `503` от прокси | DSH не прислал `Authorization`/`X-Neiro-Upstream` или upstream недоступен |
| `404 chat/completions could not be found` | В `baseURL` забыли `/v1` |
| `400 model not found` | Id модели не совпал с тем, что отдаёт DeepSeek. Проверьте `models[].id` в патче или выберите другую модель в UI |
| DSH: `MISSING_CREDENTIAL` | Нет `DEEPSEEK_API_KEY` в окружении и `~/.dsh/.env`; добавьте и перезапустите |
| В Models нет провайдера Neiro Brain | Патч не применён или правится не тот профиль (`web` vs `desktop`). Проверьте `dsh --profile web --dump-config` |
| MCP-инструментов нет | Неверный `url`/токен, либо DSH не перезапущен. Проверьте `curl` из шага 10 |
| `dsh: command not found` | `npm config get prefix`/`PATH`; переустановите `npm i -g @deepseek-ai/dsh` |
| Порт 3080 занят | `dsh web --port 3081 --no-open`, открыть новый адрес |
| `npm error engine Unsupported engine` | Node старее 24.9: `brew upgrade node` |
| `permission denied` в `storage/` | `docker compose restart app` (entrypoint переставит владельца) или `docker compose exec app chown -R www-data:www-data storage bootstrap/cache` |
| `Permission denied` при чтении `.env` | Файл закрыт правами: `chmod 644 .env` — его читает `www-data` внутри контейнера |
| Медленные ответы или таймаут 120 c | Понизьте reasoning в DSH (`low`): модель думает перед каждым tool-call |
| Карточек нет после `brain:sync` | Нет ключа в «Суммаризация» (работает эвристика), диалог ещё не «тихий» (используйте `--force`), либо фильтр качества отбросил факты — смотрите «Диалоги» |

---

## 15. Чек-лист

- [ ] Docker Desktop запущен, ресурсов ≥ 4 GB
- [ ] Репозиторий склонирован в `~/www/neiro-brain`
- [ ] Созданы `Dockerfile`, `docker-compose.yml`, `docker/` (3 файла), `.dockerignore`
- [ ] В `.env` заданы `APP_KEY`, `DB_*`, `APP_URL`, `APP_PORT`, `LOG_CHANNEL=stderr`
- [ ] `docker compose up -d` — все четыре сервиса `running`
- [ ] `php artisan migrate --force` выполнен
- [ ] Администратор создан, вход в `/admin` работает
- [ ] `brain:stats` отвечает без ошибок
- [ ] В админке включены прокси, память, захват диалогов
- [ ] В «Суммаризация» указан ключ DeepSeek и модель `deepseek-flash`
- [ ] Node ≥ 24.9, `dsh --version` работает
- [ ] `~/.dsh/profiles/web/cordis.patch.yml` содержит провайдера, `agent-default-model`, MCP
- [ ] В DSH выбрана модель «Neiro Brain (DeepSeek)»
- [ ] MCP-инструменты `mcp__neiro-brain__*` видны в сессии
- [ ] `brain:sync --force` создаёт карточки, `brain:search` их находит

---

## Ссылки

- Документация репозитория: [`docs/quickstart.md`](quickstart.md),
  [`docs/deepseek-harness.md`](deepseek-harness.md), [`docs/mcp.md`](mcp.md),
  [`docs/proxy.md`](proxy.md), [`docs/operations.md`](operations.md)
- DSH CLI и профили: <https://deepseek-harness.github.io/>
- Пакет CLI: <https://www.npmjs.com/package/@deepseek-ai/dsh>
- MCP-клиент DSH: <https://www.npmjs.com/package/@deepseek-ai/dsh-mcp-client>
