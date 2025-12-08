FROM php:8.2-fpm

ARG user=www-data
ARG uid=1000

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

RUN groupadd -g ${uid} ${user} || true \
    && useradd -u ${uid} -g ${user} -m ${user} || true \
    && chown -R ${user}:${user} /var/www

USER ${user}

COPY . /var/www/html

RUN if [ -f composer.json ]; then composer install --no-interaction --prefer-dist --no-scripts; fi

EXPOSE 9000

CMD ["php-fpm"]


