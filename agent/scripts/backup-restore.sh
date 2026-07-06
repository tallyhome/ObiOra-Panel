#!/usr/bin/env bash
set -euo pipefail

FILENAME="${1:-}"
DB_NAME="${2:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup-common.sh
source "${SCRIPT_DIR}/backup-common.sh"

if [[ -z "${FILENAME}" ]]; then
    echo "Usage: backup-restore.sh <filename> [database_name]" >&2
    exit 1
fi

[[ "${FILENAME}" =~ ^[a-zA-Z0-9._-]+$ ]] || { echo "Nom de fichier invalide" >&2; exit 1; }

FILE="${BACKUP_ROOT}/${FILENAME}"
[[ -f "${FILE}" ]] || { echo "Fichier introuvable" >&2; exit 1; }

if [[ "${FILENAME}" != *.sql.gz ]]; then
    echo "Restauration supportée uniquement pour les dumps .sql.gz" >&2
    exit 1
fi

if [[ -z "${DB_NAME}" ]]; then
    DB_NAME="$(echo "${FILENAME}" | sed -n 's/.*-db-\([^.]*\).*/\1/p')"
    DB_NAME="${DB_NAME:-restored_db}"
fi

[[ "${DB_NAME}" =~ ^[a-zA-Z0-9_]{1,64}$ ]] || { echo "Nom de base invalide" >&2; exit 1; }

mysql_root_exec -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "${FILE}" | mysql_root_exec "${DB_NAME}"

echo "OK:${DB_NAME}"
