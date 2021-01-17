#!/usr/bin/env sh
php artisan config:cache
php artisan view:cache
php-fpm
