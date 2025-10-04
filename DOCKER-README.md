# Docker - Instrukcja użycia

Ten projekt Laravel 12 + Vue zawiera pełną konfigurację Docker z dwoma trybami działania: **deweloperskim** i **produkcyjnym**.

## 📋 Wymagania

- Docker (v20.10+)
- Docker Compose (v2.0+)

## 🚀 Szybki start - Tryb deweloperski

Tryb deweloperski uruchamia serwer deweloperski PHP i Vite z hot-reload.

### 1. Uruchomienie

```bash
# Zbuduj i uruchom kontenery
docker-compose -f docker-compose.dev.yml up --build

# Lub w tle
docker-compose -f docker-compose.dev.yml up -d --build
```

### 2. Dostęp do aplikacji

- **Aplikacja**: http://localhost:8000
- **Vite/HMR**: http://localhost:5173 (hot reload)
- **Mailpit** (testowanie maili): http://localhost:8025
- **MySQL**: localhost:3306

### 3. Zatrzymanie

```bash
docker-compose -f docker-compose.dev.yml down

# Z usunięciem wolumenów (baza danych)
docker-compose -f docker-compose.dev.yml down -v
```

## 🏭 Tryb produkcyjny

Tryb produkcyjny używa Nginx + PHP-FPM z optymalizacjami.

### 1. Uruchomienie

```bash
# Zbuduj i uruchom kontenery
docker-compose up --build -d

# Wejdź do kontenera i uruchom migracje
docker-compose exec app php artisan migrate --force

# Wygeneruj klucz aplikacji (jeśli trzeba)
docker-compose exec app php artisan key:generate --force
```

### 2. Dostęp do aplikacji

- **Aplikacja**: http://localhost:8000
- **Mailpit**: http://localhost:8025
- **MySQL**: localhost:3306

### 3. Zatrzymanie

```bash
docker-compose down

# Z usunięciem wolumenów
docker-compose down -v
```

## 🛠️ Przydatne komendy

### Artisan

```bash
# Tryb deweloperski
docker-compose -f docker-compose.dev.yml exec app php artisan [komenda]

# Tryb produkcyjny
docker-compose exec app php artisan [komenda]
```

Przykłady:
```bash
# Migracje
docker-compose exec app php artisan migrate

# Cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear

# Seedowanie
docker-compose exec app php artisan db:seed
```

### Composer

```bash
# Instalacja pakietów
docker-compose exec app composer install

# Update pakietów
docker-compose exec app composer update

# Dodaj pakiet
docker-compose exec app composer require nazwa/pakietu
```

### NPM

```bash
# W trybie deweloperskim (jeśli potrzebujesz)
docker-compose -f docker-compose.dev.yml exec app npm install
docker-compose -f docker-compose.dev.yml exec app npm run build
```

### Logi

```bash
# Wszystkie logi
docker-compose logs -f

# Logi konkretnego serwisu
docker-compose logs -f app
docker-compose logs -f mysql

# Laravel logi
docker-compose exec app tail -f storage/logs/laravel.log
```

### Bash w kontenerze

```bash
# Tryb deweloperski
docker-compose -f docker-compose.dev.yml exec app bash

# Tryb produkcyjny
docker-compose exec app sh
```

## 🗄️ Baza danych

### SQLite (domyślnie)

Projekt używa SQLite domyślnie. Plik bazy znajduje się w `database/database.sqlite`.

### Przełączenie na MySQL

1. Edytuj plik `.env` w kontenerze lub stwórz lokalnie:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=zus_symulator
DB_USERNAME=zus_user
DB_PASSWORD=secret
```

2. Uruchom migracje:

```bash
docker-compose exec app php artisan migrate:fresh --seed
```

### Dostęp do bazy MySQL

```bash
# Z poziomu hosta
mysql -h 127.0.0.1 -P 3306 -u zus_user -psecret zus_symulator

# Z poziomu kontenera
docker-compose exec mysql mysql -u zus_user -psecret zus_symulator
```

## 📧 Testowanie maili (Mailpit)

Mailpit przechwytuje wszystkie maile wysyłane z aplikacji.

- **URL**: http://localhost:8025
- **SMTP**: localhost:1025

Wszystkie maile wysłane przez aplikację będą widoczne w interfejsie Mailpit.

## 🔧 Troubleshooting

### Problem: Brak uprawnień do plików

```bash
# Napraw uprawnienia
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/bootstrap/cache
```

### Problem: Brak klucza aplikacji

```bash
docker-compose exec app php artisan key:generate --force
```

### Problem: Vite nie łączy się (HMR)

W trybie deweloperskim upewnij się, że:
- Port 5173 jest dostępny
- Przeglądarka używa http://localhost:8000 (nie 127.0.0.1)

### Problem: Node modules lub vendor nie są instalowane

```bash
# Przebuduj kontenery bez cache
docker-compose -f docker-compose.dev.yml down
docker-compose -f docker-compose.dev.yml up --build --force-recreate
```

### Problem: Błąd połączenia z bazą

```bash
# Sprawdź czy MySQL działa
docker-compose ps

# Sprawdź logi MySQL
docker-compose logs mysql

# Zrestartuj kontenery
docker-compose restart
```

## 🧹 Czyszczenie

```bash
# Zatrzymaj i usuń kontenery, sieci
docker-compose down

# Usuń też wolumeny (UWAGA: usunie bazę danych!)
docker-compose down -v

# Usuń obrazy
docker-compose down --rmi all

# Pełne czyszczenie Docker
docker system prune -a --volumes
```

## 📦 Struktura Docker

```
.
├── Dockerfile              # Produkcyjny obraz (Nginx + PHP-FPM)
├── Dockerfile.dev          # Deweloperski obraz (PHP CLI + Node)
├── docker-compose.yml      # Produkcja
├── docker-compose.dev.yml  # Development
├── .dockerignore           # Pliki ignorowane przez Docker
└── docker/
    ├── nginx/
    │   ├── nginx.conf      # Główna konfiguracja Nginx
    │   └── default.conf    # Konfiguracja vhosta
    ├── php/
    │   ├── php.ini         # Konfiguracja PHP
    │   └── www.conf        # Konfiguracja PHP-FPM
    └── supervisor/
        └── supervisord.conf # Supervisor (Nginx + PHP-FPM + Queue)
```

## 🚀 Deployment produkcyjny

1. Ustaw zmienne środowiskowe:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - Wygeneruj silny `APP_KEY`

2. Użyj silnych haseł dla bazy danych

3. Rozważ użycie volume dla storage:
   ```yaml
   volumes:
     - ./storage:/var/www/html/storage
   ```

4. Skonfiguruj backup bazy danych

5. Użyj reverse proxy (np. Traefik, Nginx Proxy) z SSL/TLS

## 📝 Notatki

- **Tryb deweloperski**: Automatyczne przeładowanie kodu (hot-reload), logi na stdout
- **Tryb produkcyjny**: Optymalizacje PHP (Opcache), Nginx jako reverse proxy, queue workers
- **Redis**: Dostępny dla cache/sessions (wymaga konfiguracji w .env)
- **Queue workers**: W produkcji uruchamiane przez Supervisor (2 procesy)

## 🆘 Wsparcie

W razie problemów sprawdź logi:

```bash
docker-compose logs -f
```

Lub wejdź do kontenera i sprawdź logi Laravel:

```bash
docker-compose exec app sh
tail -f storage/logs/laravel.log
```


