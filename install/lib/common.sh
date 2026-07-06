#!/usr/bin/env bash
# ObiOra Panel — fonctions communes

set -euo pipefail

OBIORA_LOG_FILE="${OBIORA_LOG_FILE:-/var/log/obiora-install.log}"
OBIORA_SNAPSHOT_DIR="${OBIORA_SNAPSHOT_DIR:-/var/backups/obiora}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp
    timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
    echo -e "${timestamp} [${level}] ${message}" | tee -a "${OBIORA_LOG_FILE}"
}

info()    { log "INFO" "${BLUE}$*${NC}"; }
success() { log "OK" "${GREEN}$*${NC}"; }
warn()    { log "WARN" "${YELLOW}$*${NC}"; }
error()   { log "ERROR" "${RED}$*${NC}"; }

die() {
    error "$*"
    exit 1
}

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        die "Ce script doit être exécuté en root (utilisez: sudo -i)"
    fi
}

load_os_vars() {
    if [[ -f /etc/os-release ]]; then
        # shellcheck source=/dev/null
        . /etc/os-release
        OBIORA_OS_ID="${ID:-unknown}"
        OBIORA_OS_VERSION="${VERSION_ID:-unknown}"
        OBIORA_OS_NAME="${NAME:-unknown}"
    else
        die "Impossible de détecter la distribution Linux"
    fi
}

get_pkg_manager() {
    if command -v apt-get &>/dev/null; then
        echo "apt"
    elif command -v dnf &>/dev/null; then
        echo "dnf"
    else
        die "Gestionnaire de paquets non supporté"
    fi
}

pkg_update() {
    case "$(get_pkg_manager)" in
        apt) apt-get update -qq ;;
        dnf) dnf check-update -q || true ;;
    esac
}

pkg_install() {
    case "$(get_pkg_manager)" in
        apt) DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "$@" ;;
        dnf) dnf install -y -q "$@" ;;
    esac
}

pkg_upgrade() {
    case "$(get_pkg_manager)" in
        apt) DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq ;;
        dnf) dnf upgrade -y -q ;;
    esac
}

systemctl_enable_start() {
    local service="$1"
    systemctl daemon-reload
    systemctl enable "${service}"
    systemctl restart "${service}"
}

get_server_ip() {
    hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1"
}

generate_password() {
    openssl rand -base64 24 | tr -dc 'A-Za-z0-9@#%_' | head -c 24
}
