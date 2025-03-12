
FROM php:8.0-apache
WORKDIR /var/www/html
COPY . /var/www/html
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip pdo pdo_sqlite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader
RUN chmod -R 777 /var/www/html/database.sqlite /var/www/html/uploads
EXPOSE 80
CMD ["apache2-foreground"]
