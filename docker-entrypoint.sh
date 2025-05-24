#!/bin/bash

# Ensure storage permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Create the storage link if it doesn't exist
php artisan storage:link --quiet || true

# Clear caches (optional, for safety)
php artisan config:clear
php artisan cache:clear

# Start PHP-FPM (or your application)
exec php-fpm