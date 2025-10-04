# Changelog - Konfiguracja Docker

## 2025-10-04 - Poprawki i ulepszenia

### 🐛 Naprawione problemy

#### 1. Problem z własnością Git repository
**Problem:** Git odmawia dostępu do repozytorium z powodu różnych właścicieli
```
fatal: detected dubious ownership in repository at '/var/www/html'
```

**Rozwiązanie:** Dodano konfigurację Git safe.directory w docker-compose.dev.yml:
```bash
git config --global --add safe.directory /var/www/html
```

#### 2. Problem z Vite - "sh: vite: not found"
**Problem:** Vite nie był dostępny, ponieważ Composer próbował uruchomić skrypt `dev` podczas instalacji, zanim npm zainstalował zależności.

**Przyczyna:** W `composer.json` jest zdefiniowany skrypt `"dev"`, który Composer próbuje uruchomić podczas `composer install`, a ten skrypt wymaga Vite (który jeszcze nie został zainstalowany przez npm).

**Rozwiązanie:** 
- ✅ Zmieniono kolejność: **najpierw npm install, potem composer install**
- ✅ Dodano flagę `--no-scripts` dla composer install, aby pominąć problematyczne skrypty
- ✅ Ręcznie uruchamiam tylko `post-autoload-dump` (bezpieczny skrypt)
- ✅ Dodano lepsze logowanie postępu instalacji

#### 3. Docker Compose v2
**Problem:** Używanie przestarzałej komendy `docker-compose` (z myślnikiem)

**Rozwiązanie:** Zaktualizowano wszystkie pliki aby używały `docker compose` (bez myślnika):
- ✅ Makefile
- ✅ docker-start.sh
- ✅ docker/backup-db.sh
- ✅ Usunięto przestarzały atrybut `version: '3.8'` z plików compose

### 📝 Zmiany w plikach

#### `docker-compose.dev.yml`
- Dodano konfigurację Git safe.directory
- Poprawiono skrypt startowy z lepszym logowaniem
- Dodano flagę `--no-interaction` dla composer
- Usunięto atrybut `version`

#### `Makefile`
- Zmieniono `docker-compose` na `docker compose` we wszystkich komendach

#### `docker-start.sh`
- Zmieniono `docker-compose` na `docker compose` we wszystkich komendach

#### `docker/backup-db.sh`
- Zmieniono `docker-compose` na `docker compose`

#### `docker-compose.yml`
- Usunięto przestarzały atrybut `version`

### ✅ Potwierdzenie działania

Po naprawieniu powyższych problemów, kontener deweloperski powinien:
1. ✅ Poprawnie inicjalizować repozytorium Git
2. ✅ Instalować wszystkie zależności Composer
3. ✅ Instalować wszystkie zależności NPM (włącznie z Vite)
4. ✅ Uruchamiać serwer PHP na porcie 8000
5. ✅ Uruchamiać Vite dev server na porcie 5173 z hot-reload

### 🚀 Sposób użycia

```bash
# Uruchom tryb deweloperski
./docker-start.sh
# Wybierz opcję 1

# Lub użyj Make
make dev

# Lub bezpośrednio Docker Compose
docker compose -f docker-compose.dev.yml up --build
```

### 📊 Logi startowe

Po poprawkach, podczas startu kontenera zobaczysz (w poprawnej kolejności):
```
🔧 Konfiguracja Git safe.directory...
📝 Tworzenie pliku .env...
📦 Instalacja zależności NPM (najpierw!)...
📦 Instalacja zależności Composer...
🔑 Generowanie klucza aplikacji...
🗄️ Tworzenie bazy danych...
📊 Uruchamianie migracji...
✅ Konfiguracja zakończona!
🚀 Uruchamianie serwera PHP...
🎨 Uruchamianie Vite...
```

**Ważne:** NPM musi być zainstalowany **przed** Composer, ponieważ skrypty Composer mogą wymagać pakietów Node.js.

### 🔍 Weryfikacja

Sprawdź czy wszystko działa:

```bash
# Status kontenerów
docker compose -f docker-compose.dev.yml ps

# Logi
docker compose -f docker-compose.dev.yml logs -f app

# Sprawdź czy Vite działa
curl http://localhost:5173

# Sprawdź czy aplikacja działa
curl http://localhost:8000
```

### 💡 Dodatkowe notatki

- **Docker Compose v2** jest teraz wymaganym minimum (komenda `docker compose`)
- Jeśli masz starszą wersję Docker, zainstaluj Docker Compose v2
- Wszystkie skrypty i narzędzia zostały zaktualizowane do nowej składni

