# -*- coding: utf-8 -*-
# Pristine upstream Krayin CRM (no customizations yet) - Dockerfile
# Two-stage build: node stage builds assets, PHP stage serves them.
FROM node:20-bookworm AS node-builder
WORKDIR /var/www/html
ENV NODE_ENV=development
RUN apt-get update && apt-get install -y --no-install-recommends git ca-certificates \
    && rm -rf /var/lib/apt/lists/*
COPY composer.json composer.lock ./
COPY package.json ./
COPY package-lock.json ./
RUN npm install --no-audit --include=dev --loglevel=error

# Copy the rest of the app source
COPY artisan ./
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY lang/ ./lang/
COPY packages/ ./packages/
COPY resources/ ./resources/
COPY routes/ ./routes/
COPY storage/ ./storage/
COPY tests/ ./tests/
COPY app/ ./app/
COPY public/ ./public/

# Minimal index.html for Laravel Vite plugin entry resolution
RUN echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body></body></html>' > index.html

# Build frontend assets
RUN npm run build --no-audit --loglevel=error

# Normalize Vite output (dist/) to the expected public/build/ location
RUN mkdir -p public/build && cp -r dist/* public/build/ 2>/dev/null; true

FROM php:8.3-fpm-bookworm AS php-builder
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates git libicu-dev zlib1g-dev libfreetype6-dev libjpeg-dev libwebp-dev libonig-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/*
RUN apt-get update && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) calendar intl pdo_mysql mbstring zip gd
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=node-builder /var/www/html/public/build /var/www/html/public/build

COPY composer.json composer.lock ./
COPY artisan ./
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY lang/ ./lang/
COPY packages/ ./packages/
COPY resources/ ./resources/
COPY routes/ ./routes/
COPY storage/ ./storage/
COPY tests/ ./tests/
COPY app/ ./app/
COPY public/ ./public/

RUN composer install --no-interaction --no-dev --optimize-autoloader
RUN php artisan package:discover --ansi

RUN chown -R www-data:www-data /var/www/html/storage
RUN chown -R www-data:www-data /var/www/html/bootstrap/cache

COPY nginx.conf /etc/nginx/nginx.conf
EXPOSE 80
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
