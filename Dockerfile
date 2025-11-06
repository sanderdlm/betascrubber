# Use the official PHP image
FROM php:8.2-apache

# Install necessary PHP extensions
#RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN apt-get -y update
RUN apt-get -y upgrade

RUN apt-get install -y curl
RUN apt-get install -y ffmpeg

RUN sudo curl -L https://yt-dl.org/downloads/latest/youtube-dl -o /usr/local/bin/youtube-dl
RUN sudo chmod a+rx /usr/local/bin/youtube-dl \

# Enable Apache
RUN a2enmod rewrite

# Set the working directory
WORKDIR /var/www/html

# Copy the project code into the container
COPY . /var/www/html