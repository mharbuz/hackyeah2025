#!/bin/bash

# Skrypt do backupu bazy danych
# Użycie: ./docker/backup-db.sh

set -e

BACKUP_DIR="./backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Kolory
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}🔄 Tworzę backup bazy danych...${NC}"

# Utwórz katalog backupów jeśli nie istnieje
mkdir -p "$BACKUP_DIR"

# Sprawdź typ bazy danych
DB_CONNECTION=$(docker compose exec -T app php -r "echo env('DB_CONNECTION');")

if [ "$DB_CONNECTION" = "sqlite" ]; then
    # Backup SQLite
    BACKUP_FILE="$BACKUP_DIR/database_sqlite_$DATE.sqlite"
    docker cp zus_symulator_app:/var/www/html/database/database.sqlite "$BACKUP_FILE"
    echo -e "${GREEN}✅ Backup SQLite zapisany: $BACKUP_FILE${NC}"
    
elif [ "$DB_CONNECTION" = "mysql" ]; then
    # Backup MySQL
    BACKUP_FILE="$BACKUP_DIR/database_mysql_$DATE.sql"
    DB_DATABASE=$(docker compose exec -T app php -r "echo env('DB_DATABASE');")
    DB_USERNAME=$(docker compose exec -T app php -r "echo env('DB_USERNAME');")
    DB_PASSWORD=$(docker compose exec -T app php -r "echo env('DB_PASSWORD');")
    
    docker compose exec -T mysql mysqldump -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_FILE"
    echo -e "${GREEN}✅ Backup MySQL zapisany: $BACKUP_FILE${NC}"
    
else
    echo "❌ Nieznany typ bazy danych: $DB_CONNECTION"
    exit 1
fi

# Kompresja
gzip "$BACKUP_FILE"
echo -e "${GREEN}✅ Backup skompresowany: ${BACKUP_FILE}.gz${NC}"

# Usuwanie starych backupów (starsze niż 30 dni)
find "$BACKUP_DIR" -name "database_*" -type f -mtime +30 -delete 2>/dev/null || true
echo -e "${GREEN}✅ Stare backupy wyczyszczone${NC}"

echo -e "${GREEN}🎉 Backup zakończony pomyślnie!${NC}"

