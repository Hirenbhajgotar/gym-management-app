@echo off
docker-compose exec -e DB_DATABASE=gym_app_test -e DB_HOST=db -e APP_ENV=testing app php artisan test %*
