# Use the official PHP image
FROM php:8.4-apache

# Install system dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    ffmpeg \
    git \
    unzip \
    python3 \
    python3-pip \
 && docker-php-ext-install gd zip curl \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install yt-dlp (requires Python 3)
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
 && chmod a+rx /usr/local/bin/yt-dlp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set Apache to serve from /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Adjust Apache to listen on port 8080 for DigitalOcean
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Set the working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Set proper permissions
RUN chmod -R 777 /var/www/html/public/tmp

# Create yt-dlp cache directory and set permissions
RUN mkdir -p /var/www/.cache/yt-dlp && chmod -R 777 /var/www/.cache

EXPOSE 8080
