# plugins/recono-pipeline/

Claude Code плагин для конвейера recono: агент-оркестратор, скиллы со знаниями и хуки
безопасности. Пользовательская документация — `README.md`.

## Структура

```
recono-pipeline/
├── .claude-plugin/plugin.json             манифест
├── agents/reconciliation-orchestrator.md  агент-оркестратор
├── skills/
│   ├── run-reconciliation/                точка входа /recono-pipeline:run-reconciliation
│   ├── pipeline-runbook/                  команды, статусы, гейты
│   ├── pipeline-diagnostics/              SQL, логи, разбор симптомов
│   └── pipeline-change/                   правила изменения кода и схемы
├── hooks/hooks.json                       SessionStart, PreToolUse, PostToolUse
├── scripts/hooks.py                       реализация хуков
├── AGENTS.md                              правила поддержки плагина
└── README.md
```

Установка: `.claude/settings.json` уже объявляет маркетплейс `recono` из
`.claude-plugin/marketplace.json` и включает плагин.

## Правила

- Знания о командах и статусах — в `skills/pipeline-runbook`, диагностика — в
  `skills/pipeline-diagnostics`, правила изменений — в `skills/pipeline-change`. Не
  дублировать их в агенте и не предзагружать через `skills:`: скиллы подключаются по
  автодискавери, когда действительно нужны.
- Модель агента фиксируется в `model` frontmatter, чтобы смена модели оркестратора не
  меняла поведение субагента.
- При изменении команд, аргументов или статусов в `src/` синхронизируй
  `skills/pipeline-runbook` и контекст в `scripts/hooks.py`.
- Хуки не должны требовать сторонних Python-пакетов; `PreToolUse` только спрашивает или
  запрещает, `PostToolUse` возвращает код 2 только при ошибке `php -l`.
- Не добавлять в плагин секреты и не коммитить `.env.local`.
- Проверка: `claude plugin validate plugins/recono-pipeline --strict`; JSON в
  `hooks/hooks.json`, `.claude-plugin/plugin.json` и marketplace валиден;
  `python3 -m py_compile scripts/hooks.py`; ручной прогон сценария в Claude Code.
