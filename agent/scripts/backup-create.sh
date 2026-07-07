#!/usr/bin/env bash
set -euo pipefail

TYPE="${1:-}"
LABEL="${2:-}"
TARGET="${3:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup-common.sh
source "${SCRIPT_DIR}/backup-common.sh"

if [[ -z "${TYPE}" || -z "${LABEL}" ]]; then
    echo "Usage: backup-create.sh <database|files|full> <label> [target]" >&2
    exit 1
fi

sanitize_label "${LABEL}" || { echo "Label invalide" >&2; exit 1; }

ensure_backup_root
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
SAFE_LABEL="${LABEL}"

case "${TYPE}" in
    database)
        DB_NAME="${TARGET:-all}"
        FILE="${BACKUP_ROOT}/${SAFE_LABEL}-db-${TIMESTAMP}.sql.gz"
        if [[ "${DB_NAME}" == "all" ]]; then
            DB_LIST="$(mysql_root_exec -N -e "SHOW DATABASES" 2>/dev/null | grep -Ev '^(information_schema|performance_schema|mysql|sys)$' || true)"
            if [[ -z "${DB_LIST}" ]]; then
                echo "ERREUR: aucune base de données à sauvegarder" >&2
                exit 1
            fi
            {
                while read -r db; do
                    [[ -z "${db}" ]] && continue
                    echo "-- DB: ${db}"
                    mysql_root_exec "${db}" 2>/dev/null
                done <<< "${DB_LIST}"
            } | gzip > "${FILE}"
        else
            [[ "${DB_NAME}" =~ ^[a-zA-Z0-9_]{1,64}$ ]] || { echo "Base invalide" >&2; exit 1; }
            mysql_root_exec "${DB_NAME}" | gzip > "${FILE}"
        fi
        ;;
    files)
        PATH_TO_BACKUP="${TARGET:-/var/www}"
        FILE="${BACKUP_ROOT}/${SAFE_LABEL}-files-${TIMESTAMP}.tar.gz"
        tar_cmd -czf "${FILE}" -C "$(dirname "${PATH_TO_BACKUP}")" "$(basename "${PATH_TO_BACKUP}")" 2>/dev/null
        ;;
    full)
        TMP_DIR="$(mktemp -d)"
        DB_DUMP="${TMP_DIR}/databases.sql"
        DB_LIST="$(mysql_root_exec -N -e "SHOW DATABASES" 2>/dev/null | grep -Ev '^(information_schema|performance_schema|mysql|sys)$' || true)"
        if [[ -n "${DB_LIST}" ]]; then
            while read -r db; do
                [[ -z "${db}" ]] && continue
                echo "-- DB: ${db}" >> "${DB_DUMP}"
                mysql_root_exec "${db}" >> "${DB_DUMP}" 2>/dev/null
            done <<< "${DB_LIST}"
        else
            echo "-- Aucune base utilisateur" > "${DB_DUMP}"
        fi
        gzip "${DB_DUMP}"
        FILE="${BACKUP_ROOT}/${SAFE_LABEL}-full-${TIMESTAMP}.tar.gz"
        tar_cmd -czf "${FILE}" -C "${TMP_DIR}" databases.sql.gz -C / var/www 2>/dev/null || tar_cmd -czf "${FILE}" -C "${TMP_DIR}" databases.sql.gz
        rm -rf "${TMP_DIR}"
        ;;
    *)
        echo "Type invalide" >&2
        exit 1
        ;;
esac

SIZE="$(stat -c%s "${FILE}" 2>/dev/null || stat -f%z "${FILE}")"
echo "OK:${FILE}:$(basename "${FILE}"):${SIZE}:${TYPE}"
