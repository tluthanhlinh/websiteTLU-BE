# Base image for PHP applications
FROM php:8.2-apache 

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy your application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port 80 for Apache
EXPOSE 80

# Start Apache server (default for php:apache images)
CMD ["apache2-foreground"]