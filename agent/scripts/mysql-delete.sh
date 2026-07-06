#!/usr/bin/env bash
# Supprime une base MySQL et son utilisateur associé
set -euo pipefail

DB_NAME="${1:-}"
DB_USER="${2:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=mysql-common.sh
source "${SCRIPT_DIR}/mysql-common.sh"

if [[ -z "${DB_NAME}" ]]; then
    echo "Usage: mysql-delete.sh <db_name> [username]" >&2
    exit 1
fi

validate_db_name "${DB_NAME}" || { echo "Nom de base invalide" >&2; exit 1; }

if [[ "${DB_NAME}" == "obiora_panel" ]]; then
    echo "Suppression de la base panel interdite" >&2
    exit 1
fi

if [[ -n "${DB_USER}" ]]; then
    validate_db_user "${DB_USER}" || { echo "Nom utilisateur invalide" >&2; exit 1; }
fi

mysql_root_exec -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;"

if [[ -n "${DB_USER}" ]]; then
    mysql_root_exec -e "DROP USER IF EXISTS '${DB_USER}'@'localhost';"
fi

mysql_root_exec -e "FLUSH PRIVILEGES;"

echo "OK:deleted"
