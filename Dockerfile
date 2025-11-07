# Use the official PHP image
FROM php:8.4-apache

# Install necessary PHP extensions
RUN docker-php-ext-install gd curl zip

RUN apt-get -y update
RUN apt-get -y upgrade

RUN apt-get install -y curl
RUN apt-get install -y ffmpeg
RUN apt-get install -y git unzip

# Install yt-dlp
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
 && chmod a+rx /usr/local/bin/yt-dlp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache
RUN a2enmod rewrite

# Set the working directory
WORKDIR /var/www/html

# Copy the project code into the container
COPY . /var/www/html

# Install Composer dependencies
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html

# Configure Apache to use the public directory as DocumentRoot and listen on port 8080
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/apache2.conf && \
    sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf && \
    sed -i 's/:80>/:8080>/g' /etc/apache2/sites-available/000-default.conf

# Set proper permissions for tmp directory
RUN mkdir -p /var/www/html/public/tmp && \
    chown -R www-data:www-data /var/www/html/public/tmp

EXPOSE 8080
