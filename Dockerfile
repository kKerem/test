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

# Startup script'i çalıştırılabilir yap
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Railway PORT değişkenini kullan (varsayılan 8000)
ENV PORT=8000

EXPOSE 8000

# Railway'in PORT değişkenini kullanarak PHP built-in server başlat
# Railway otomatik olarak PORT değişkenini set eder
CMD ["/start.sh"]


