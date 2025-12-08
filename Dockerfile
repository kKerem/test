FROM php:8.2-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --chown=www-data:www-data . /var/www/html

# Composer işlemleri root ile (izin sorunlarını önlemek için), sonra sahipliği geri ver
USER root
RUN if [ -f composer.json ]; then composer install --no-interaction --prefer-dist --no-scripts; fi \
    && chown -R www-data:www-data /var/www/html

USER root

ENV PORT=8000

EXPOSE ${PORT}

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public"]


