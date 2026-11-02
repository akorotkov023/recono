#!/usr/bin/env bash
# Entrypoint контейнера reconciliation-cron (только локальная разработка).
# Устанавливает crontab и запускает cron в фоне.
set -euo pipefail

LOG_DIR=/app/var/cron
mkdir -p "$LOG_DIR"
# Разрешаем чтение логов контейнеру приложения (www-data, uid 33)
chown 33:33 "$LOG_DIR" 2>/dev/null || true

# Каталоги реестров должны быть доступны приложению (www-data)
mkdir -p /registry/temp /registry/provider /registry/out /registry/act
chown -R 33:33 /registry 2>/dev/null || true

crontab /app/docker/cron/crontab

echo "cron started; logs: $LOG_DIR"
exec cron -f -l 8
