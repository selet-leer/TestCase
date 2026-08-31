FROM php:8.2-cli-alpine

RUN apk add --no-cache zip unzip git bash

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD if [ ! -d "vendor" ]; then \
        composer install --prefer-dist --no-interaction; \
    fi && \
    mkdir -p storage/framework/cache/data \
             storage/framework/views \
             storage/framework/sessions \
             storage/logs \
             bootstrap/cache && \
    chmod -R 777 storage bootstrap/cache && \
    php artisan serve --host=0.0.0.0 --port=8000

EXPOSE 8000
