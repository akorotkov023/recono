# AGENTS.md

## Проект

Symfony 7.1 + Doctrine ORM (PostgreSQL) + Webpack Encore. Cron-конвейер сверки реестров
провайдера и формирование реестров мерчантам; отдельно ведутся балансы. Провайдеры и
мерчанты задаются в БД (`registry_config`, `email_config`), а не в коде. Порядок стадий и
точные команды — в `plugins/recono-pipeline/skills/pipeline-runbook`.

## Корень проекта

| Путь | Что внутри |
|---|---|
| `src/` | PHP-код приложения. Правила — `src/AGENTS.md`; команды — `src/Command/AGENTS.md` |
| `config/` | Symfony-конфигурация, env, Doctrine, Messenger. Детали — `config/AGENTS.md` |
| `docker/` | PHP-FPM, nginx, cron, entrypoint'ы. Детали — `docker/AGENTS.md` |
| `assets/` | Stimulus-контроллеры, SCSS, frontend-зависимости. Детали — `assets/AGENTS.md` |
| `templates/` | Twig-шаблоны, сгруппированы по конвейерам. Детали — `templates/AGENTS.md` |
| `plugins/recono-pipeline/` | Claude Code плагин: агент, скиллы, хуки. Детали — `plugins/recono-pipeline/AGENTS.md` |
| `.claude/`, `.claude-plugin/` | Настройки Claude Code и маркетплейс плагина |
| `public/` | Web-root; `public/build/` и `public/uploads/` — generated, не коммитить |
| `translations/` | Переводы интерфейса |
| `var/` | Логи, кэш и локальные реестры (gitignored) |
| `bin/console` | Входная точка консольных команд |
| `tables.sql` | Схема БД вручную; миграции отключены |
| `compose.yaml`, `Dockerfile`, `Makefile` | Локальное окружение и сборка |
| `.env`, `.env.local` | Несекретные настройки / секреты (`.env.local` не коммитить) |
| `README.md` | Запуск, команды, переменные окружения |
| `PATTERNS.md` | Устойчивые паттерны кода и известный техдолг |

## Общие правила

- PHP, Composer и yarn запускаются только в Docker. Команды и особенности окружения —
  `docker/AGENTS.md`.
- Секреты — только в `.env.local`. Не коммитить `.env.local`, `*.decrypt.private.php`,
  `public/uploads/`, `*.xlsx`.
- Миграции отключены: изменения схемы — вручную в `tables.sql` и в локальной БД.
- Комментарии, README и коммиты — на русском.
- PHPUnit, CI и PHP-линтера нет: минимум — `php -l` в контейнере и прогон затронутой команды.
- Детали по каталогу ищи в его `AGENTS.md`; он подхватывается при работе с файлами каталога.
