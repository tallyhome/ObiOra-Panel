#!/usr/bin/env bash
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

BACKUP_ROOT="${OBIORA_BACKUP_ROOT:-/var/backups/obiora}"

ensure_backup_root() {
    mkdir -p "${BACKUP_ROOT}"
    chmod 700 "${BACKUP_ROOT}"
}

tar_cmd() {
    if command -v tar &>/dev/null; then
        command tar "$@"
    elif [[ -x /usr/bin/tar ]]; then
        /usr/bin/tar "$@"
    else
        echo "ERREUR: tar non installé — exécutez : dnf install -y tar" >&2
        exit 1
    fi
}

sanitize_label() {
    local label="${1}"
    [[ "${label}" =~ ^[a-zA-Z0-9_-]{1,64}$ ]]
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=mysql-common.sh
source "${SCRIPT_DIR}/mysql-common.sh"
