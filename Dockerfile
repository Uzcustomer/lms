FROM php:8.2-fpm

USER root

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    build-essential \
    openssl \
    nginx \
    supervisor \
    cron \
    procps\
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    zlib1g-dev \
    libzip-dev \
    gcc \
    g++ \
    make \
    vim \
    nano \
    unzip \
    curl \
    git \
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    locales \
    libonig-dev \
    nodejs \
    imagemagick \
    libgmp-dev \
    libicu-dev \
    sudo \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mbstring exif pcntl bcmath gmp intl zip \
    && apt-get autoclean -y \
    && rm -rf /var/lib/apt/lists/* /tmp/pear/

RUN docker-php-ext-enable opcache sockets || true
RUN git config --global --add safe.directory /var/www/html
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && sed -ri -e 's!upload_max_filesize = 2M!upload_max_filesize = 100M!g' /usr/local/etc/php/php.ini \
    && sed -ri -e 's!post_max_size = 8M!post_max_size = 100M!g' /usr/local/etc/php/php.ini

RUN apt-get update && apt-get install -y \
    nodejs \
    npm \
    && npm install -g npm@9.2.0

RUN ln -snf /usr/share/zoneinfo/Asia/Tashkent /etc/localtime && echo "Asia/Tashkent" > /etc/timezone

RUN echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron
RUN crontab /etc/cron.d/laravel-cron

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh


COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN chown -R $USER:www-data storage && \
    chown -R $USER:www-data bootstrap/cache && \
    chmod -R 775 storage && \
    chmod -R 775 bootstrap/cache

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
#CMD ["php-fpm"]
