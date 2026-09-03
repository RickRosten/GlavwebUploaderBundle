FROM php:8.4-cli

RUN apt-get update && \
    apt-get install -y \
      unzip \
      git \
      libzip-dev \
      libpng-dev \
    && docker-php-ext-install zip gd exif

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /usr/src/

COPY scripts/ scripts/
RUN chmod +x scripts/*

WORKDIR /usr/src/bundle
