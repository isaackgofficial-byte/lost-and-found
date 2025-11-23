# Use official PHP image with Apache
FROM php:8.2-apache

# Enable mysqli extension
RUN docker-php-ext-install mysqli

# Copy project files to container
COPY . /var/www/html/

# Set proper permissions for uploads folder
RUN chown -R www-data:www-data /var/www/html/uploads

# Expose port 10000 (Render will handle routing)
EXPOSE 10000
