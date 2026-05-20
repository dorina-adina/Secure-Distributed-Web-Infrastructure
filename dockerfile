FROM php:8.2-fpm

RUN apt-get update && apt-get install -y libpq-dev && \
    docker-php-ext-install pdo pdo_mysql && \
    pecl install redis && \
    docker-php-ext-enable redis

WORKDIR /var/www/html
