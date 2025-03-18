FROM php:8.0-apache
WORKDIR /var/www/html

# Install required dependencies
RUN apt-get update && \
    apt-get install -y \
        libzip-dev \
        unzip \
        sqlite3 \
        libsqlite3-dev && \
    docker-php-ext-install zip pdo pdo_sqlite

# Enable mod_rewrite for Apache
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html

# Install Composer and dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Set proper permissions
RUN chmod -R 777 /var/www/html/database.sqlite /var/www/html/uploads

# Expose port 80 and start Apache
EXPOSE 80
CMD ["apache2-foreground"]

