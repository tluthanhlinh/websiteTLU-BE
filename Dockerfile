# Base image for PHP applications
FROM php:8.2-apache 

# Install system dependencies for PostgreSQL PHP extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy your application files
COPY . .

# Copy Apache configuration
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Enable Apache rewrite module if needed (for clean URLs)
RUN a2ensite 000-default.conf && a2enmod rewrite

# Expose port 80 for Apache
EXPOSE 80

# Start Apache server (default for php:apache images)
CMD ["apache2-foreground"]