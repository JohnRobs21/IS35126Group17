FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (pdo is built-in, don't install separately)
RUN docker-php-ext-install pdo_mysql mysqli zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80