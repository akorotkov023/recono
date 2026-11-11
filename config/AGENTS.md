# config/

Symfony-конфигурация. Здесь же связываются env-переменные, Doctrine и Messenger.

## Что внутри

```
config/
├── packages/
│   ├── doctrine.yaml                         Doctrine, схема reconciliation
│   ├── databases_connection_parameters.yaml  параметр %database% для Pool
│   ├── messenger.yaml                        очереди extract/send/failed
│   ├── monolog.yaml                          логи (application-dev.log)
│   ├── security.yaml                         вход в UI
│   └── ...                                   cache, mailer, twig, webpack и др.
├── routes.yaml                               attribute-роутинг контроллеров
├── routes/                                   служебные роуты
├── services.yaml                             DI, bind'ы env и сервисов
├── bundles.php
└── preload.php
```

## Env и секреты

- `.env` — несекретные значения и локальные DSN (БД, Messenger, пути `/registry`).
- `.env.local` — секреты: API процессинга, SMTP, Telegram, IMAP. Не коммитить.
- После изменения `.env`/`.env.local` пересоздай контейнеры:
  `docker compose up -d --force-recreate`.
- Переменные процессинга (`PROCESSING_*`) приходят из окружения контейнера; имена не
  должны начинаться с цифры — php-fpm не передаёт такие переменные воркерам.

## Две БД

- `DATABASE_URL` — Doctrine, основная БД, схема `reconciliation`.
- `%database%` (см. `databases_connection_parameters.yaml`) — параметры для
  `App\Service\Connection\Pool`: ключи `dev`/`prod`, выбор по `APP_ENV`.
- Локально обе БД — один Postgres (`db`); в проде `PROCESSING_DB_*_PROD` указывает на
  отдельную БД.
- Миграции отключены (`doctrine_migrations.yaml` есть, но `migrations/` в `.gitignore`);
  схема — вручную в `tables.sql`.

## Messenger

- `extract` → `App\Message\ExtractedRegistryMessage`
- `send` → `App\Message\ChangeStatusMessage`
- `failed` — failure transport.
- Очереди в основном заглушки; реальный оркестратор — UI/cron, а не воркер.

## Сервисы

`services.yaml` связывает env с сервисами: `Pool` (`%database%`), `ApiSession`
(`PROCESSING_*`), `RegistryMailService` (`IMAP_*`, `REG_*`), `BaseHelper`,
`TelegramService`. Новые env-зависимости добавляй туда, а не в код.

## Правила

- Новые секреты — только в `.env.local`, в `.env` — пустые плейсхолдеры.
- Не менять `DATABASE_URL` и `PROCESSING_*` в закоммиченных файлах под локальную машину.
- При добавлении пакета/бандла обнови `bundles.php` и `composer.json`.
