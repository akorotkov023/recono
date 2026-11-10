# Паттерны, которые видны в recono

1. **Конвейер как конечный автомат статусов.**
   - Провайдерские реестры: `new → extracted → verified | issues → error` (`RegistryCommandStatusEnum`).
   - Реестры мерчантам: `created → checking → checked → finishing → finished | error`.
   - Балансы: `created → checked → finishing → finished | error`, плюс `balance_reset` возвращает `finished → new`.
   - Статусы — главный контракт между командами: каждая следующая стадия выбирает процессы только в определённом статусе.

2. **Идемпотентный find-or-create по `(config, date)`.**
   - `registry_create`, `email_create`, `balance_create` сначала ищут существующий процесс за дату.
   - Защищённые статусы (`checking`, `checked`, `finishing`, `finished`, `error`) не пересоздаются.
   - Есть хрупкое место: `registry_create` сравнивает существующие команды по `description` без последних 14 символов, поэтому при смене формата имени файла возможны дубли.

3. **Стратегия через конфигурацию, а не через код.**
   - `email_config.registry_type`: `percent`, `provider_amount`, `merchant_amount` (`RegistryTypeEnum`).
   - `balance_config.type`: `provider`, `rolling`, партнёрский.
   - `utc`, `terminal`, `format`, `is_refund_checked` тоже задаются в БД.
   - Новое поведение добавляется значением конфига/enum, а не `if` по конкретному провайдеру.

4. **Две логические БД — два способа доступа.**
   - Doctrine и сущности в схеме `reconciliation`.
   - Сырой PDO через `App\Service\Connection\Pool` к схеме `public` (`partners`, `terminals`, `wallets`, `txs`).
   - HTTP-вызовы процессинга — через `ApiSession`.
   - Граница осознанная, но нет единой транзакции и ORM-связей между половинами.

5. **Оркестрация снаружи приложения.**
   - Контроллеры запускают команды через `Symfony\Component\Process\Process` (`php ../bin/console ...`).
   - Cron-контейнер дергает `registry_create` за вчерашнюю дату.
   - Messenger-очереди `extract`/`send` в `messenger.yaml` в основном заглушки, поэтому реальный оркестратор — UI/cron, а не воркер.

6. **Наблюдаемость через статусы и журналы.**
   - `registry_error_log`, `email_error_log`, `balance_error_log`.
   - `collation_issues` и `collation_issues_log` для расхождений.
   - Логи с префиксом `[имя_команды]`.
   - Это хорошая основа для диагностического агента: по статусу и логу можно восстановить картину.

7. **Главный технический долг — god object и copy-paste.**
   - `src/Helper/BaseHelper.php` — ~1550 строк: SQL, CSV, почта, балансы, API, логирование.
   - Три конвейера (registry/email/balance) повторяют парсинг даты, поиск процесса, смену статуса и запись ошибок.
   - Статусы email/balance пока строками, а не enum; в SQL местами склейка строк.
   - `registry_check_fix` — заглушка с TODO, `registry_fix` чинит не все ветки.

8. **Разрыв в верификации.**
   - Нет PHPUnit, CI и PHP-линтера.
   - Схема поддерживается вручную в `tables.sql`, миграции отключены.
   - Поэтому изменения проверяются прогоном команд, `php -l` и просмотром статусов/логов.
