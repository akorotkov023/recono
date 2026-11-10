# AGENTS.md

Symfony 7.1 + Doctrine ORM (PostgreSQL) + Webpack Encore. Cron-конвейер сверки реестров
провайдера и реестров мерчантам. Провайдеры и мерчанты задаются в БД (`registry_config` и
`email_config`).

## Запуск / проверка локально

Всё в Docker (PHP/node на хосте не нужны):

- `docker compose up -d --build` (или `make up`) — app + nginx + PostgreSQL + cron,
  http://localhost:8991
- `docker compose --profile worker up -d` — воркер Messenger (очереди `extract`, `send`)
- `docker compose exec app php bin/console <name> ...`
- `docker compose exec app tail -f var/log/application-dev.log`
- `make build` — пересборка образов, `make install` — пересборка без кэша.

Сервис `app` монтирует `.` в `/app`, но `vendor/`, `public/build/`, `var/` — анонимные
volumes из образа. После смены composer/yarn-зависимостей — пересборка. Сервис `cron`
запускается в дефолтном профиле, но его crontab (`docker/cron/crontab`) закомментирован.

Entrypoint (`docker/php/entrypoint.sh`) запускает команды от `www-data` (runuser), поэтому
файлы в `/registry` должны быть доступны `www-data` (uid 33).

## Console-команды

Имена большинства команд — snake_case, имена файлов классов — camelCase. Исключения
(camelCase): `balanceSaveForAllPartners`, `sendRegistry`, `setStatus`, `getRegistrySum`, и
`telegram:send`.

Сверка реестров: `registry_get_mail <provider>`, `registry_create <date>`,
`registry_extract <provider>` (аргумент обязателен), `registry_check <provider> <date>`,
`registry_check_fix <provider> <date>`, `registry_fix`, `registry_diff`.

Реестры мерчантам: `email_create|email_check|email_finish|email_cancel <date>`.

Балансы: `balanceSaveForAllPartners`, `balance_create`, `balance_check`,
`balance_finish`, `balance_reset`, `balance_cancel`, `balance_partner_reload`.

`registry_get_mail <provider>` забирает реестры из почты через
`App\Service\RegistryMailService` (IMAP в `App\Service\Imap`, без python/ext-imap).

## Данные и схема

- Миграции отключены: `migrations/` в .gitignore, схема — вручную в `tables.sql`.
- Две логические БД:
  - Doctrine (`DATABASE_URL`) — все сущности лежат в схеме `reconciliation`
    (`registry_config`, `registry_command`, `email_config`, `balance_*`, `collation_*`, `user`).
  - БД процессинга — сырой PDO через `App\Service\Connection\Pool` (параметр `%database%`,
    ключ `dev`/`prod` выбирается по `APP_ENV`). Таблицы `partners`, `terminals`, `wallets`,
    `txs` и др. — в схеме `public`, Doctrine их не знает.
  - Локально обе БД — один Postgres (`db`), в проде `PROCESSING_DB_*_PROD` указывает на
    отдельную БД.
- Файлы реестров — на общем mount `/registry` (на хосте `var/registry`), пути из
  `REG_*`, `IN`, `OUT*`.

## Конфигурация и секреты

- Секреты — только в `.env.local` (gitignored). `.env` — несекретные значения/пути.
- НЕ коммитить `.env.local`, `*.decrypt.private.php`, `public/uploads/`, `*.xlsx`.
- `deploy/app.env` и `deploy/crontab.example` (упомянуты в README/Dockerfile) в репозитории
  отсутствуют — деплой-артефакты.

## Frontend

- Webpack Encore через **yarn** (`yarn install --frozen-lockfile`, `yarn build`).
- `package.json` ссылается на `file:vendor/symfony/...` — сначала `vendor/`, потом `yarn`.

## Тесты и CI

- PHPUnit-тестов нет (`tests/` отсутствует); CI-конфига в репозитории нет.
- PHP-линтера/typecheck нет.

## Стиль

- Комментарии, README и коммиты — на русском.
