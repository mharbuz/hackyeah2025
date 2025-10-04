#!/bin/bash
set -e

echo '🔧 Konfiguracja Git safe.directory...'
git config --global --add safe.directory /var/www/html

# Tworzenie .env jeśli nie istnieje
if [ ! -f .env ]; then
    echo '📝 Tworzenie pliku .env...'
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        cat > .env << 'EOF'
APP_NAME=ZUS_Symulator
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Europe/Warsaw
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="ZUS Symulator"
EOF
    fi
fi

# Instalacja zależności NPM
echo '📦 Instalacja zależności NPM...'
if [ ! -d "node_modules" ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
    echo '   Katalog node_modules pusty lub nie istnieje - pełna instalacja...'
    npm ci --prefer-offline --no-audit || npm install --prefer-offline --no-audit
else
    echo '   Sprawdzanie i aktualizacja zależności...'
    npm install --prefer-offline --no-audit
fi

# Sprawdzenie czy vite został zainstalowany
if [ ! -f "node_modules/.bin/vite" ]; then
    echo '❌ Vite nie został zainstalowany! Próba ponownej instalacji...'
    rm -rf node_modules package-lock.json
    npm install --prefer-offline --no-audit
fi

echo '✅ NPM zainstalowane!'
ls -la node_modules/.bin/ | grep vite || echo '⚠️ Ostrzeżenie: vite nie znaleziony w node_modules/.bin/'

# Instalacja zależności Composer
echo '📦 Instalacja zależności Composer...'
if [ ! -d "vendor" ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
    echo '   Katalog vendor pusty lub nie istnieje - pełna instalacja...'
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo '   Sprawdzanie i aktualizacja zależności...'
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi
composer run-script post-autoload-dump

# Generowanie klucza aplikacji
echo '🔑 Generowanie klucza aplikacji...'
php artisan key:generate --force

# Konfiguracja bazy danych
echo '🗄️ Tworzenie bazy danych...'
mkdir -p database
touch database/database.sqlite
chmod 775 database/database.sqlite

# Migracje
echo '📊 Uruchamianie migracji...'
php artisan migrate --force

echo '✅ Konfiguracja zakończona!'

# Uruchomienie serwera PHP w tle
echo '🚀 Uruchamianie serwera PHP...'
php artisan serve --host=0.0.0.0 --port=8000 &

# Uruchomienie Vite (na pierwszym planie)
echo '🎨 Uruchamianie Vite...'
exec npx vite --host 0.0.0.0 --port 5173

