#!/usr/bin/env python3
"""Хуки плагина recono-pipeline.

Три задачи:
- SessionStart: подсказать агенту правила работы с recono.
- PreToolUse: предупредить об необратимых командах и защищённых файлах.
- PostToolUse: проверить синтаксис изменённого PHP-файла через php -l в Docker.

Скрипт не зависит от сторонних библиотек и всегда завершается кодом 0, кроме
случая, когда PostToolUse должен показать агенту ошибку линта (код 2).
"""

from __future__ import annotations

import json
import os
import re
import shlex
import subprocess
import sys

PROJECT_DIR = os.path.realpath(os.environ.get("CLAUDE_PROJECT_DIR") or os.getcwd())

CONTEXT = """Контекст recono-pipeline:
- PHP и консольные команды — только в Docker: docker compose exec -T app php bin/console ...
- Провайдерский конвейер: registry_get_mail -> registry_create -> registry_extract -> registry_check.
- Реестры мерчантам создаются только после статуса verified у всех провайдерских реестров за дату.
- balance_finish вызывает wallet/inc и необратим: сначала проверь wallet_id, counter_in_was, counter_in_be.
- Перед изменениями кода прочитай корневой AGENTS.md, AGENTS.md нужного каталога и скиллы pipeline-runbook, pipeline-diagnostics, pipeline-change.
- Схема поддерживается вручную в tables.sql, миграций нет. Защищённые файлы: .env.local, tables.sql, src/Helper/BaseHelper.php."""

# Команды, которые меняют данные или необратимы. Спрашиваем подтверждение.
DANGEROUS_CONSOLE = {
    "balance_finish": "вызывает PUT wallet/inc и необратимо меняет баланс кошелька",
    "balance_reset": "возвращает завершённые процессы в new и меняет данные",
    "balance_cancel": "удаляет процессы балансов",
    "email_cancel": "удаляет процессы реестров мерчантам",
    "registry_fix": "массово исправляет расхождения сверки",
    "registry_check_fix": "циклическая заглушка с TODO, может долго висеть",
    "doctrine:database:drop": "удаляет базу данных",
    "doctrine:schema:drop": "удаляет схему БД",
}

SQL_WRITE = re.compile(
    r"\b(drop\s+(table|schema|database)|truncate\s+(table\s+)?\w+|"
    r"delete\s+from|update\s+[a-z_][\w.]*\s+set|insert\s+into|alter\s+table)\b",
    re.IGNORECASE,
)

PROTECTED_SECRETS = (".env.local",)
PROTECTED_DATA_DIRS = ("var/registry", "public/uploads")


def read_payload() -> dict:
    try:
        raw = sys.stdin.read()
        return json.loads(raw) if raw.strip() else {}
    except (json.JSONDecodeError, OSError):
        return {}


def emit(data: dict) -> None:
    print(json.dumps(data, ensure_ascii=False))


def ask(reason: str) -> None:
    emit(
        {
            "hookSpecificOutput": {
                "hookEventName": "PreToolUse",
                "permissionDecision": "ask",
                "permissionDecisionReason": reason,
            }
        }
    )


def deny(reason: str) -> None:
    emit(
        {
            "hookSpecificOutput": {
                "hookEventName": "PreToolUse",
                "permissionDecision": "deny",
                "permissionDecisionReason": reason,
            }
        }
    )


def normalize(path: str) -> str:
    stripped = path.rstrip("/")
    if stripped == "":
        return os.sep
    return os.path.normpath(stripped)


def is_under(path: str, base: str) -> bool:
    return path == base or path.startswith(base + os.sep)


def protected_rm_target(target: str) -> tuple[str, str] | None:
    """Возвращает (решение, причина) для цели rm, если она защищена."""
    norm = normalize(target)
    if norm == "/registry" or norm.startswith("/registry/"):
        return "deny", "Каталог /registry содержит файлы реестров. Удаление запрещено."
    absolute = norm if os.path.isabs(norm) else os.path.join(PROJECT_DIR, norm)
    absolute = normalize(absolute)

    if norm in (".", "./", "*") or absolute == PROJECT_DIR:
        return "deny", "Нельзя удалять корень проекта целиком."
    if absolute == os.sep or is_under(PROJECT_DIR, absolute):
        return "deny", "Нельзя удалять каталог, содержащий проект."

    hard_protected = ("var/registry", "vendor", ".git")
    for rel in hard_protected:
        if is_under(absolute, normalize(os.path.join(PROJECT_DIR, rel))):
            return "deny", f"Каталог {rel} защищён: удаление снесёт данные или зависимости."

    for rel in PROTECTED_DATA_DIRS:
        if is_under(absolute, normalize(os.path.join(PROJECT_DIR, rel))):
            return "ask", f"Каталог {rel} содержит данные конвейера. Удаление требует подтверждения."
    return None


def check_bash(command: str) -> tuple[str, str] | None:
    cmd = command.strip()
    if not cmd:
        return None

    # docker compose down -v / volume rm / system prune
    if re.search(r"\bdocker\s+compose\b", cmd) and re.search(r"\bdown\b", cmd):
        if re.search(r"(^|\s)(-v|--volumes)(\s|$)", cmd):
            return "deny", "docker compose down -v удалит volume с БД. Используй down без -v."
    if re.search(r"\bdocker\s+volume\s+(rm|prune)\b", cmd):
        return "deny", "Удаление docker volume снесёт локальную БД."
    if re.search(r"\bdocker\s+system\s+prune\b", cmd):
        return "deny", "docker system prune может удалить volume и данные конвейера."

    # rm по защищённым каталогам
    for match in re.finditer(r"(?:^|[;&|]\s*|\bsudo\s+)rm\s+([^;&|]+)", cmd):
        args = match.group(1)
        try:
            tokens = shlex.split(args, posix=True)
        except ValueError:
            tokens = args.split()
        recursive = any(
            token in ("--recursive",) or (token.startswith("-") and ("r" in token or "R" in token))
            for token in tokens
        )
        targets = [token for token in tokens if not token.startswith("-")]
        for target in targets:
            result = protected_rm_target(target)
            if result is None:
                continue
            decision, reason = result
            if decision == "deny" and not recursive:
                decision, reason = "ask", reason + " Цель указана без -r, но это всё равно данные конвейера."
            return decision, reason

    # Опасные git-операции
    if (
        re.search(r"\bgit\s+reset\s+--hard\b", cmd)
        or re.search(r"\bgit\s+clean\s+-[a-zA-Z]*f", cmd)
        or re.search(r"\bgit\s+(checkout|restore)\s+(--\s+)?\.", cmd)
        or re.search(r"\bgit\s+push\b[^\n]*--force", cmd)
    ):
        return "ask", "Операция перезапишет локальные изменения. Подтверди, что они не нужны."

    # Запись в БД через psql
    if re.search(r"\bpsql\b", cmd) and SQL_WRITE.search(cmd):
        return "ask", "Прямая запись в БД. Штатный путь — консольная команда; подтверди SQL-изменение."

    # Опасные консольные команды recono
    console = re.search(r"\bbin/console\s+([A-Za-z0-9:_-]+)", cmd)
    if console:
        name = console.group(1)
        if name in DANGEROUS_CONSOLE and not re.search(r"(^|\s)(--help|-h)(\s|$)", cmd):
            return "ask", f"Команда {name} {DANGEROUS_CONSOLE[name]}. Нужно явное подтверждение."

    return None


def check_path(path: str) -> tuple[str, str] | None:
    if not path:
        return None
    absolute = normalize(path if os.path.isabs(path) else os.path.join(PROJECT_DIR, path))
    try:
        rel = os.path.relpath(absolute, PROJECT_DIR)
    except ValueError:
        return None
    base = os.path.basename(absolute)

    if base in PROTECTED_SECRETS or base.endswith(".decrypt.private.php"):
        return "ask", f"{base} содержит секреты. Убедись, что не коммитишь его."
    if base == "tables.sql":
        return "ask", "tables.sql — источник схемы (миграций нет). Изменение затрагивает локальную БД."
    if rel == os.path.join("src", "Helper", "BaseHelper.php"):
        return "ask", "BaseHelper — god object на ~1500 строк. Лучше вынести изменение в отдельный сервис."
    if any(is_under(absolute, normalize(os.path.join(PROJECT_DIR, d))) for d in PROTECTED_DATA_DIRS):
        return "ask", "Это каталог данных конвейера. Прямое редактирование может испортить реестры."
    if absolute == "/registry" or absolute.startswith("/registry/"):
        return "ask", "Это каталог файлов реестров. Прямое редактирование может испортить данные."
    if rel == ".git" or rel.startswith(".git" + os.sep):
        return "deny", "Прямое изменение .git запрещено."
    return None


def pre_tool_use(payload: dict) -> int:
    tool = payload.get("tool_name", "")
    tool_input = payload.get("tool_input") or {}

    if tool == "Bash":
        result = check_bash(str(tool_input.get("command", "")))
    elif tool in ("Edit", "Write", "MultiEdit"):
        result = check_path(str(tool_input.get("file_path", "")))
    else:
        result = None

    if result is None:
        return 0
    decision, reason = result
    if decision == "deny":
        deny(reason)
    else:
        ask(reason)
    return 0


def post_tool_use(payload: dict) -> int:
    tool = payload.get("tool_name", "")
    if tool not in ("Edit", "Write", "MultiEdit"):
        return 0

    path = str((payload.get("tool_input") or {}).get("file_path", ""))
    if not path.endswith(".php"):
        return 0

    absolute = normalize(path if os.path.isabs(path) else os.path.join(PROJECT_DIR, path))
    try:
        rel = os.path.relpath(absolute, PROJECT_DIR)
    except ValueError:
        return 0

    if rel.startswith(".." + os.sep) or rel in ("..",):
        return 0
    if rel.startswith(("vendor" + os.sep, "var" + os.sep, "node_modules" + os.sep)):
        return 0

    container_path = "/app/" + rel.replace(os.sep, "/")
    try:
        proc = subprocess.run(
            ["docker", "compose", "exec", "-T", "app", "php", "-l", container_path],
            cwd=PROJECT_DIR,
            capture_output=True,
            text=True,
            timeout=25,
        )
    except (FileNotFoundError, subprocess.TimeoutExpired, OSError):
        # Docker недоступен — не блокируем работу, линт можно запустить вручную.
        return 0

    output = (proc.stdout + proc.stderr).strip()
    if proc.returncode == 0:
        return 0

    infra_error = (
        "is not running" in output
        or "No such service" in output
        or "Cannot connect to the Docker daemon" in output
        or "docker: command not found" in output
    )
    if infra_error:
        return 0

    print(f"PHP-линт не прошёл для {rel}:\n{output}", file=sys.stderr)
    return 2


def session_start(_payload: dict) -> int:
    emit(
        {
            "hookSpecificOutput": {
                "hookEventName": "SessionStart",
                "additionalContext": CONTEXT,
            }
        }
    )
    return 0


def main() -> int:
    global PROJECT_DIR
    if len(sys.argv) < 2:
        return 0
    event = sys.argv[1]
    payload = read_payload()
    PROJECT_DIR = os.path.realpath(
        os.environ.get("CLAUDE_PROJECT_DIR") or payload.get("cwd") or os.getcwd()
    )
    if event == "session-start":
        return session_start(payload)
    if event == "pre-tool-use":
        return pre_tool_use(payload)
    if event == "post-tool-use":
        return post_tool_use(payload)
    return 0


if __name__ == "__main__":
    sys.exit(main())
