#!/bin/bash

GREEN="\033[1;32m"
RED="\033[1;31m"
YELLOW="\033[1;33m"
RESET="\033[0m"

CHECK="✔"
CROSS="✖"

MYSQL_BIN="/Applications/MAMP/Library/bin/mysql57/bin/mysqldump"
DB_USER="root"
DB_PASS="root"
DB_HOST="127.0.0.1"
DB_PORT="8889"

DB_NAME="fromscratch2"
BACKUP_FILE="export-$(date +%Y-%m-%d_%H-%M-%S).sql"

echo -e "${YELLOW}Exporting ${DB_NAME}...${RESET}"

"$MYSQL_BIN" \
  -h "$DB_HOST" \
  -P "$DB_PORT" \
  -u "$DB_USER" \
  -p"$DB_PASS" \
  "$DB_NAME" \
  > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}${CHECK} Export successful: $BACKUP_FILE${RESET}"
else
    echo -e "${RED}${CROSS} Export failed.${RESET}"
fi