# Use the official PHP image with Apache
FROM php:8.1-apache

# Enable Apache rewrite (optional but useful for routing)
RUN a2enmod rewrite

# Install MySQL extensions (both mysqli and pdo_mysql)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your PHP files into the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Set proper permissions (optional)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
