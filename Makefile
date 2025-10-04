.PHONY: help dev prod up down build logs shell artisan composer npm clean migrate fresh test

# Domyślna komenda
help:
	@echo "🐳 Docker commands dla ZUS Symulator"
	@echo ""
	@echo "Tryb deweloperski:"
	@echo "  make dev          - Uruchom w trybie deweloperskim"
	@echo "  make dev-down     - Zatrzymaj tryb deweloperski"
	@echo "  make dev-logs     - Pokaż logi (dev)"
	@echo ""
	@echo "Tryb produkcyjny:"
	@echo "  make prod         - Uruchom w trybie produkcyjnym"
	@echo "  make prod-down    - Zatrzymaj tryb produkcyjny"
	@echo "  make prod-logs    - Pokaż logi (prod)"
	@echo ""
	@echo "Zarządzanie:"
	@echo "  make build        - Przebuduj kontenery"
	@echo "  make shell        - Wejdź do kontenera (bash/sh)"
	@echo "  make logs         - Pokaż wszystkie logi"
	@echo "  make clean        - Wyczyść kontenery i obrazy"
	@echo ""
	@echo "Laravel:"
	@echo "  make migrate      - Uruchom migracje"
	@echo "  make fresh        - Odśwież bazę (migrate:fresh)"
	@echo "  make artisan CMD='...' - Uruchom komendę artisan"
	@echo "  make test         - Uruchom testy"
	@echo ""
	@echo "Composer & NPM:"
	@echo "  make composer CMD='...' - Uruchom komendę composer"
	@echo "  make npm CMD='...'      - Uruchom komendę npm"
	@echo "  make sync-vendor        - Synchronizuj vendor z kontenera do lokalnego systemu"

# Tryb deweloperski
dev:
	@echo "🚀 Uruchamiam w trybie deweloperskim..."
	docker compose -f docker-compose.dev.yml up --build

dev-d:
	@echo "🚀 Uruchamiam w trybie deweloperskim (w tle)..."
	docker compose -f docker-compose.dev.yml up -d --build

dev-down:
	@echo "🛑 Zatrzymuję tryb deweloperski..."
	docker compose -f docker-compose.dev.yml down

dev-logs:
	docker compose -f docker-compose.dev.yml logs -f

# Tryb produkcyjny
prod:
	@echo "🚀 Uruchamiam w trybie produkcyjnym..."
	docker compose up --build -d
	@echo "⏳ Czekam na uruchomienie..."
	@sleep 5
	@echo "🔧 Uruchamiam migracje..."
	docker compose exec app php artisan migrate --force
	@echo "✅ Gotowe! Aplikacja dostępna na http://localhost:8000"

prod-down:
	@echo "🛑 Zatrzymuję tryb produkcyjny..."
	docker compose down

prod-logs:
	docker compose logs -f

# Budowanie
build:
	@echo "🔨 Przebudowuję kontenery..."
	docker compose build --no-cache

build-dev:
	@echo "🔨 Przebudowuję kontenery deweloperskie..."
	docker compose -f docker-compose.dev.yml build --no-cache

# Shell
shell:
	@echo "🐚 Wchodzę do kontenera..."
	@docker compose exec app sh 2>/dev/null || docker compose -f docker-compose.dev.yml exec app bash

# Logi
logs:
	docker compose logs -f

# Artisan
artisan:
	@if [ -z "$(CMD)" ]; then \
		echo "❌ Użycie: make artisan CMD='migrate'"; \
		exit 1; \
	fi
	@docker compose exec app php artisan $(CMD) 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan $(CMD)

migrate:
	@echo "🗄️  Uruchamiam migracje..."
	@docker compose exec app php artisan migrate --force 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan migrate --force

fresh:
	@echo "🗄️  Odświeżam bazę danych..."
	@docker compose exec app php artisan migrate:fresh --seed --force 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed --force

seed:
	@echo "🌱 Seeduję bazę danych..."
	@docker compose exec app php artisan db:seed --force 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan db:seed --force

# Cache
cache-clear:
	@echo "🧹 Czyszczę cache..."
	@docker compose exec app php artisan cache:clear 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan cache:clear
	@docker compose exec app php artisan config:clear 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan config:clear
	@docker compose exec app php artisan route:clear 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan route:clear

# Composer
composer:
	@if [ -z "$(CMD)" ]; then \
		echo "❌ Użycie: make composer CMD='install'"; \
		exit 1; \
	fi
	@docker compose exec app composer $(CMD) 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app composer $(CMD)

composer-install:
	@echo "📦 Instaluję zależności Composer..."
	@docker compose exec app composer install 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app composer install

composer-update:
	@echo "📦 Aktualizuję zależności Composer..."
	@docker compose exec app composer update 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app composer update

sync-vendor:
	@echo "🔄 Synchronizuję vendor z kontenera..."
	@./docker-sync-vendor.sh

# NPM
npm:
	@if [ -z "$(CMD)" ]; then \
		echo "❌ Użycie: make npm CMD='install'"; \
		exit 1; \
	fi
	@docker compose -f docker-compose.dev.yml exec app npm $(CMD)

npm-install:
	@echo "📦 Instaluję zależności NPM..."
	@docker compose -f docker-compose.dev.yml exec app npm install

npm-build:
	@echo "🔨 Buduję frontend..."
	@docker compose -f docker-compose.dev.yml exec app npm run build

# Testy
test:
	@echo "🧪 Uruchamiam testy..."
	@docker compose exec app php artisan test 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app php artisan test

# Czyszczenie
clean:
	@echo "🧹 Czyszczę Docker..."
	docker compose down -v
	docker compose -f docker-compose.dev.yml down -v
	@echo "✅ Wyczyszczone!"

clean-all:
	@echo "🧹 Pełne czyszczenie Docker..."
	docker compose down -v --rmi all
	docker compose -f docker-compose.dev.yml down -v --rmi all
	@echo "✅ Wszystko wyczyszczone!"

# Uprawnienia
permissions:
	@echo "🔐 Naprawiam uprawnienia..."
	@docker compose exec app chown -R www-data:www-data /var/www/html/storage 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app chown -R www-data:www-data /var/www/html/storage
	@docker compose exec app chmod -R 775 /var/www/html/storage 2>/dev/null || \
	 docker compose -f docker-compose.dev.yml exec app chmod -R 775 /var/www/html/storage
	@echo "✅ Uprawnienia naprawione!"

# Status
status:
	@echo "📊 Status kontenerów:"
	@docker compose ps 2>/dev/null || docker compose -f docker-compose.dev.yml ps

