FROM php:8.3-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends nginx supervisor libpq-dev libzip-dev git unzip ca-certificates \
 && docker-php-ext-install pdo_pgsql pgsql bcmath zip opcache \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/mt2.conf
COPY docker/start.sh /usr/local/bin/mt2-start
RUN chmod +x /usr/local/bin/mt2-start && mkdir -p /run/php /var/log/supervisor
COPY . /var/www/html
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader \
    --no-scripts \
 && mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 10000
CMD ["/usr/local/bin/mt2-start"]
