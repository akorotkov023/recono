# AGENTS.md

Symfony 7.1 + Doctrine ORM (PostgreSQL) + Webpack Encore. Cron-конвейер сверки реестров
провайдера и реестров мерчантам. Провайдеры и мерчанты задаются в БД (`registry_config` и
`email_config`).

## Запуск / проверка локально

Всё в Docker (PHP/node на хосте не нужны):

- `docker compose up -d --build` — app + nginx + PostgreSQL, http://localhost:8990
- `docker compose --profile worker up -d` — воркер Messenger (очереди `extract`, `send`)
- `docker compose exec app php bin/console <name> ...`
- `docker compose exec app tail -f var/log/application-dev.log`

Сервис `app` монтирует `.` в `/app`, но `vendor/`, `public/build/`, `var/` — анонимные
volumes из образа. После смены composer/yarn-зависимостей — пересборка.

## Console-команды

Имена команд — snake_case, имена файлов классов — camelCase.

Сверка реестров: `registry_create <date>`, `registry_get_mail <provider>`,
`registry_extract`, `registry_check <provider> <date>`, `registry_fix`, `registry_diff`.

Реестры мерчантам: `email_create|email_check|email_finish <date>`.

Балансы: `balanceSaveForAllPartners`, `balance_create`, `balance_check`,
`balance_finish`, `balance_reset`, `balance_cancel`, `balance_partner_reload`.

`registry_get_mail <provider>` забирает реестры из почты через
`App\Service\RegistryMailService` (IMAP по `IMAP_*`, без python/ext-imap).

## Данные и схема

- Миграции отключены: `migrations/` в .gitignore, схема — вручную в `tables.sql`.
- Две БД: `default` (`DATABASE_URL`) и БД процессинга через `App\Service\Connection\Pool`
  (параметр `%database%`, `PROCESSING_DB_*` и `PROCESSING_DB_*_PROD`).
- Файлы реестров — на общем mount `/registry` (на хосте `var/registry`), пути из
  `REG_*`, `IN`, `OUT*`.

## Конфигурация и секреты

- Секреты — только в `.env.local` (gitignored). `.env` — несекретные значения/пути.
- НЕ коммитить `.env.local`, `*.decrypt.private.php`, `public/uploads/`, `*.xlsx`.

## Frontend

- Webpack Encore через **yarn** (`yarn install --frozen-lockfile`, `yarn build`).
- `package.json` ссылается на `file:vendor/symfony/...` — сначала `vendor/`, потом `yarn`.

## CI

- PHPUnit-тестов нет (`tests/` отсутствует).
- `composer audit` — non-blocking (известные уязвимости в зависимостях).

## Стиль

- Комментарии, README и коммиты — на русском.
- PHP-линтера/typecheck нет.
