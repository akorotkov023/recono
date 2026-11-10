---
name: pipeline-diagnostics
description: Диагностика зависших и ошибочных процессов recono — SQL-запросы к статусам, логи, error_log и collation_issues. Используй, когда процесс не двигается, статус error/issues или нужно понять расхождение.
---

# Диагностика конвейера recono

## 1. Общая проверка

```bash
docker compose ps
docker compose exec -T app php bin/console about
```

Если `app` не запущен — сначала `docker compose up -d --build`, потом диагностика.

## 2. Статусы за дату

Подставь дату в `DATE 'YYYY-MM-DD'`.

### Провайдерские реестры

```sql
SELECT rc.id,
       cfg.provider,
       cfg.slug,
       rc.status,
       rc.total_count,
       rc.total_amount,
       rc.description
FROM reconciliation.registry_command rc
JOIN reconciliation.registry_config cfg ON cfg.id = rc.registry_config_id
WHERE rc.on_date = DATE 'YYYY-MM-DD'
ORDER BY cfg.provider, cfg.slug;
```

### Реестры мерчантам

```sql
SELECT ep.id,
       ec.slug,
       ep.status,
       ep.total_amount,
       ep.count,
       ep.description
FROM reconciliation.email_process ep
JOIN reconciliation.email_config ec ON ec.id = ep.email_config_id
WHERE ep.on_date = DATE 'YYYY-MM-DD'
ORDER BY ep.id;
```

### Балансы

```sql
SELECT bp.id,
       bc.partner_name,
       bc.type,
       bc.wallet_id,
       bp.status,
       bp.total_amount,
       bp.counter_in_was,
       bp.counter_in_be
FROM reconciliation.balance_process bp
JOIN reconciliation.balance_config bc ON bc.id = bp.balance_config_id
WHERE bp.on_date = DATE 'YYYY-MM-DD'
ORDER BY bp.id;
```

### Сводка по статусам

```sql
SELECT status, count(*)
FROM reconciliation.registry_command
WHERE on_date = DATE 'YYYY-MM-DD'
GROUP BY status
ORDER BY status;
```

## 3. Ошибки и расхождения

```sql
-- Ошибки провайдерской сверки
SELECT id, command_id, type, status, provider_id, processing_id, description, created_at
FROM reconciliation.registry_error_log
ORDER BY id DESC
LIMIT 50;

-- Ошибки реестров мерчантам
SELECT id, process_id, description, created_at
FROM reconciliation.email_error_log
ORDER BY id DESC
LIMIT 50;

-- Ошибки балансов
SELECT id, process_id, description, created_at
FROM reconciliation.balance_error_log
ORDER BY id DESC
LIMIT 50;

-- Расхождения сверки
SELECT id, command_id, type, status, provider_id, processing_id, description, created_at
FROM reconciliation.collation_issues
ORDER BY id DESC
LIMIT 100;
```

Для конкретной команды:

```sql
SELECT *
FROM reconciliation.collation_issues
WHERE command_id = <commandId>
ORDER BY id DESC;
```

## 4. Логи

```bash
# Последние строки
docker compose exec -T app tail -n 300 var/log/application-dev.log

# Только нужная команда
docker compose exec -T app sh -c "grep -E '\[registry_check\]|\[registry_extract\]' var/log/application-dev.log | tail -n 100"

# Ошибки
docker compose exec -T app sh -c "grep -E 'ERROR|CRITICAL|Exception' var/log/application-dev.log | tail -n 100"
```

Команды пишут префикс `[имя_команды]` — ищи по нему. Для `registry_get_mail` смотри также
ошибки IMAP, для `email_finish` — SMTP, для `balance_finish` — ответы API процессинга.

## 5. Дерево решений по симптомам

### Провайдерский реестр висит в `new`

- Файл реестра не забран из почты: проверь `registry_get_mail` и каталог `/registry`.
- `registry_extract` не запускался или запускался с другим провайдером.
- Нет прав у `www-data` на файл в `/registry`.
- Неверная дата/провайдер в команде.

### `extracted`, но не `verified`

- `registry_check` не запускался.
- Сверка упала: смотри лог и `registry_error_log`.
- В БД процессинга нет данных за дату или неверный `utc`/`terminal` в конфиге.

### `issues`

1. `registry_diff <provider> <date> --process=<id>` — что именно расходится.
2. `collation_issues` — тип, `processing_id`, `provider_id`.
3. Только потом решай про `registry_fix`. Помни: команда чинит не все ветки, а при
   issues >= 1150 может не сделать ничего.

### `error`

- Смотри `registry_error_log` и лог приложения.
- Частые причины: отсутствующий файл, битый CSV, недоступная БД процессинга, ошибка API.

### `email_process` висит в `created`

- `email_check` не запускался.
- Процесс создан, но гейт по провайдерам был нарушен — проверь, что все
  `registry_command` за дату `verified`.
- `email_check` обрабатывает только `created`; если процесс уже `checking`, повторный запуск
  его не подхватит.

### `email_process` висит в `checking` или `finishing`

- Процесс прерван (падение PHP, таймаут).
- `checking`: смотри лог `email_check`, проверь входные данные конфига
  (`registry_type`, `type`, `terminal`, `utc`, `format`).
- `finishing`: вероятна ошибка SMTP. Не запускай `email_finish` повторно вслепую — письмо
  могло уже уйти. Сначала проверь `email_error_log` и лог, потом реши с пользователем.
- Откат — `email_cancel` (удаляет процесс), только с подтверждением.

### `balance_process` висит в `created`

- `balance_check` не запускался.
- Не заполнены `balance_partners` за дату — сначала `balanceSaveForAllPartners`.
- Провайдерские реестры не `verified` — `balance_create` мог создать error-процесс.

### `balance_process` в `checked`

- Готов к `balance_finish`, но это необратимо. Проверь `wallet_id`, `counter_in_was`,
  `counter_in_be`, `total_amount` и тип (`provider`/`rolling`/партнёрский).
- Покажи пользователю расчёт и дождись подтверждения.

### `balance_process` в `finishing`

- Вызов `wallet/inc` мог пройти, а статус не сохраниться. **Не повторяй `balance_finish`
  автоматически** — сначала проверь фактический баланс кошелька в БД процессинга/API.
- Для отката есть `balance_reset` (только `finished`) и `balance_cancel` (удаляет процесс).

### Дубли процессов

- Проверь `(email_config_id, on_date)` и `(balance_config_id, on_date)`.
- `*_create` пересоздаёт процессы только в «незащищённых» статусах, но при ручных
  манипуляциях дубли возможны.
- Лишний процесс удаляй через `email_cancel`/`balance_cancel`, а не прямым SQL.

## 6. Что собрать перед изменением кода

- дату, конвейер, `id` процесса/команды;
- статус до и после проблемного запуска;
- 50–100 строк лога вокруг ошибки;
- записи из соответствующего `*_error_log` и `collation_issues`;
- точную команду, которая запускалась (аргументы и опции).

Без этого не меняй код и не «чини» данные — сначала воспроизводимая картина.
