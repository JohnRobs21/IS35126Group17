FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install zip extension
RUN apt-get update && apt-get install -y \
    libzip-dev unzip git \
    && docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Enable Apache mod_rewrite for .htaccess
RUN a2enmod rewrite

# Set Apache to allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80