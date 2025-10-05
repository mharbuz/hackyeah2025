# Etap budowania frontendu
FROM node:22-alpine AS node-builder

WORKDIR /app

# Kopiuj pliki package
COPY package*.json ./

# Instaluj zależności Node.js
RUN npm install

# Kopiuj pliki źródłowe
COPY . .

# Buduj aplikację Vue
ENV NODE_ENV=production
RUN npm run build


# Etap produkcyjny PHP
FROM php:8.2-fpm-alpine

# Instaluj zależności systemowe
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite \
    sqlite-dev \
    oniguruma-dev \
    nginx \
    supervisor

# Instaluj rozszerzenia PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_sqlite \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Instaluj Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ustaw katalog roboczy
WORKDIR /var/www/html

# Kopiuj pliki aplikacji
COPY --chown=www-data:www-data . .

# Kopiuj zbudowane assety z etapu node-builder
COPY --from=node-builder --chown=www-data:www-data /app/public/build ./public/build

# Instaluj zależności Composer (produkcja)
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Ustaw uprawnienia
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Konfiguracja Nginx
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Konfiguracja PHP-FPM
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Konfiguracja Supervisor
RUN mkdir -p /var/log/supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Ustaw uprawnienia dla bazy SQLite
RUN mkdir -p /var/www/html/database \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/database \
    && chmod -R 775 /var/www/html/database

# Skopiuj healthcheck script
COPY --chmod=755 docker/healthcheck.sh /usr/local/bin/healthcheck.sh

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh || exit 1

# Eksponuj port
EXPOSE 80

# Uruchom supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

