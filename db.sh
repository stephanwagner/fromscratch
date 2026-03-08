#!/bin/bash

GREEN="\033[1;32m"
RED="\033[1;31m"
YELLOW="\033[1;33m"
RESET="\033[0m"

CHECK="✔"
CROSS="✖"

MYSQL="/Applications/MAMP/Library/bin/mysql80/bin/mysql"
MYSQLDUMP="/Applications/MAMP/Library/bin/mysql57/bin/mysqldump"

DB_USER="root"
DB_PASS="root"
DB_HOST="127.0.0.1"
DB_PORT="8889"
DB_NAME="fromscratch2"

COMMAND="$1"
FILE="$2"

export_db() {

  read -p "Enter backup name (leave empty for timestamp): " NAME

  if [ -z "$NAME" ]; then
    BACKUP_FILE="export-$(date +%Y-%m-%d_%H-%M-%S).sql"
  else
    BACKUP_FILE="${NAME}.sql"
  fi

  echo -e "${YELLOW}Exporting ${DB_NAME}...${RESET}"

  "$MYSQLDUMP" \
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
}

import_db() {

  if [ -z "$FILE" ]; then
    echo -e "${RED}Please provide a backup file.${RESET}"
    echo "Usage: ./db.sh import backup.sql"
    exit 1
  fi

  if [ "$FILE" = "latest" ]; then
    FILE=$(ls -t export-*.sql 2>/dev/null | head -n 1)
  fi

  if [ ! -f "$FILE" ]; then
    echo -e "${RED}Backup file not found.${RESET}"
    exit 1
  fi

  echo -e "${YELLOW}This will OVERWRITE database: $DB_NAME${RESET}"
  read -p "Type 'yes' to continue: " confirm

  if [ "$confirm" = "yes" ]; then

    "$MYSQL" \
      -h "$DB_HOST" \
      -P "$DB_PORT" \
      -u "$DB_USER" \
      -p"$DB_PASS" \
      "$DB_NAME" \
      < "$FILE"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}${CHECK} Import successful.${RESET}"
    else
        echo -e "${RED}${CROSS} Import failed.${RESET}"
    fi

  else
    echo "Cancelled."
  fi
}

case "$COMMAND" in
  export)
    export_db
    ;;
  import)
    import_db
    ;;
  *)
    echo "Usage:"
    echo "./db.sh export"
    echo "./db.sh import backup.sql"
    echo "./db.sh import latest"
    ;;
esac