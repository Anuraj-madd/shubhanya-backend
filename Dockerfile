# Use the official PHP image with Apache
FROM php:8.1-apache

# Enable mod_rewrite (optional but useful)
RUN a2enmod rewrite

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy your PHP files into the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Set proper permissions (optional)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
