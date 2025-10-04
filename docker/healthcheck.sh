#!/bin/sh

# Healthcheck script for Docker container
# Sprawdza czy aplikacja odpowiada na żądania HTTP

set -e

# Sprawdź czy Nginx działa
if ! pgrep nginx > /dev/null 2>&1; then
    echo "Nginx nie działa"
    exit 1
fi

# Sprawdź czy PHP-FPM działa
if ! pgrep php-fpm > /dev/null 2>&1; then
    echo "PHP-FPM nie działa"
    exit 1
fi

# Sprawdź odpowiedź HTTP
if ! wget --no-verbose --tries=1 --spider http://localhost:80/api/health 2>/dev/null; then
    # Jeśli endpoint /api/health nie istnieje, sprawdź główną stronę
    if ! wget --no-verbose --tries=1 --spider http://localhost:80 2>/dev/null; then
        echo "Aplikacja nie odpowiada"
        exit 1
    fi
fi

echo "Healthcheck OK"
exit 0


