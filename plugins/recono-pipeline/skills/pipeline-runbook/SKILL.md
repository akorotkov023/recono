---
name: pipeline-runbook
description: Точные команды, порядок стадий, гейты и статусы конвейера recono. Используй перед запуском любой стадии, при продолжении процесса или восстановлении после ошибки.
---

# Runbook конвейера recono

## Среда

Всё выполняется в Docker. PHP на хосте нет.

```bash
docker compose up -d --build          # поднять приложение, БД, nginx, cron
docker compose ps                     # проверить, что сервисы живы
docker compose exec -T app php bin/console <команда> [аргументы] [опции]
docker compose exec -T app tail -n 200 var/log/application-dev.log
docker compose exec -T db psql -U recono -d recono -c "<SQL>"
```

- Дата везде — `YYYY-MM-DD`. Cron обычно передаёт вчерашнюю дату.
- Файлы реестров лежат на общем mount `/registry` (на хосте `var/registry`).
  Пути: `REG_TEMP=/registry/temp/`, `REG_PROVIDER=/registry/provider/`,
  `OUT_REG_DIR=/registry/out/`, `OUT_ACT_DIR=/registry/act/`.
- Файлы должны быть доступны пользователю `www-data` (uid 33).
- Очереди Messenger (`extract`, `send`) в основном заглушки; не рассчитывай на воркер как на
  обязательную часть конвейера.

## Статусы

Провайдерские реестры (`reconciliation.registry_command.status`):

```
new → extracted → verified
                ↘ issues → error
```

- `new` — команда создана.
- `extracted` — данные извлечены из файла.
- `verified` — сверка прошла, расхождений нет.
- `issues` — есть расхождения (`collation_issues`).
- `error` — ошибка.

Реестры мерчантам (`reconciliation.email_process.status`):

```
created → checking → checked → finishing → finished
                    ↘ error
```

Балансы (`reconciliation.balance_process.status`):

```
created → checked → finishing → finished
        ↘ error
```

`balance_reset` возвращает `finished` → `new`. `email_cancel` и `balance_cancel` удаляют
процессы, кроме `finished`.

## Провайдерский конвейер

```bash
# 1. Забрать реестры из почты (опционально, если файлов ещё нет)
docker compose exec -T app php bin/console registry_get_mail <provider>
# для временного каталога REG_TEMP:
docker compose exec -T app php bin/console registry_get_mail <provider>_temp

# 2. Создать команды по активным настройкам registry_config
docker compose exec -T app php bin/console registry_create <date> --form=0

# 3. Извлечь данные из файлов
docker compose exec -T app php bin/console registry_extract <provider> --form=true

# 4. Сверить с БД процессинга
docker compose exec -T app php bin/console registry_check <provider> <date>

# 5. Диагностика расхождений
docker compose exec -T app php bin/console registry_diff <provider> <date> --process=<id>

# 6. Исправление (меняет данные, только после разбора)
docker compose exec -T app php bin/console registry_fix
```

Важно:

- `registry_extract` требует провайдера; без аргумента команда не запустится.
- `registry_create` идемпотентен по `(config, date)`, но сверяет существующие команды по
  `description` без последних 14 символов. Если формат описания менялся, возможны дубли —
  проверь `registry_command` перед повторным запуском.
- `registry_check_fix` — заглушка с TODO и циклом ожидания. Не используй его в рабочем
  сценарии; для диагностики и ручного исправления есть `registry_diff` и `registry_fix`.
- `registry_fix` сейчас реально обрабатывает не все ветки, а при количестве issues >= 1150
  может ничего не сделать. Сначала посмотри, что именно он закроет.

## Реестры мерчантам

Гейт: **все** провайдерские реестры за дату должны быть `verified`. Иначе `email_create`
пропустит провайдера и запишет предупреждение в лог.

```bash
# 1. Создать процессы (id=0 — все активные конфиги)
docker compose exec -T app php bin/console email_create <date> --id=0

# 2. Сформировать реестры
docker compose exec -T app php bin/console email_check <date> --id=<configId>
# или конкретный процесс:
docker compose exec -T app php bin/console email_check <date> --process=<processId>

# 3. Отправить реестры
docker compose exec -T app php bin/console email_finish <date> --process=<processId>
# или по конфигу:
docker compose exec -T app php bin/console email_finish <date> --id=<configId>

# 4. Отменить/удалить незавершённые процессы (меняет данные)
docker compose exec -T app php bin/console email_cancel <date> --process=<processId>
```

- `email_create` не трогает процессы в статусах `checking`, `checked`, `finishing`, `finished`,
  `error`; процессы в остальных статусах удаляет и создаёт заново.
- `email_check` обрабатывает только `created`.
- `email_finish` обрабатывает только `checked`; при ошибке SMTP ставит `error` и пишет
  `email_error_log`.
- `email_cancel` удаляет всё, что не `finished`.

## Балансы

Гейт: провайдерские реестры за дату `verified`, а `balance_partners` заполнены за дату.

```bash
# 0. Собрать балансы партнёров (без аргументов)
docker compose exec -T app php bin/console balanceSaveForAllPartners

# 1. Создать процессы
docker compose exec -T app php bin/console balance_create <date> --id=<configId>

# 2. Проверить и посчитать
docker compose exec -T app php bin/console balance_check <date> --process=<processId>

# 3. Зачислить на кошелёк — НЕОБРАТИМО, только после подтверждения
docker compose exec -T app php bin/console balance_finish <date> --process=<processId>

# 4. Откат/пересчёт (меняет данные)
docker compose exec -T app php bin/console balance_reset <date> --process=<processId>
docker compose exec -T app php bin/console balance_cancel <date> --process=<processId>
docker compose exec -T app php bin/console balance_partner_reload <date> --process=<processId>
```

- `balance_create` при не сверенных реестрах создаёт error-процесс с описанием причины.
- `balance_check` обрабатывает только `created`; для `provider`/`rolling` считает по кошельку
  провайдера, для остальных — по партнёру.
- `balance_finish` вызывает `PUT wallet/inc/<walletId>`:
  - `provider`/`rolling` → `type=provider`;
  - остальные → `type=internal`.
  Перед запуском сверь `counter_in_was`, `counter_in_be` и `wallet_id`. Повторный запуск
  после успеха может зачислить сумму ещё раз.
- `balance_reset` возвращает в `new` только `finished`.
- `balance_cancel` удаляет всё, что не `finished`.

## Порядок для `all`

1. `registry_get_mail` (если нужно) → `registry_create` → `registry_extract` → `registry_check`.
2. Дождаться `verified` по всем провайдерам за дату.
3. `email_create` → `email_check` → `email_finish`.
4. `balanceSaveForAllPartners` → `balance_create` → `balance_check` → `balance_finish`
   (с подтверждением).

Не запускай следующий конвейер, пока предыдущий не закрыт или пользователь явно не разрешил
исключение.
