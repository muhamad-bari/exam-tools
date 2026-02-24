FROM serversideup/php:8.3-fpm-nginx

# Install ekstensi PHP yang dibutuhkan
USER root
RUN install-php-extensions gd pdo_sqlite iconv intl

# Copy source code
COPY --chown=www-data:www-data . /var/www/html

# Install Composer dependencies
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Pastikan folder writable untuk uploads, results, dan sqlite
RUN mkdir -p /var/www/html/uploads /var/www/html/results /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/results /var/www/html/data
