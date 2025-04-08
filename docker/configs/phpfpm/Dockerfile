FROM php:8.4-fpm

USER root

ADD .bashrc /var/www/.bashrc

ADD www.conf /usr/local/etc/php-fpm.d/www.conf
ADD zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
ADD php.ini /usr/local/etc/php/php.ini

ENV TZ='Europe/Rome'

#permissions
RUN chown -R www-data:www-data /var/www \
    && chown www-data:www-data /usr/local/etc/php-fpm.d/www.conf \
    && chown -R www-data:www-data /usr/local/etc/php-fpm.d/zz-docker.conf

#apt packages
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libpng-dev \
    zlib1g-dev \
    libicu-dev \
    g++ \
    imagemagick \
    libmagickwand-dev \
    libmagickcore-dev \
    git \
    unzip \
    vim \
    libzip-dev \
    libwebp-dev \
    libpq-dev \
    osmium-tool \
    osm2pgsql \
    build-essential \
    cmake \
    libboost-dev \
    libboost-program-options-dev \
    libboost-filesystem-dev \
    libboost-regex-dev \
    libexpat1-dev \
    zlib1g-dev \
    libbz2-dev \
    liblz4-dev \
    libprotobuf-dev \
    protobuf-compiler \
    libosmium2-dev \
    libprotozero-dev \
    supervisor \
    gdal-bin \
    zip \
    nodejs \
    npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

#php extensions
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
    gd \
    intl \
    zip \
    pdo \
    pdo_mysql \
    opcache \
    pgsql \
    pdo_pgsql \
    exif \
    && docker-php-ext-install pcntl \
    && pecl install redis \
    && docker-php-ext-enable exif

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && if [ ! -f /usr/bin/composer ]; then ln -s /usr/local/bin/composer /usr/bin/composer; fi

# Verifica la versione di PHP
RUN php -v

ENV HOME /var/www
WORKDIR /var/www

USER www-data
