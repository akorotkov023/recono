# src/

PHP-код приложения. Точные команды и порядок стадий — в
`plugins/recono-pipeline/skills/pipeline-runbook`; здесь — правила кода.

## Карта

```
src/
├── Command/          консольные команды; см. Command/AGENTS.md
├── Controller/       web-UI и запуск команд через Symfony Process
├── Entity/           Doctrine-сущности схемы reconciliation
├── Repository/       выборки процессов и статусов
├── Service/          ApiSession, RegistryMailService, Imap, файлы, Telegram
│   └── Connection/   Pool — PDO к БД процессинга
├── Helper/           BaseHelper — исторический god object (~1550 строк)
├── Message/          Messenger-сообщения
├── MessageHandler/   обработчики очередей extract/send
├── Enum/             RegistryCommandStatusEnum, RegistryTypeEnum
├── DTO/              объекты для расхождений и транзакций
├── Form/             Symfony-формы
├── Logging/          форматтер логов
├── Security/         аутентификация и access denied
└── Kernel.php
```

## Две БД

- **Doctrine** (`DATABASE_URL`) — схема `reconciliation`: `registry_config`,
  `registry_command`, `email_config`, `balance_*`, `collation_*`, `user`. Работать через
  сущности и репозитории.
- **БД процессинга** — сырой PDO через `App\Service\Connection\Pool` (параметр `%database%`,
  ключ `dev`/`prod` выбирается по `APP_ENV`). Таблицы `partners`, `terminals`, `wallets`,
  `txs` и др. лежат в схеме `public`; Doctrine их не знает.
- HTTP-вызовы процессинга — через `App\Service\ApiSession`, не новый клиент.
- Почта: `registry_get_mail` забирает реестры через `RegistryMailService`; IMAP-клиент —
  `Service/Imap`, без ext-imap и python.
- Общей транзакции между двумя БД нет: не связывать сущности ORM и не рассчитывать на
  атомарность.

## Правила кода

- Новые команды — в `Command/`; имя и формат аргументов — см. `Command/AGENTS.md`.
- Переиспользуемая логика — в `Service/`, а не в `BaseHelper`.
- `BaseHelper` не переписывать целиком и не добавлять в него новое: это god object на
  ~1550 строк (SQL, CSV, почта, балансы, API, логирование). Изменения — точечные.
- Схема — вручную в `tables.sql`; миграции отключены. Меняешь таблицы/колонки — обнови
  `tables.sql` и локальную БД.
- Статусы — конечный автомат:
  - провайдеры: `new → extracted → verified | issues → error`;
  - мерчанты: `created → checking → checked → finishing → finished | error`;
  - балансы: `created → checked → finishing → finished | error`.
  Новый статус — сначала в enum, затем во все переходы, репозитории, SQL-фильтры и шаблоны.
- `*_create` идемпотентны по `(config, date)`: сначала ищи существующий процесс.
  Защищённые статусы (`checking`, `checked`, `finishing`, `finished`, `error`) не
  пересоздавай без явного решения.
- Поведение задаётся конфигом/enum (`registry_type`, `balance_config.type`, `utc`,
  `terminal`, `format`, `is_refund_checked`), а не `if` по slug провайдера.
- Логи — с префиксом `[имя_команды]`; ошибки — в `*_error_log`, расхождения —
  `collation_issues`.
- Новые SQL-запросы — с параметрами, без склейки строк. В `BaseHelper` есть старые места
  с интерполяцией; новые так не писать.
- Не коммитить `dd()`, `var_dump()` и `// TODO` без описания.

## Проверка

PHPUnit, CI и PHP-линтера нет. Минимум:

```bash
docker compose exec app php -l src/Command/MyCommand.php
docker compose exec app php bin/console list
docker compose exec app php bin/console my_command --help
docker compose exec app php bin/console my_command 2026-11-09 --id=<id>
docker compose exec app tail -n 200 var/log/application-dev.log
```

Для изменений в `BaseHelper` проверь все команды, которые его используют:
`grep -rn "BaseHelper" src/Command`.
