# Use the official PHP image with Apache
FROM php:8.1-apache

# Enable mod_rewrite (required for many frameworks)
RUN a2enmod rewrite

# Copy your PHP files into the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80
