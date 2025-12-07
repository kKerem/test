# Dockerfile (örnek)
FROM php:8.2-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql

WORKDIR /app

# composer kurulumu (opsiyonel: eğer vendor repoda yoksa buildde kur)
COPY composer.json composer.lock /app/
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && composer install --no-interaction --prefer-dist --no-scripts --no-dev || true

COPY . /app

# Basit entrypoint: PORT env yoksa 3000 kullan
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-3000} -t public"]
