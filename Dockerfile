# Use the official PHP image
FROM php:8.4-apache

# Install necessary PHP extensions
#RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN apt-get -y update
RUN apt-get -y upgrade

RUN apt-get install -y curl
RUN apt-get install -y ffmpeg

RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
 && chmod a+rx /usr/local/bin/yt-dlp

# Enable Apache
RUN a2enmod rewrite

# Set the working directory
WORKDIR /var/www/html

# Copy the project code into the container
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
