#!/bin/bash

# Skrypt do synchronizacji vendor z kontenera Docker do lokalnego systemu
# Użyj: ./docker-sync-vendor.sh

set -e

echo "🔄 Synchronizacja katalogu vendor z kontenera Docker..."

# Sprawdź czy kontener działa
if ! docker ps | grep -q "zus_symulator_app_dev"; then
    echo "❌ Kontener zus_symulator_app_dev nie jest uruchomiony!"
    echo "Uruchom najpierw: docker-compose -f docker-compose.dev.yml up -d"
    exit 1
fi

# Utwórz katalog vendor jeśli nie istnieje
mkdir -p vendor

# Skopiuj vendor z kontenera
echo "📦 Kopiowanie plików vendor z kontenera..."
docker cp zus_symulator_app_dev:/var/www/html/vendor/. ./vendor/

echo "✅ Synchronizacja zakończona pomyślnie!"
echo "💡 Twoje IDE powinno teraz widzieć wszystkie paczki Composer"

