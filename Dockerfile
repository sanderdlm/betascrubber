# =========================
# 1️⃣ Build Stage — Composer
# =========================
FROM composer:2 AS builder

WORKDIR /app

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install production dependencies only
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy app source and rebuild optimized autoloader
COPY . .
RUN composer dump-autoload --optimize


# =========================
# 2️⃣ Production Stage — PHP 8.4 + Apache (Alpine)
# =========================
FROM php:8.4-apache-alpine

# Install Alpine dependencies for PHP extensions & tools
RUN apk add --no-cache \
    ffmpeg \
    git \
    unzip \
    curl \
    libpng-dev \
    libzip-dev \
    libcurl \
    oniguruma-dev \
    icu-dev \
    bash \
 && docker-php-ext-install gd zip curl intl \
 && rm -rf /var/cache/apk/*

# Install yt-dlp (static binary)
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
 && chmod a+rx /usr/local/bin/yt-dlp

# Enable Apache rewrite module
RUN sed -i '/LoadModule rewrite_module/s/^#//g' /usr/local/apache2/conf/httpd.conf

# Set Apache DocumentRoot to /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i "s#DocumentRoot \"/var/www/html\"#DocumentRoot \"/var/www/html/public\"#g" /usr/local/apache2/conf/httpd.conf \
 && printf '\n<Directory "/var/www/html/public">\n    AllowOverride All\n</Directory>\n' >> /usr/local/apache2/conf/httpd.conf

# Change Apache listen port to 8080 (required by DigitalOcean)
RUN sed -i 's/Listen 80/Listen 8080/' /usr/local/apache2/conf/httpd.conf

# Set working directory
WORKDIR /var/www/html

# Copy the built app from the builder stage
COPY --from=builder /app ./

EXPOSE 8080

# Default Apache CMD inherited from base image
