FROM php:7.1-apache

RUN apt-get update && \
    apt-get install -y \ 
    vim \
    default-mysql-client  \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN  docker-php-ext-install pdo_mysql gd mysqli && \
     docker-php-ext-enable pdo_mysql gd mysqli && \
     docker-php-source delete

COPY . /var/www/html
RUN mv /var/www/html/vhost.conf /etc/apache2/sites-enabled/000-default.conf && \
    mkdir -p /var/www/html/application/files/ && \
    chown -R www-data:www-data /var/www/html && \
    a2enmod rewrite

