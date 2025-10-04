#!/bin/bash

# Kolory
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔═══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   ZUS Symulator - Docker Launcher    ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════╝${NC}"
echo ""

# Sprawdź czy Docker działa
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}❌ Docker nie działa. Uruchom Docker Desktop/Docker daemon.${NC}"
    exit 1
fi

# Menu wyboru
echo -e "${YELLOW}Wybierz tryb uruchomienia:${NC}"
echo "1) Deweloperski (hot-reload, debugging)"
echo "2) Produkcyjny (zoptymalizowany)"
echo "3) Status kontenerów"
echo "4) Zatrzymaj wszystko"
echo "5) Wyczyść kontenery i dane"
echo "6) Pokaż logi"
echo ""
read -p "Wybór (1-6): " choice

case $choice in
    1)
        echo -e "${GREEN}🚀 Uruchamiam tryb deweloperski...${NC}"
        docker compose -f docker-compose.dev.yml up --build
        ;;
    2)
        echo -e "${GREEN}🚀 Uruchamiam tryb produkcyjny...${NC}"
        docker compose up --build -d
        
        echo -e "${YELLOW}⏳ Czekam na uruchomienie kontenerów...${NC}"
        sleep 5
        
        echo -e "${YELLOW}🔧 Uruchamiam migracje bazy danych...${NC}"
        docker compose exec app php artisan migrate --force
        
        echo ""
        echo -e "${GREEN}✅ Gotowe!${NC}"
        echo -e "${BLUE}📱 Aplikacja:${NC} http://localhost:8000"
        echo -e "${BLUE}📧 Mailpit:${NC} http://localhost:8025"
        echo ""
        echo -e "${YELLOW}Aby zatrzymać: ${NC}docker compose down"
        echo -e "${YELLOW}Lub uruchom: ${NC}./docker-start.sh ${YELLOW}i wybierz opcję 4${NC}"
        ;;
    3)
        echo -e "${BLUE}📊 Status kontenerów:${NC}"
        echo ""
        docker compose ps 2>/dev/null
        docker compose -f docker-compose.dev.yml ps 2>/dev/null
        ;;
    4)
        echo -e "${YELLOW}🛑 Zatrzymuję kontenery...${NC}"
        docker compose down 2>/dev/null
        docker compose -f docker-compose.dev.yml down 2>/dev/null
        echo -e "${GREEN}✅ Zatrzymane!${NC}"
        ;;
    5)
        echo -e "${RED}⚠️  UWAGA: To usunie wszystkie kontenery i dane (włącznie z bazą)!${NC}"
        read -p "Czy na pewno chcesz kontynuować? (tak/nie): " confirm
        if [ "$confirm" = "tak" ]; then
            echo -e "${YELLOW}🧹 Czyszczę...${NC}"
            docker compose down -v 2>/dev/null
            docker compose -f docker-compose.dev.yml down -v 2>/dev/null
            echo -e "${GREEN}✅ Wyczyszczone!${NC}"
        else
            echo -e "${BLUE}Anulowano.${NC}"
        fi
        ;;
    6)
        echo -e "${BLUE}📋 Wybierz logi:${NC}"
        echo "1) Wszystkie kontenery"
        echo "2) Tylko aplikacja"
        echo "3) MySQL"
        echo "4) Redis"
        read -p "Wybór (1-4): " log_choice
        
        case $log_choice in
            1)
                docker compose logs -f 2>/dev/null || docker compose -f docker-compose.dev.yml logs -f
                ;;
            2)
                docker compose logs -f app 2>/dev/null || docker compose -f docker-compose.dev.yml logs -f app
                ;;
            3)
                docker compose logs -f mysql 2>/dev/null || docker compose -f docker-compose.dev.yml logs -f mysql
                ;;
            4)
                docker compose logs -f redis 2>/dev/null || docker compose -f docker-compose.dev.yml logs -f redis
                ;;
            *)
                echo -e "${RED}❌ Nieprawidłowy wybór${NC}"
                exit 1
                ;;
        esac
        ;;
    *)
        echo -e "${RED}❌ Nieprawidłowy wybór${NC}"
        exit 1
        ;;
esac


