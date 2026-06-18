# Be-SIA-UGN-Kel1 — image produksi untuk CapRover (Laravel 12 + Apache + PHP 8.3)
FROM php:8.3-apache

# --- System packages + PHP extensions yang dibutuhkan Laravel ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libonig-dev libxml2-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath gd zip exif pcntl \
    && rm -rf /var/lib/apt/lists/*

# --- Apache: arahkan docroot ke /public + aktifkan mod_rewrite ---
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

WORKDIR /var/www/html

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Copy aplikasi + install dependency (tanpa dev) ---
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

# Entrypoint: migrasi DB + (opsional) seed, lalu jalankan Apache
CMD ["sh", "docker/entrypoint.sh"]
