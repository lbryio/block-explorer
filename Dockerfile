FROM php:7.4-fpm

ENV PORT 80

COPY composer.lock composer.json /var/www/

WORKDIR /var/www

RUN apt-get update && apt-get install -y curl libxml2-dev libpng-dev libonig-dev zip unzip && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www
RUN chown -R www-data:www-data /var/www

RUN php ./artisan optimize

EXPOSE 9000
ENTRYPOINT ["./entrypoint.sh"]
