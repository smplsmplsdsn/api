FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y \
      libcurl4-openssl-dev \
      libmagickwand-dev \
    && docker-php-ext-install pdo_mysql curl \
    && pecl install imagick \
    && docker-php-ext-enable imagick