# Плагин recono-pipeline

Плагин Claude Code для конвейера сверки реестров recono. Он помогает агенту вести процесс
по стадиям, не пропускать гейты, диагностировать зависшие процессы и безопасно менять код.

Архитектура повторяет подход из лекции `hurness/lec4/3-team-portal/plugins/onboarding`:
агент-оркестратор + скиллы со знаниями + точка входа для пользователя + хуки. Роль MCP-сервера
здесь играет Docker: консольные команды Symfony запускаются через
`docker compose exec -T app php bin/console ...`.

## Из чего состоит

```
recono-pipeline/
├── .claude-plugin/plugin.json          манифест плагина
├── agents/
│   └── reconciliation-orchestrator.md  агент-оркестратор трёх конвейеров
├── skills/
│   ├── run-reconciliation/             /recono-pipeline:run-reconciliation — точка входа
│   ├── pipeline-runbook/               точные команды, статусы, гейты
│   ├── pipeline-diagnostics/           SQL, логи, error_log, разбор симптомов
│   └── pipeline-change/                правила изменения кода и схемы
├── hooks/
│   └── hooks.json                      SessionStart, PreToolUse, PostToolUse
├── scripts/
│   └── hooks.py                        реализация хуков
├── AGENTS.md                           правила поддержки плагина
└── README.md
```

## Как это работает

```
/recono-pipeline:run-reconciliation 2026-11-09 all
        │
        ▼
reconciliation-orchestrator (субагент на sonnet; скиллы — по автодискавери)
   0. docker compose ps + статусы за дату
   1. registry_get_mail → registry_create → registry_extract → registry_check
   2. гейт: все провайдерские реестры verified
   3. email_create → email_check → email_finish
   4. balanceSaveForAllPartners → balance_create → balance_check → balance_finish
      (только после подтверждения пользователя)
        │
        ▼
отчёт: статусы, суммы, ошибки, следующий безопасный шаг
```

- **Агент** держит порядок стадий и гейты в отдельном контексте.
- **Скиллы** хранят знания о командах, статусах, SQL и ограничениях репозитория.
- **Скилл-команда** `run-reconciliation` — удобная точка входа для человека.
- **Хуки** добавляют контекст, предупреждают об опасных действиях и линтуют PHP.

## Установка

Из корня репозитория recono:

```bash
claude plugin marketplace add ./ --scope project
claude plugin install recono-pipeline@recono --scope project
```

Или внутри Claude Code, запущенного в корне recono:

```
/plugin marketplace add ./
/plugin install recono-pipeline@recono
```

`.claude/settings.json` уже объявляет маркетплейс `recono` и включает плагин.

## Использование

```bash
# Полный конвейер за дату
/recono-pipeline:run-reconciliation 2026-11-09 all

# Только провайдерские реестры
/recono-pipeline:run-reconciliation 2026-11-09 providers

# Один провайдер
/recono-pipeline:run-reconciliation 2026-11-09 providers --provider=payeer

# Продолжить конкретный процесс мерчанта
/recono-pipeline:run-reconciliation 2026-11-09 merchants --process=123

# Только диагностика (агент не запускает стадии)
/recono-pipeline:run-reconciliation 2026-11-09 balances
```

Без слэш-команды можно просто попросить агента:

> Проверь, почему процессы мерчантов за 2026-11-09 висят в created, и предложи следующий шаг.

Claude сам вызовет `reconciliation-orchestrator`, потому что описание агента содержит
триггеры «сверка», «реестры», «балансы», «зависший процесс».

## Хуки

| Событие | Что делает |
|---|---|
| `SessionStart` | Добавляет в контекст правила: Docker-only, порядок конвейера, необратимость `balance_finish`, защищённые файлы. |
| `PreToolUse` (Bash) | Спрашивает подтверждение перед `balance_finish`, `balance_reset`, `balance_cancel`, `email_cancel`, `registry_fix`, `registry_check_fix`, прямой записью в БД через `psql`, `git reset --hard` и т.п. Запрещает `docker compose down -v`, `docker volume rm/prune`, `rm -rf` по `/registry`, `var/registry`, `vendor`, `.git`. |
| `PreToolUse` (Edit/Write) | Спрашивает подтверждение перед правкой `.env.local`, `tables.sql`, `src/Helper/BaseHelper.php`, файлов в `var/registry`/`public/uploads`. Запрещает правку `.git`. |
| `PostToolUse` (Edit/Write) | Запускает `php -l` в контейнере `app` для изменённого PHP-файла. Если Docker недоступен — не блокирует. |

Хуки не заменяют права Claude Code: это дополнительная страховка. Финальное решение об
опасной операции всё равно принимает пользователь.

## Требования

- Docker и `docker compose`.
- Запущенный сервис `app` для консольных команд и линта.
- `python3` на хосте для хуков.
- Claude Code с поддержкой плагинов.

## Ограничения

- Плагин не создаёт MCP-сервер: «руки» — это `docker compose exec`.
- `registry_check_fix` — заглушка с TODO; плагин не рекомендует её использовать.
- `registry_fix` чинит не все ветки и может ничего не сделать при большом количестве issues.
- В репозитории нет PHPUnit/CI, поэтому проверка изменений — ручная: `php -l`, прогон команды
  на тестовой дате и просмотр статусов/логов.
