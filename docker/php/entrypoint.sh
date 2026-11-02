#!/usr/bin/env bash
# Инициализация каталогов /registry (temp, provider, out, act) и запуск команды.
# php-fpm запускается от root (мастер-процесс) и спускает воркеры в www-data через www.conf;
# остальные команды (воркер Messenger) запускаются от www-data.
set -euo pipefail

mkdir -p /registry/temp /registry/provider /registry/out /registry/act
chown -R www-data:www-data /registry

if [ "$(id -u)" = "0" ] && [ "${1:-}" != "php-fpm" ]; then
    exec runuser -u www-data -- "$@"
fi

exec "$@"
