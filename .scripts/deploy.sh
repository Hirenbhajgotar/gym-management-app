#!/bin/bash
set -e

echo "Deployment started ..."

# Turn ON Maintenance Mode or return true
# if already is in maintenance mode
(php artisan down) || true

# Pull the latest version of the app
echo "Pulling latest version of the app..."
git pull origin main

# Install composer dependencies
echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install npm dependencies
echo "Installing npm dependencies..."
npm install
npm run build

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Running database seeder for roles and permissions
echo "Running database seeder for roles and permissions..."
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=PermissionDescriptionSeeder

# Restart queue workers after deployment
echo "Restarting queue workers..."
# sudo supervisorctl restart laravel-worker:* || true
php artisan queue:restart

# Clearing Cache
echo "Clearing Cache..."
php artisan optimize:clear
php artisan filament:clear

# Recreate cache
echo "Recreating cache..."
php artisan optimize

# Set proper permissions
echo "🔒 Setting permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Restart PHP-FPM (adjust version if needed)
echo "🔄 Restarting PHP-FPM..."
sudo systemctl restart php8.3-fpm

# Turn OFF Maintenance mode
echo "Turning OFF Maintenance mode..."
php artisan up

echo "Deployment finished!"
