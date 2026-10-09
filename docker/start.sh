#!/bin/sh
# Container entrypoint. The filesystem may be ephemeral (Render's free tier
# resets it on every restart), so the SQLite database is created and migrated
# on each boot. Demo accounts are throwaway anyway.
set -e
cd /var/www/html

# Render's generateValue is a base64 256-bit value without Laravel's "base64:"
# prefix. Add it, and fall back to a fresh key if the value isn't usable.
case "$APP_KEY" in
    base64:*) ;;
    "") ;;
    *) APP_KEY="base64:$APP_KEY" ;;
esac
if ! php -r 'exit(strlen((string) base64_decode(substr((string) getenv("APP_KEY"), 7), true)) === 32 ? 0 : 1);'; then
    echo "APP_KEY missing or invalid; generating one for this boot (sessions reset on restart)."
    APP_KEY="$(php artisan key:generate --show)"
fi
export APP_KEY

export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:$PORT}}"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch "$DB_DATABASE"
php artisan migrate --force
php artisan optimize

cd public
exec php -S "0.0.0.0:$PORT" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
