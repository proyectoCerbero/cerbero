FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install curl pdo_mysql mbstring \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p /var/www/html/backend/uploads/incidencias \
    && chown -R www-data:www-data /var/www/html/backend/uploads

EXPOSE 80
