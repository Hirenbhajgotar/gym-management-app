#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo 'Clearing compiled Blade views...'
docker compose exec -T app php artisan view:clear

echo 'Rebuilding Filament component cache...'
docker compose exec -T app php artisan filament:optimize

echo 'Restarting app container to reset OPcache...'
docker compose restart app

echo 'Waiting for PHP-FPM to become ready...'
for _ in $(seq 1 60); do
    if curl -fsS -o /dev/null http://localhost:8000/login 2>/dev/null; then
        echo 'Done. PHP and Blade changes are now visible.'
        exit 0
    fi
    sleep 2
done

echo 'Timed out waiting for PHP-FPM.' >&2
exit 1
