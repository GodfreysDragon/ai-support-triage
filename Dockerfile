# Production image for the demo: PHP's built-in server with several workers,
# SQLite, the fake AI driver and an in-process queue. See render.yaml.

# ---- Build: PHP dependencies, then the frontend. PHP is needed here too,
# because the Wayfinder Vite plugin runs `php artisan wayfinder:generate`.
FROM php:8.4-cli-bookworm AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /app
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && npm run build \
    && rm -rf node_modules

# ---- Runtime
FROM php:8.4-cli-bookworm

# pdo_sqlite, mbstring and the other extensions Laravel needs ship with this image.
RUN docker-php-ext-install opcache
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app /var/www/html
RUN chmod +x docker/start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    AI_DRIVER=fake \
    DEMO_ENABLED=true \
    PHP_CLI_SERVER_WORKERS=4 \
    PORT=10000

USER www-data
EXPOSE 10000
CMD ["docker/start.sh"]
