#!/usr/bin/env bash
set -euo pipefail

FILENAME="${1:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup-common.sh
source "${SCRIPT_DIR}/backup-common.sh"

if [[ -z "${FILENAME}" ]]; then
    echo "Usage: backup-delete.sh <filename>" >&2
    exit 1
fi

[[ "${FILENAME}" =~ ^[a-zA-Z0-9._-]+$ ]] || { echo "Nom de fichier invalide" >&2; exit 1; }

FILE="${BACKUP_ROOT}/${FILENAME}"
[[ -f "${FILE}" ]] || { echo "Fichier introuvable" >&2; exit 1; }

rm -f "${FILE}"
echo "OK:deleted"
