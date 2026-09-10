FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY proyecto/ /var/www/html/
COPY CERBEROBD.sql /var/www/CERBEROBD.sql

RUN mkdir -p /var/www/html/backend/uploads/incidencias \
    && chown -R www-data:www-data /var/www/html/backend/uploads

EXPOSE 80
