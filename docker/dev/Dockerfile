# Development-only image. Production runs PHP natively in the Proxmox LXC
# container (see docs/deployment.md) — this image exists purely so the project
# can be built, tested and run on a workstation without installing PHP locally.
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        sqlite3 \
        poppler-utils \
        cups-client \
        cups-ipp-utils \
    && docker-php-ext-install -j"$(nproc)" zip intl pcntl opcache bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

WORKDIR /app

COPY docker/dev/php.ini /usr/local/etc/php/conf.d/99-assoprint.ini
