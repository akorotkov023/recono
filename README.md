# Recon — сверка реестров

Symfony 7.1 + Doctrine ORM (PostgreSQL) + Webpack Encore. Cron-конвейер сверки реестров
провайдера и формирование реестров мерчантам. Провайдеры и мерчанты настраиваются в БД
(`registry_config` и `email_config`).

## Запуск локально (Docker)

```bash
docker compose up -d --build
```

- Приложение (nginx): http://localhost:8991
- PostgreSQL поднимается автоматически, схема создаётся из `tables.sql` при первом запуске.
- Рабочая очередь Messenger (опционально): `docker compose --profile worker up -d`

Команды выполняются внутри контейнера:

```bash
docker compose exec app php bin/console <command> [args]
docker compose exec app tail -f var/log/application-dev.log
```

## Секреты и конфигурация

- `.env` — несекретные настройки и значения по умолчанию (пути, DSN очередей, локальная БД).
- `.env.local` — **секреты** (не коммитится, см. `.gitignore`): доступ к API процессинга,
  SMTP, Telegram, IMAP.

Перечень переменных — в комментариях `.env` и `.env.local`. После изменения секретов
пересоздайте контейнеры: `docker compose up -d --force-recreate`.

## Конвейер сверки реестров

Команды (имя класса в snake_case; `<date>` — `Y-m-d`, cron передаёт вчерашнюю дату):

- `registry_get_mail <provider>` — забрать реестры из почты (`App\Service\RegistryMailService`)
- `registry_create <date>` — создать команды по активным настройкам
- `registry_extract <provider>` — извлечь данные из файлов реестров
- `registry_check <provider> <date>` — сверить с базой процессинга
- `registry_fix` — исправление расхождений
- `registry_diff` — разница реестров

Реестры мерчантам:

- `email_create <date>`, `email_check <date>`, `email_finish <date>`

Балансы:

- `balanceSaveForAllPartners`, `balance_create`, `balance_check`, `balance_finish`,
  `balance_reset`, `balance_cancel`, `balance_partner_reload`

Пример cron — `deploy/crontab.example`.

## База данных

- Основная БД (Doctrine) — `DATABASE_URL`.
- БД процессинга (только чтение, `App\Service\Connection\Pool`) — параметры `PROCESSING_DB_*`
  (dev) и `PROCESSING_DB_*_PROD` (prod).
- Миграции отключены, схема поддерживается вручную в `tables.sql`.

## Провайдеры и мерчанты

- Провайдеры — строки таблицы `registry_config` (`provider`, `slug`).
- Мерчанты — строки таблицы `email_config` (`slug`, `registry_type`, `provider`).

`registry_type` в `email_config` задаёт стратегию формирования реестра мерчанта:
`percent` (комиссия %), `provider_amount` (зачисление provider_amount),
`merchant_amount` (зачисление merchant_amount). Возвраты формируются при
`is_refund_checked = true`.

## Frontend

```bash
yarn install --frozen-lockfile
yarn build
```

## Логи

```bash
docker compose exec app tail -f var/log/application-dev.log
```
