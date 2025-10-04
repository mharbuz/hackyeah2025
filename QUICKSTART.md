# 🚀 Szybki Start z Docker

## Metoda 1: Interaktywny skrypt (najłatwiejsze)

```bash
./docker-start.sh
```

Wybierz opcję z menu i gotowe! 🎉

---

## Metoda 2: Make (zalecane dla deweloperów)

### Tryb deweloperski (z hot-reload)
```bash
make dev
```

### Tryb produkcyjny
```bash
make prod
```

### Inne przydatne komendy
```bash
make help              # Pokaż wszystkie dostępne komendy
make migrate           # Uruchom migracje
make shell             # Wejdź do kontenera
make logs              # Pokaż logi
make clean             # Wyczyść wszystko
```

---

## Metoda 3: Docker Compose (bezpośrednio)

### Tryb deweloperski
```bash
# Uruchom
docker-compose -f docker-compose.dev.yml up --build

# W tle
docker-compose -f docker-compose.dev.yml up -d --build

# Zatrzymaj
docker-compose -f docker-compose.dev.yml down
```

### Tryb produkcyjny
```bash
# Uruchom
docker-compose up -d --build

# Migracje (po pierwszym uruchomieniu)
docker-compose exec app php artisan migrate --force

# Zatrzymaj
docker-compose down
```

---

## 📱 Dostęp do aplikacji

Po uruchomieniu:

- **Aplikacja**: http://localhost:8000
- **Mailpit** (e-maile): http://localhost:8025
- **Vite HMR** (dev): http://localhost:5173

---

## ⚡ Pierwsze uruchomienie

Jeśli nie masz pliku `.env`:

1. Skopiuj `.env.example` do `.env`
2. Wygeneruj klucz aplikacji:
   ```bash
   docker-compose exec app php artisan key:generate
   ```

Lub użyj trybu deweloperskiego - zrobi to automatycznie!

---

## 🛠️ Często używane komendy

```bash
# Artisan
docker-compose exec app php artisan [komenda]

# Composer
docker-compose exec app composer [komenda]

# NPM (tylko dev)
docker-compose -f docker-compose.dev.yml exec app npm [komenda]

# Bash/Shell
docker-compose exec app sh

# Logi Laravel
docker-compose exec app tail -f storage/logs/laravel.log
```

---

## 📚 Więcej informacji

Zobacz pełną dokumentację w pliku [DOCKER-README.md](DOCKER-README.md)

---

## ❓ Problemy?

### Aplikacja nie startuje
```bash
# Sprawdź logi
docker-compose logs -f

# Sprawdź status
docker-compose ps
```

### Port zajęty
Zmień port w `docker-compose.yml`:
```yaml
ports:
  - "8001:80"  # zamiast 8000:80
```

### Brak uprawnień do plików
```bash
make permissions
```

lub:
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/storage
```

---

## 💡 Wskazówki

- **Używaj trybu deweloperskiego** podczas development - ma hot-reload
- **Tryb produkcyjny** używaj tylko do testowania wersji produkcyjnej
- **Mailpit** przechwytuje wszystkie e-maile - nie musisz konfigurować SMTP
- **Logi** są zawsze dostępne: `docker-compose logs -f`

---

**Miłego kodowania! 🚀**


