# docker/

Инфраструктура локального запуска. Прикладной код — в `src/`; сюда заходят редко.

## Что внутри

```
docker/
├── php/            PHP 8.3 FPM: php.ini, www.conf, opcache, entrypoint.sh
├── nginx/          default.conf; наружу — порт 8991
└── cron/           crontab и entrypoint.sh dev-крона
```

Рядом в корне: `Dockerfile` (стадии base → vendor → assets → app → test → nginx),
`compose.yaml` (app, db, cron, nginx; worker — отдельный профиль), `Makefile`
(up / build / install).

## Сервисы

- `app` — PHP-FPM. Код монтируется из `.` в `/app`; `vendor/`, `public/build/`, `var/` —
  анонимные volumes из образа.
- `db` — PostgreSQL 16; `tables.sql` применяется при первом создании тома.
- `nginx` — http://localhost:8991.
- `cron` — dev-крон. `docker/cron/crontab` закомментирован; продакшн-пример —
  `deploy/crontab.example` (в репозитории отсутствует).
- `worker` — Messenger (`extract`, `send`), запускается с `--profile worker`.

## Запуск

```bash
docker compose up -d --build          # или make up
docker compose --profile worker up -d
docker compose exec app php bin/console <команда> ...
docker compose exec app tail -f var/log/application-dev.log
make build                            # инкрементальная пересборка
make install                          # без кэша
```

- После изменения `composer.json`, `yarn.lock` или `package.json` нужна пересборка образа:
  зависимости и `public/build/` живут в volume, а не в рабочей копии.
- `docker/php/entrypoint.sh` создаёт `/registry/{temp,provider,out,act}` и запускает
  команды (кроме `php-fpm`) от `www-data`. Файлы в `/registry` должны быть доступны
  `www-data` (uid 33).
- `docker/cron/entrypoint.sh` готовит `var/cron` и `/registry`, затем ставит crontab.
- Файлы реестров — общий mount `/registry` (на хосте `var/registry`, переопределяется
  `REGISTRY_DIR`); пути задаются `REG_TEMP`, `REG_PROVIDER`, `IN`, `OUT*`.
- PHP на хосте нет: `php`, `composer`, `bin/console` — только через `docker compose exec`.
- `deploy/app.env` и `deploy/crontab.example` в репозитории отсутствуют — это
  деплой-артефакты, не ищи их в рабочей копии.

## Осторожно

- Не менять entrypoint'ы без проверки прав на `/registry`: иначе команды упадут на чтении
  файлов реестров.
- Не коммитить `var/`, `vendor/`, `public/build/`, `node_modules/`.
- Секреты — в `.env.local`, не в `compose.yaml` и `Dockerfile`.
