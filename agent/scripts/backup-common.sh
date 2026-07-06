#!/usr/bin/env bash
set -euo pipefail

BACKUP_ROOT="${OBIORA_BACKUP_ROOT:-/var/backups/obiora}"

ensure_backup_root() {
    mkdir -p "${BACKUP_ROOT}"
    chmod 700 "${BACKUP_ROOT}"
}

sanitize_label() {
    local label="${1}"
    [[ "${label}" =~ ^[a-zA-Z0-9_-]{1,64}$ ]]
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=mysql-common.sh
source "${SCRIPT_DIR}/mysql-common.sh"
