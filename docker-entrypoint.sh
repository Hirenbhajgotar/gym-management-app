#!/bin/bash

# Ensure storage permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Create the storage link if it doesn't exist
php artisan storage:link --quiet || true

# Warm the caches that are expensive to build per request.
# config:cache and route:cache are intentionally NOT used: tests run in this
# same container and need the forced phpunit.xml config, and the routes use closures.
php artisan filament:optimize || true
php artisan config:clear || true

# Start PHP-FPM (or your application)
exec php-fpm