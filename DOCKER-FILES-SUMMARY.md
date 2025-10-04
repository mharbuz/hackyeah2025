# 📁 Podsumowanie plików Docker

## Główne pliki Docker

### 🐳 Dockerfiles
- **`Dockerfile`** - Produkcyjny obraz (Alpine + Nginx + PHP-FPM + Supervisor)
- **`Dockerfile.dev`** - Deweloperski obraz (Alpine + PHP CLI + Node.js)

### 🔧 Docker Compose
- **`docker-compose.yml`** - Konfiguracja produkcyjna
- **`docker-compose.dev.yml`** - Konfiguracja deweloperska z hot-reload

### 📋 Pliki konfiguracyjne

#### Nginx (`docker/nginx/`)
- **`nginx.conf`** - Główna konfiguracja Nginx
- **`default.conf`** - Konfiguracja virtual host dla Laravel

#### PHP (`docker/php/`)
- **`php.ini`** - Konfiguracja PHP (limity, timezone, opcache)
- **`www.conf`** - Konfiguracja PHP-FPM (procesy, timeouty)

#### Supervisor (`docker/supervisor/`)
- **`supervisord.conf`** - Konfiguracja Supervisor (Nginx + PHP-FPM + Queue workers)

### 🛠️ Skrypty pomocnicze

#### `docker/`
- **`healthcheck.sh`** - Healthcheck dla kontenera (sprawdza Nginx, PHP-FPM, HTTP)
- **`backup-db.sh`** - Automatyczny backup bazy danych (SQLite/MySQL)

#### Katalog główny
- **`docker-start.sh`** - Interaktywny launcher (najłatwiejszy sposób uruchomienia)
- **`Makefile`** - Komendy make dla zaawansowanych użytkowników

### 📚 Dokumentacja
- **`QUICKSTART.md`** - Szybki start (3 metody uruchomienia)
- **`DOCKER-README.md`** - Pełna dokumentacja Docker
- **`DOCKER-FILES-SUMMARY.md`** - Ten plik

### 🚫 Inne
- **`.dockerignore`** - Pliki ignorowane podczas budowania obrazu
- **`.github-workflows-example.yml`** - Przykładowy CI/CD dla GitHub Actions

## 🎯 Struktura katalogów

```
zus_symulator_emerytury/
├── docker/
│   ├── nginx/
│   │   ├── nginx.conf          # Główna config Nginx
│   │   └── default.conf        # VHost dla Laravel
│   ├── php/
│   │   ├── php.ini            # Config PHP
│   │   └── www.conf           # Config PHP-FPM
│   ├── supervisor/
│   │   └── supervisord.conf   # Config Supervisor
│   ├── healthcheck.sh         # Healthcheck script
│   └── backup-db.sh           # Backup script
├── Dockerfile                 # Produkcja
├── Dockerfile.dev            # Development
├── docker-compose.yml        # Compose produkcja
├── docker-compose.dev.yml    # Compose dev
├── docker-start.sh           # Interaktywny launcher
├── Makefile                  # Make commands
├── .dockerignore             # Docker ignore
├── QUICKSTART.md            # Szybki start
├── DOCKER-README.md         # Pełna dokumentacja
└── DOCKER-FILES-SUMMARY.md  # Ten plik
```

## 🚀 Sposób użycia

### Metoda 1: Interaktywny skrypt (zalecane dla początkujących)
```bash
./docker-start.sh
```

### Metoda 2: Make (zalecane dla deweloperów)
```bash
make dev      # Development
make prod     # Production
make help     # Pokaż wszystkie komendy
```

### Metoda 3: Docker Compose (bezpośrednio)
```bash
# Development
docker-compose -f docker-compose.dev.yml up --build

# Production
docker-compose up --build -d
```

## 📦 Co zawierają kontenery?

### Kontener `app` (Produkcja)
- Alpine Linux
- Nginx 1.24+
- PHP 8.2 FPM
- Supervisor (zarządzanie procesami)
- Queue workers (2 procesy)
- Opcache (zoptymalizowany)

### Kontener `app` (Development)
- Alpine Linux
- PHP 8.2 CLI
- Node.js 22 + npm
- Hot Module Replacement (HMR)
- Vite dev server

### Kontener `mysql`
- MySQL 8.0
- Healthcheck
- Persistent volume

### Kontener `redis`
- Redis 7 Alpine
- Persistent volume
- Opcjonalny (dla cache/sessions)

### Kontener `mailpit`
- Mailpit (catch-all mail server)
- Web UI: http://localhost:8025
- SMTP: localhost:1025

## 🔐 Bezpieczeństwo

### Produkcja
- ✅ Opcache włączony
- ✅ `expose_php = Off`
- ✅ Blokada plików `.env`, `.git`
- ✅ Minimalna powierzchnia ataku (Alpine)
- ✅ Healthchecks
- ✅ Non-root user (www-data)

### Development
- ⚠️ Debug mode włączony
- ⚠️ Hot reload
- ⚠️ Wszystkie logi widoczne

## 📊 Porty

| Serwis | Port | Opis |
|--------|------|------|
| App    | 8000 | Aplikacja Laravel |
| Vite   | 5173 | Vite dev server (tylko dev) |
| MySQL  | 3306 | Baza danych MySQL |
| Redis  | 6379 | Cache/Sessions |
| Mailpit Web | 8025 | Web UI dla maili |
| Mailpit SMTP | 1025 | SMTP server |

## 🗄️ Volumes

- **`mysql_data`** - Dane MySQL (persistent)
- **`redis_data`** - Dane Redis (persistent)
- **`./storage`** - Laravel storage (bind mount)
- **`./database`** - SQLite database (bind mount)

## 🔄 Backup

Użyj skryptu backup:
```bash
./docker/backup-db.sh
```

Backupy zapisywane są w `./backups/` i automatycznie kompresowane.
Stare backupy (>30 dni) są automatycznie usuwane.

## 🧪 Testowanie

```bash
# Make
make test

# Docker Compose
docker-compose exec app php artisan test
```

## 📝 Notatki

- **Tryb deweloperski**: Używaj podczas development - automatyczne przeładowanie
- **Tryb produkcyjny**: Tylko do testowania wersji produkcyjnej lokalnie
- **SQLite domyślnie**: Łatwy start bez konfiguracji MySQL
- **MySQL opcjonalnie**: Zmień w `.env` na `DB_CONNECTION=mysql`
- **Redis opcjonalnie**: Zmień w `.env` na `CACHE_STORE=redis`

## 🆘 Troubleshooting

### Sprawdź logi
```bash
make logs
# lub
docker-compose logs -f
```

### Sprawdź status
```bash
make status
# lub
docker-compose ps
```

### Napraw uprawnienia
```bash
make permissions
```

### Przebuduj bez cache
```bash
make build
# lub
docker-compose build --no-cache
```

## 🔗 Przydatne linki

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Laravel Documentation](https://laravel.com/docs)
- [Alpine Linux](https://alpinelinux.org/)

---

**Pytania?** Zobacz pełną dokumentację w `DOCKER-README.md`


