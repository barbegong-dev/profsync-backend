FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libsqlite3-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip pdo_sqlite

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Configure Apache DocumentRoot to point to /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/conf-available/*.conf

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application code
COPY . /var/www/html

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN touch /var/www/html/database/database.sqlite
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Render sends traffic to $PORT (default 10000); fall back to 80 locally.
EXPOSE 80

# On start: point Apache at $PORT, make sure the SQLite file exists (DB_DATABASE may
# point at a Render persistent disk), link public storage, migrate, seed, then serve.
CMD bash -c '  PORT="${PORT:-80}";   sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf;   sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf;   DB="${DB_DATABASE:-/var/www/html/database/database.sqlite}";   mkdir -p "$(dirname "$DB")" && touch "$DB" && chown -R www-data:www-data "$(dirname "$DB")";   php artisan storage:link || true;   php artisan config:cache &&   php artisan migrate --force --seed &&   apache2-foreground'
