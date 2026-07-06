#!/usr/bin/env bash
# Crée une base MySQL + utilisateur dédié
set -euo pipefail

DB_NAME="${1:-}"
DB_USER="${2:-}"
DB_PASS="${3:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=mysql-common.sh
source "${SCRIPT_DIR}/mysql-common.sh"

if [[ -z "${DB_NAME}" ]]; then
    echo "Usage: mysql-create.sh <db_name> [username] [password]" >&2
    exit 1
fi

validate_db_name "${DB_NAME}" || { echo "Nom de base invalide" >&2; exit 1; }

if [[ -z "${DB_USER}" ]]; then
    DB_USER="${DB_NAME}_user"
fi

validate_db_user "${DB_USER}" || { echo "Nom utilisateur invalide" >&2; exit 1; }

if [[ -z "${DB_PASS}" ]]; then
    DB_PASS="$(generate_db_password)"
fi

mysql_root_exec <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "OK:${DB_NAME}:${DB_USER}:${DB_PASS}"
