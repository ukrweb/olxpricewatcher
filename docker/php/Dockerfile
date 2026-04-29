FROM php:8.3-cli

ARG PHP_MEMORY_LIMIT
ARG PHP_UPLOAD_MAX_FILESIZE
ARG PHP_POST_MAX_SIZE
ARG PHP_MAX_EXECUTION_TIME
ARG PHP_DATE_TIMEZONE

ARG PHP_XDEBUG_MODE
ARG PHP_XDEBUG_CLIENT_HOST
ARG PHP_XDEBUG_CLIENT_PORT
ARG PHP_XDEBUG_IDE_KEY
ARG PHP_XDEBUG_START_WITH_REQUEST
ARG PHP_XDEBUG_DISCOVER_CLIENT_HOST
ARG PHP_XDEBUG_LOG
ARG PHP_XDEBUG_LOG_LEVEL

ENV PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT} \
    PHP_UPLOAD_MAX_FILESIZE=${PHP_UPLOAD_MAX_FILESIZE} \
    PHP_POST_MAX_SIZE=${PHP_POST_MAX_SIZE} \
    PHP_MAX_EXECUTION_TIME=${PHP_MAX_EXECUTION_TIME} \
    PHP_DATE_TIMEZONE=${PHP_DATE_TIMEZONE} \
    PHP_XDEBUG_MODE=${PHP_XDEBUG_MODE} \
    PHP_XDEBUG_CLIENT_HOST=${PHP_XDEBUG_CLIENT_HOST} \
    PHP_XDEBUG_CLIENT_PORT=${PHP_XDEBUG_CLIENT_PORT} \
    PHP_XDEBUG_IDE_KEY=${PHP_XDEBUG_IDE_KEY} \
    PHP_XDEBUG_START_WITH_REQUEST=${PHP_XDEBUG_START_WITH_REQUEST} \
    PHP_XDEBUG_DISCOVER_CLIENT_HOST=${PHP_XDEBUG_DISCOVER_CLIENT_HOST} \
    PHP_XDEBUG_LOG=${PHP_XDEBUG_LOG} \
    PHP_XDEBUG_LOG_LEVEL=${PHP_XDEBUG_LOG_LEVEL}

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip gettext-base libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql intl zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /tmp/php.ini.template
COPY docker/php/xdebug.ini /tmp/xdebug.ini.template
RUN envsubst < /tmp/php.ini.template > /usr/local/etc/php/conf.d/php.ini \
    && envsubst < /tmp/xdebug.ini.template > /usr/local/etc/php/conf.d/xdebug.ini \
    && rm /tmp/php.ini.template /tmp/xdebug.ini.template

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --optimize

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
