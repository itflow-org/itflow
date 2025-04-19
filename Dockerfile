# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies
RUN apt-get update && apt-get install -y \
    mariadb-client \
    git \
    unzip \
    curl \
    supervisor \
    whois \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libmcrypt-dev \
    libc-client-dev \
    libkrb5-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install \
        gd \
        zip \
        mysqli \
        pdo \
        pdo_mysql \
        intl \
        mbstring \
        xml \
        opcache \
        imap

# Install PECL extensions
RUN pecl install mailparse \
    && docker-php-ext-enable mailparse

# Enable Apache modules
RUN a2enmod rewrite ssl

# Set up ITFlow
WORKDIR /var/www/html
RUN rm -rf * && git clone https://github.com/Qwerty-Systems/itflow.git .

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html 

# Update PHP configuration
RUN { \
    echo "upload_max_filesize = 500M"; \
    echo "post_max_size = 500M"; \
    echo "memory_limit = 512M"; \
    echo "max_execution_time = 300"; \
} > /usr/local/etc/php/conf.d/uploads.ini

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Expose ports
EXPOSE 80 443

# Start Apache
CMD ["apache2-foreground"]
