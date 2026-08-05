FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    libpng-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libsqlite3-dev \
    libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring xml zip intl

RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

COPY docker/php.ini /usr/local/etc/php/conf.d/99-order-import.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

COPY . /var/www/html
RUN composer install --no-interaction --no-scripts --prefer-dist && rm -rf /root/.composer/cache

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
