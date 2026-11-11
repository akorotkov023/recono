---
name: pipeline-change
description: Правила безопасного изменения кода конвейера recono — куда добавлять команды и сервисы, как менять схему, какие файлы защищены и как проверять без PHPUnit/CI. Используй при доработке команд, статусов, SQL или парсеров.
---

# Изменение кода конвейера recono

## Главные правила

1. **Сначала прочитай корневой `AGENTS.md` и `AGENTS.md` каталога, в котором работаешь** —
   там общие правила, схема и ограничения. Для команд — `src/Command/AGENTS.md`.
2. **PHP — только в Docker.** Не запускай `php`, `composer`, `bin/console` на хосте.
   ```bash
   docker compose exec -T app php -l src/Command/MyCommand.php
   docker compose exec -T app php bin/console list
   ```
3. **Не переписывай `src/Helper/BaseHelper.php` целиком.** Это god object на ~1500 строк:
   SQL, CSV, почта, балансы, API и логирование в одном классе. Изменения — точечные, а новый
   код — в отдельный сервис/хелпер, а не в `BaseHelper`.
4. **Схема — вручную в `tables.sql`.** Миграции отключены (`migrations/` в `.gitignore`).
   Любое изменение таблиц/колонок должно попасть в `tables.sql`, иначе локальная БД разойдётся
   с репозиторием.
5. **Секреты — только в `.env.local`.** Не коммить `.env.local`, `*.decrypt.private.php`,
   `public/uploads/`, `*.xlsx`.
6. **Комментарии, README и коммиты — на русском.**

## Куда добавлять код

- Новая консольная команда — `src/Command/<name>Command.php`, имя команды в `#[AsCommand]`
  в snake_case (исключения — camelCase, см. `src/Command/AGENTS.md`).
- Переиспользуемая логика — `src/Service/...` или отдельный узкий хелпер, не `BaseHelper`.
- Работа с БД процессинга — только через `App\Service\Connection\Pool` (сырой PDO), таблицы
  `public.*`; Doctrine их не знает.
- Работа с БД сверки — через Doctrine-сущности/репозитории, схема `reconciliation`.
- HTTP-вызовы процессинга — через существующий `ApiSession`/хелпер, не изобретай новый клиент.
- Новый статус — сначала добавь его в `RegistryCommandStatusEnum` (или в соответствующий
  набор статусов процесса), потом во все переходы и SQL-фильтры.

## Паттерны, которые нужно сохранять

### 1. Конечный автомат статусов

Не добавляй статус «сбоку». Опиши переходы:

```
new → extracted → verified | issues → error
created → checking → checked → finishing → finished | error
```

Проверь все места, где статус читается: репозитории (`findAllCreatedProcess`,
`findOneCheckedProcess`, `findAllCancelProcess`), команды, шаблоны, SQL-отчёты.

### 2. Find-or-create по `(config, date)`

Перед созданием процесса ищи существующий. Не создавай второй процесс за ту же дату.
Защищённые статусы (`checking`, `checked`, `finishing`, `finished`, `error`) не пересоздавай
без явного решения пользователя.

### 3. Конфигурация вместо хардкода

Стратегии уже вынесены в БД:

- `email_config.registry_type`: `percent`, `provider_amount`, `merchant_amount`
  (`App\Enum\RegistryTypeEnum`);
- `balance_config.type`: `provider`, `rolling`, партнёрский;
- `registry_config.utc`, `email_config.utc`, `terminal`, `format`, `is_refund_checked`.

Новую ветку поведения добавляй через новое значение конфига/enum, а не через `if` по slug
конкретного провайдера.

### 4. Наблюдаемость

- Логируй с префиксом `[имя_команды]`.
- Ошибки процесса пиши в `*_error_log` (`registry_error_log`, `email_error_log`,
  `balance_error_log`), расхождения — в `collation_issues`.
- В отчётах используй `id`, `status`, `total_count`, `total_amount`, `counter_in_was`,
  `counter_in_be`.

### 5. Идемпотентность и безопасный повтор

Команда должна быть безопасна при повторном запуске: либо находит существующую запись, либо
переводит процесс в корректное состояние. Необратимые шаги (`balance_finish`, отправка писем)
должны явно проверять статус и не выполняться дважды.

## Анти-паттерны, которые не размножаем

- **God object.** Не добавляй новые методы в `BaseHelper`; выноси в сервис.
- **Copy-paste трёх конвейеров.** Общие куски (парсинг даты, поиск процесса, логирование,
  смена статуса) выноси в небольшой общий сервис, но не ломай существующее поведение.
- **Сырой SQL со склейкой строк.** Используй параметры/query builder. В `BaseHelper` уже есть
  места с интерполяцией — новые так не пиши.
- **Прямое редактирование данных в БД** вместо команды.
- **Магические числа/строки статусов** — только константы/enum.
- **`dd()`/`var_dump()` в коммите.**
- **Комментарии-заглушки `// TODO` без описания**, что именно и когда делать.

## Чек-лист изменения

1. Прочитал корневой `AGENTS.md`, `src/AGENTS.md` (и `src/Command/AGENTS.md` для команд)
   и relevant-скилл `pipeline-runbook`.
2. Понял, какой статус/переход затрагиваю и какие команды его читают.
3. Проверил, не дублирую ли существующий сервис/метод.
4. Если менял схему — обновил `tables.sql` (и при необходимости локальную БД вручную).
5. Если менял команду — проверил `--help`, аргументы и опции.
6. Прогнал `php -l` для каждого изменённого PHP-файла в Docker.
7. Проверил сценарий на локальной БД: создать → проверить → завершить/отменить.
8. Не закоммитил секреты и сгенерированные файлы.
9. Написал коммит по-русски: что и зачем изменено.

## Проверка без PHPUnit/CI

В репозитории нет тестов и CI, поэтому минимум:

```bash
# Синтаксис
docker compose exec -T app php -l src/Command/MyCommand.php

# Команда видна и аргументы корректны
docker compose exec -T app php bin/console list
docker compose exec -T app php bin/console my_command --help

# Прогон на тестовой дате/конфиге
docker compose exec -T app php bin/console my_command 2026-11-09 --id=<id>

# Статусы и ошибки
docker compose exec -T db psql -U recono -d recono -c "SELECT ..."
docker compose exec -T app tail -n 200 var/log/application-dev.log
```

Для изменений в `BaseHelper` дополнительно проверь все команды, которые его используют:
`grep -rn "BaseHelper" src/Command`.
