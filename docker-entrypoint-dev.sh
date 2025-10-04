#!/bin/bash

echo '🔧 Konfiguracja Git safe.directory...'
git config --global --add safe.directory /var/www/html 2>/dev/null || echo '   ⚠️ Nie można skonfigurować Git (może brak uprawnień)'

# Ustawienie uprawnień dla katalogów
echo '🔐 Ustawianie uprawnień...'
chmod -R 755 /var/www/html/storage 2>/dev/null || true
chmod -R 755 /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 755 /var/www/html/database 2>/dev/null || true

# Sprawdź czy możemy zapisywać w node_modules
if [ -d "node_modules" ]; then
    echo '🔧 Sprawdzanie uprawnień node_modules...'
    if ! touch node_modules/.test_write 2>/dev/null; then
        echo '   ⚠️ Brak uprawnień do zapisu w node_modules - usuwanie...'
        sudo rm -rf node_modules package-lock.json 2>/dev/null || rm -rf node_modules package-lock.json 2>/dev/null || true
    else
        rm -f node_modules/.test_write 2>/dev/null || true
    fi
fi

# Sprawdź czy możemy zapisywać w vendor
if [ -d "vendor" ]; then
    echo '🔧 Sprawdzanie uprawnień vendor...'
    if ! touch vendor/.test_write 2>/dev/null; then
        echo '   ⚠️ Brak uprawnień do zapisu w vendor - usuwanie...'
        sudo rm -rf vendor composer.lock 2>/dev/null || rm -rf vendor composer.lock 2>/dev/null || true
    else
        rm -f vendor/.test_write 2>/dev/null || true
    fi
fi

# Włącz set -e po potencjalnie problematycznych operacjach
set -e

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
    npm install --prefer-offline --no-audit
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

# Wyłącz set -e tymczasowo, aby obsłużyć błędy composer
set +e

if [ ! -d "vendor" ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
    echo '   Katalog vendor pusty lub nie istnieje - pełna instalacja...'
    composer install --no-interaction --prefer-dist --optimize-autoloader
    COMPOSER_EXIT_CODE=$?
else
    echo '   Sprawdzanie i aktualizacja zależności...'
    # Próba instalacji i zapisanie wyjścia
    COMPOSER_OUTPUT=$(composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1)
    COMPOSER_EXIT_CODE=$?
    
    echo "$COMPOSER_OUTPUT"
    
    # Jeśli composer.lock jest nieaktualny (exit code 4), zaktualizuj go
    if [ $COMPOSER_EXIT_CODE -eq 4 ]; then
        if echo "$COMPOSER_OUTPUT" | grep -q "is not present in the lock file"; then
            echo '   ⚠️ Composer.lock nieaktualny - aktualizacja...'
            composer update --no-interaction --prefer-dist --optimize-autoloader
            COMPOSER_EXIT_CODE=$?
        fi
    fi
fi

# Włącz z powrotem set -e
set -e

# Sprawdź czy composer zakończył się sukcesem
if [ $COMPOSER_EXIT_CODE -ne 0 ]; then
    echo '   ❌ Błąd instalacji Composer!'
    exit $COMPOSER_EXIT_CODE
fi

composer run-script post-autoload-dump

# Generowanie klucza aplikacji
echo '🔑 Generowanie klucza aplikacji...'
php artisan key:generate --force

# Konfiguracja bazy danych
echo '🗄️ Tworzenie bazy danych...'
mkdir -p database
touch database/database.sqlite
chmod 664 database/database.sqlite 2>/dev/null || true

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

