#!/bin/bash

# ==========================
# WordPress DB Helper Script
# ==========================

GREEN="\033[1;32m"
RED="\033[1;31m"
YELLOW="\033[1;33m"
RESET="\033[0m"

CHECK="✔"
CROSS="✖"

BASELINE_FILE="db-baseline.sql"

if ! command -v wp &> /dev/null
then
    echo -e "${RED}${CROSS} WP-CLI not found.${RESET}"
    exit 1
fi

case "$1" in
  export)
    echo -e "${YELLOW}Exporting database...${RESET}"
    wp db export "$BASELINE_FILE"
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}${CHECK} Database exported to ${BASELINE_FILE}.${RESET}"
    else
        echo -e "${RED}${CROSS} Export failed.${RESET}"
    fi
    ;;

  import)
    if [ ! -f "$BASELINE_FILE" ]; then
        echo -e "${RED}${CROSS} ${BASELINE_FILE} not found.${RESET}"
        exit 1
    fi

    echo -e "${RED}⚠ This will OVERWRITE the entire database.${RESET}"
    read -p "Type 'yes' to continue: " confirm

    if [ "$confirm" = "yes" ]; then
        echo -e "${YELLOW}Importing database...${RESET}"
        wp db import "$BASELINE_FILE"
        wp cache flush
        echo -e "${GREEN}${CHECK} Database imported successfully.${RESET}"
    else
        echo -e "${YELLOW}Cancelled.${RESET}"
    fi
    ;;

  *)
    echo "Usage:"
    echo "  ./db.sh export   # Export full database"
    echo "  ./db.sh import   # Import and overwrite database"
    ;;
esac