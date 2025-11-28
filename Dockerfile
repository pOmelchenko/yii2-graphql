FROM php:7.4-cli

ARG USER_ID=1000
ARG GROUP_ID=1000
ARG USER_NAME=appuser

# Install system packages and PHP extensions required by Yii2 and the tests.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && pecl install xdebug-3.1.6 \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

# Create a non-root user that matches host UID/GID to avoid file-permission issues.
RUN groupadd -g "${GROUP_ID}" "${USER_NAME}" \
    && useradd -m -u "${USER_ID}" -g "${GROUP_ID}" -s /bin/bash "${USER_NAME}"

# Copy Composer from the official image to avoid running as root unnecessarily.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
RUN chown "${USER_NAME}:${USER_NAME}" /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    XDEBUG_MODE=off \
    XDEBUG_CONFIG=""

# Show PHP and Composer versions during build logs for easier debugging.
RUN php -v && composer --version

USER "${USER_NAME}"
