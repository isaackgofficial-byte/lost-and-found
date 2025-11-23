# Use official PHP image with Apache
FROM php:8.2-apache

# Install mysqli extension for MySQL
RUN docker-php-ext-install mysqli

# Enable Apache rewrite module (optional, useful for .htaccess)
RUN a2enmod rewrite

# Copy all project files into container
COPY . /var/www/html/

# Set proper permissions for project root and uploads folder
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 755 /var/www/html/uploads

# Expose port 10000 (Render maps it to public URL)
EXPOSE 10000

# Start Apache in foreground
CMD ["apache2-foreground"]
