FROM php:8.2-cli

# Sistem paketleri ve sık gereken PHP eklentileri
RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql

WORKDIR /app

# Önce sadece composer dosyalarını kopyala (build cache kazanımı için)
COPY composer.json composer.lock /app/

# composer'ı kur ve izinleri ayarla
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Bellek sorunu çıkarsa COMPOSER_MEMORY_LIMIT kullanabilirsin
RUN composer install --no-interaction --prefer-dist --no-scripts

# Sonra tüm projeyi kopyala
COPY . /app

# (opsiyonel) prod için vendor'ı önceden yükleyip --no-dev kullan
