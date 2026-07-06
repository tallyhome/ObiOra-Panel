#!/usr/bin/env bash

OBIORA_LOG_FILE="${OBIORA_LOG_FILE:-/var/log/obiora-slave-install.log}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${OBIORA_LOG_FILE}"
}

info()    { log "INFO $*"; }
success() { log "OK $*"; }
warn()    { log "WARN $*"; }
die()     { log "ERROR $*"; exit 1; }

require_root() {
    [[ "${EUID}" -eq 0 ]] || die "Exécuter en root (sudo -i)"
}

load_os_vars() {
    # shellcheck source=/dev/null
    . /etc/os-release
    OBIORA_OS_ID="${ID:-unknown}"
    OBIORA_OS_VERSION="${VERSION_ID:-unknown}"
    OBIORA_OS_NAME="${NAME:-unknown}"
}

get_pkg_manager() {
    command -v apt-get &>/dev/null && echo "apt" && return
    command -v dnf &>/dev/null && echo "dnf" && return
    die "Gestionnaire de paquets non supporté"
}

pkg_install() {
    case "$(get_pkg_manager)" in
        apt) DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "$@" ;;
        dnf) dnf install -y -q "$@" ;;
    esac
}

get_server_ip() {
    hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1"
}

generate_api_key() {
    openssl rand -hex 32
}
