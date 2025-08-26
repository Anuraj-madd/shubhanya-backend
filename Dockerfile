# Use the official PHP image with Apache
FROM php:8.1-apache

# Enable Apache rewrite
RUN a2enmod rewrite

# Install mysqli and PDO MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Copy app files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Permissions (optional)
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80
