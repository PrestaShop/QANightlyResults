FROM php:8.3-apache as builder

RUN apt-get update && \
    apt-get install -y \
    git \
    zip

WORKDIR "/var/www/html/"

RUN curl --insecure https://getcomposer.org/composer.phar -o /usr/bin/composer && chmod +x /usr/bin/composer

COPY . /var/www/html

RUN composer update && \
    composer install


FROM php:8.3-apache

RUN apt-get update && \
    apt-get install -y \ 
    vim \
    default-mysql-client  \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev && \
    openssl && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN  docker-php-ext-install pdo_mysql gd mysqli && \
     docker-php-ext-enable pdo_mysql gd mysqli && \
     docker-php-source delete

COPY --from=0 /var/www/html/ /var/www/html/
RUN mv /var/www/html/vhost.conf /etc/apache2/sites-enabled/000-default.conf && \
    mkdir -p /var/www/html/application/files/ && \
    chown -R www-data:www-data /var/www/html && \
    a2enmod rewrite && \
    a2enmod ssl && \
    a2enmod proxy_http && \
    a2enmod headers

RUN sed -i 's/^max_execution_time = .*/max_execution_time = 300/'  /usr/local/etc/php/php.ini-production && \ 
    sed -i 's/variables_order = "GPCS"/variables_order = "EGPCS"/' /usr/local/etc/php/php.ini-production && \ 
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
