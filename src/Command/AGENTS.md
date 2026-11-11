# src/Command/

Консольные команды конвейера. Точный порядок стадий и гейты — в
`plugins/recono-pipeline/skills/pipeline-runbook`; здесь — именование и карта команд.
Аргументы и опции перед запуском сверяй с `php bin/console <команда> --help`.

## Именование

- Файл — `<name>Command.php`; исторически встречаются camelCase-имена.
- Имя команды — snake_case. Исключения (camelCase): `balanceSaveForAllPartners`,
  `sendRegistry`, `setStatus`, `getRegistrySum`, `telegram:send`.
- Новые команды — через `#[AsCommand(name: '...', description: '...')]`. В старых файлах
  встречается `setName()` в `configure()`; для новых так не делать.
- Дата — аргумент `Y-m-d`; cron обычно передаёт вчерашнюю дату.
- Логгер — с префиксом `[имя_команды]`.

## Карта команд

### Провайдерские реестры

| Команда | Аргументы / опции |
|---|---|
| `registry_get_mail` | `<provider>`, `--form`, `--date`; суффикс `_temp` — локальный temp-каталог |
| `registry_create` | `<date>`, `--form` |
| `registry_extract` | `<provider>` (обязателен), `--form` |
| `registry_check` | `<provider> <date>` |
| `registry_check_fix` | `<provider> <date>`; заглушка с TODO, не для рабочего сценария |
| `registry_diff` | `<provider> <date>`, `--process` |
| `registry_fix` | без аргументов; чинит `collation_issues` не во всех ветках |
| `getRegistrySum` | без аргументов |

### Реестры мерчантам

| Команда | Аргументы / опции |
|---|---|
| `email_create` | `<date>`, `--id` (`0` — все активные конфиги) |
| `email_check` | `<date>`, `--process`, `--id` |
| `email_finish` | `<date>`, `--process`, `--id` |
| `email_cancel` | `<date>`, `--process` |

### Балансы

| Команда | Аргументы / опции |
|---|---|
| `balanceSaveForAllPartners` | без аргументов |
| `balance_create` | `<date>`, `--id` |
| `balance_check` | `<date>`, `--process` |
| `balance_finish` | `<date>`, `--process`; необратим, только с подтверждением |
| `balance_reset` | `<date>`, `--process` |
| `balance_cancel` | `<date>`, `--process` |
| `balance_partner_reload` | `<date>`, `--process` |

### Прочее

| Команда | Аргументы / опции |
|---|---|
| `sendRegistry` | без аргументов; рассылает CSV из `IN` по активным `email_config` |
| `setStatus` | `<token> <id>`; меняет статус транзакции в процессинге |
| `telegram:send` | без аргументов |

## Новая команда

1. Создай `src/Command/<name>Command.php` с `#[AsCommand]`.
2. Добавь аргументы/опции через `configure()`, дату — `Y-m-d`.
3. Логируй через `LoggerInterface` с префиксом `[имя_команды]`.
4. Если команда меняет процесс — проверь статусы и идемпотентность по `(config, date)`.
5. Если команда необратима — опиши это в description и не запускай без подтверждения.
6. Проверь: `php bin/console list`, `php bin/console <name> --help`, `php -l`.

## Запуск

Все команды — только в контейнере:

```bash
docker compose exec app php bin/console <команда> [аргументы] [опции]
```

Порядок стадий, гейты и диагностика — в скиллах плагина:
`pipeline-runbook`, `pipeline-diagnostics`, `pipeline-change`.
