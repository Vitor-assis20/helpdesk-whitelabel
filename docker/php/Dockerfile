FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl zip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libicu-dev libonig-dev libxml2-dev libpq-dev postgresql-client \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_pgsql pgsql mbstring zip gd intl bcmath pcntl exif opcache \
    && pecl install redis xdebug && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${GID} dev && useradd -u ${UID} -g dev -m -s /bin/bash dev
USER dev
WORKDIR /var/www/html
