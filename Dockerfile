ARG PHP_VERSION=8.3
ARG NODE_VERSION=20

# ---------------------------------------------------------------- base
FROM php:${PHP_VERSION}-fpm-bookworm AS base

ENV TZ=Europe/Moscow \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        ca-certificates curl git unzip \
        cron \
 && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Только расширения из исходников PHP (качается лишь apt). Расширений с PECL (amqp, apcu, http)
# из старого образа намеренно нет: приложение их не использует (composer.lock их не требует,
# Messenger работает через Doctrine, apcu-адаптер кэша выключен), а pecl.php.net
# из сети раннера отвечает нестабильно и ломал сборку.
RUN install-php-extensions bcmath gd gmp intl pdo_pgsql soap zip

COPY docker/php/php.ini      "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/*.ini "$PHP_INI_DIR/conf.d/"
COPY docker/php/www.conf     /usr/local/etc/php-fpm.d/www.conf

RUN chown -R www-data:www-data /var/www

WORKDIR /app

# ---------------------------------------------------------------- vendor
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock symfony.lock ./

RUN --mount=type=cache,target=/tmp/composer/cache \
    composer install --no-dev --no-scripts --no-autoloader --no-progress --no-interaction --prefer-dist

# ---------------------------------------------------------------- assets
FROM node:${NODE_VERSION}-bookworm-slim AS assets

WORKDIR /app
ENV NODE_ENV=production

COPY package.json yarn.lock ./
# package.json ссылается на пакеты из vendor (file:vendor/symfony/...)
COPY --from=vendor /app/vendor/symfony/stimulus-bundle/assets ./vendor/symfony/stimulus-bundle/assets
COPY --from=vendor /app/vendor/symfony/ux-chartjs/assets       ./vendor/symfony/ux-chartjs/assets

RUN --mount=type=cache,target=/usr/local/share/.cache/yarn \
    yarn install --frozen-lockfile --production=false --network-timeout 600000

COPY webpack.config.js ./
COPY assets ./assets
RUN yarn build

# ---------------------------------------------------------------- app
FROM base AS app

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor        ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build  ./public/build

# Плейсхолдеры нужны только чтобы собрать контейнер DI и прогреть кэш.
# Реальные значения приходят из окружения контейнера (deploy/app.env).
RUN --mount=type=cache,target=/tmp/composer/cache \
    composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && composer dump-env prod \
 && DATABASE_URL="postgresql://app:app@127.0.0.1:5432/app?serverVersion=13&charset=utf8" \
    bin/console assets:install public --no-interaction \
 && DATABASE_URL="postgresql://app:app@127.0.0.1:5432/app?serverVersion=13&charset=utf8" \
    bin/console cache:warmup --no-interaction \
 && chown -R www-data:www-data var public/uploads

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD bash -c 'exec 3<>/dev/tcp/127.0.0.1/9000' || exit 1

CMD ["php-fpm"]

# ---------------------------------------------------------------- test
FROM app AS test

USER root
ENV APP_ENV=test \
    APP_DEBUG=1

RUN --mount=type=cache,target=/tmp/composer/cache \
    composer install --no-scripts --no-progress --no-interaction --prefer-dist \
 && composer dump-autoload --optimize \
 && rm -f .env.local.php \
 && chown -R www-data:www-data var vendor

USER www-data

# ---------------------------------------------------------------- nginx
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /app/public /app/public

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD wget -qO- http://127.0.0.1/healthz >/dev/null || exit 1
