FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git curl libpq-dev libicu-dev libxml2-dev \
    libonig-dev libssl-dev pkg-config unzip libzstd-dev \
    && docker-php-ext-install pdo pdo_pgsql intl mbstring xml \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=prod
ENV APP_DEBUG=0


WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-scripts

EXPOSE 8000

CMD php bin/console cache:clear \
    && php bin/console doctrine:migrations:migrate --no-interaction \
    && php -S 0.0.0.0:8000 -t public