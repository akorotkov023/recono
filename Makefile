# Локальная разработка через Docker.
# composer/yarn ставятся только на этапе сборки образа (см. Dockerfile).

.PHONY: up build install

## Поднять приложение: собрать образы и запустить в фоне.
up:
	docker compose up -d --build

## Пересобрать образы (инкрементально).
build:
	docker compose build

## Полная переустановка зависимостей: пересборка образов без кэша
## (внутри образа выполняются composer install, yarn install и yarn build).
install:
	docker compose build --no-cache
