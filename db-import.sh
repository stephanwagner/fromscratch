#!/bin/bash

GREEN="\033[1;32m"
RED="\033[1;31m"
YELLOW="\033[1;33m"
RESET="\033[0m"

CHECK="✔"
CROSS="✖"

MYSQL="/Applications/MAMP/Library/bin/mysql80/bin/mysql"
DB_USER="root"
DB_PASS="root"
DB_HOST="127.0.0.1"
DB_PORT="8889"

DB_NAME="fromscratch2"
BACKUP_FILE="$1"

if [ -z "$DB_NAME" ] || [ -z "$BACKUP_FILE" ]; then
  echo "Usage: ./db-import.sh database_name backup.sql"
  exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
  echo "Backup file not found."
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
    < "$BACKUP_FILE"

  if [ $? -eq 0 ]; then
      echo -e "${GREEN}${CHECK} Import successful.${RESET}"
  else
      echo -e "${RED}${CROSS} Import failed.${RESET}"
  fi
else
  echo "Cancelled."
fi