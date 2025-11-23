# Use official PHP image with Apache
FROM php:8.2-apache

# Install mysqli extension for MySQL
RUN docker-php-ext-install mysqli

# Enable Apache rewrite module (optional, useful if you use .htaccess)
RUN a2enmod rewrite

# Copy all project files into the container
COPY . /var/www/html/

# Set proper permissions for uploads folder
RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

# Expose port 10000 (Render handles the actual routing)
EXPOSE 10000

# Start Apache in foreground
CMD ["apache2-foreground"]

