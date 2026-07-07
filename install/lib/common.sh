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

# Détecte le socket PHP-FPM (chemins Debian, RHEL, Remi)
detect_php_fpm_socket() {
    local sock pool_conf listen_val

    for sock in \
        /run/php/php8.3-fpm.sock \
        /run/php-fpm/www.sock \
        /var/run/php-fpm/www.sock \
        /var/opt/remi/php83/run/php-fpm/www.sock \
        /var/opt/remi/php82/run/php-fpm/www.sock; do
        if [[ -S "${sock}" ]]; then
            echo "${sock}"
            return 0
        fi
    done

    for pool_conf in /etc/php-fpm.d/www.conf /etc/opt/remi/php*/php-fpm.d/www.conf; do
        [[ -f "${pool_conf}" ]] || continue
        listen_val="$(grep -E '^listen\s*=' "${pool_conf}" | head -1 | sed -E 's/^listen\s*=\s*//;s/\s*;.*//;s/^[[:space:]]+//;s/[[:space:]]+$//')"
        [[ -z "${listen_val}" ]] && continue
        if [[ "${listen_val}" == /* ]] && [[ -S "${listen_val}" ]]; then
            echo "${listen_val}"
            return 0
        fi
    done

    die "Socket PHP-FPM introuvable (vérifiez: systemctl status php-fpm)"
}

# Permissions lecture web : nginx + php-fpm (apache sur RHEL)
setup_web_permissions() {
    local web_user

    for web_user in nginx apache www-data; do
        if id "${web_user}" &>/dev/null; then
            usermod -aG "${OBIORA_GROUP:-obiora}" "${web_user}" 2>/dev/null || true
        fi
    done

    chmod 750 "${OBIORA_INSTALL_DIR}"
    chmod -R g+rX "${OBIORA_INSTALL_DIR}"
    chmod 755 "${OBIORA_INSTALL_DIR}/public"
}

setup_selinux_panel() {
    if command -v getenforce &>/dev/null && [[ "$(getenforce)" != "Disabled" ]]; then
        info "Configuration SELinux pour le panel..."
        if ! command -v semanage &>/dev/null; then
            pkg_install policycoreutils-python-utils 2>/dev/null || true
        fi
        if command -v semanage &>/dev/null; then
            semanage fcontext -a -t httpd_sys_content_t "${OBIORA_INSTALL_DIR}(/.*)?" 2>/dev/null \
                || semanage fcontext -m -t httpd_sys_content_t "${OBIORA_INSTALL_DIR}(/.*)?" 2>/dev/null || true
            semanage fcontext -a -t httpd_sys_rw_content_t "${OBIORA_INSTALL_DIR}/storage(/.*)?" 2>/dev/null \
                || semanage fcontext -m -t httpd_sys_rw_content_t "${OBIORA_INSTALL_DIR}/storage(/.*)?" 2>/dev/null || true
            semanage fcontext -a -t httpd_sys_rw_content_t "${OBIORA_INSTALL_DIR}/bootstrap/cache(/.*)?" 2>/dev/null \
                || semanage fcontext -m -t httpd_sys_rw_content_t "${OBIORA_INSTALL_DIR}/bootstrap/cache(/.*)?" 2>/dev/null || true
            restorecon -Rv "${OBIORA_INSTALL_DIR}" 2>/dev/null || true
        fi
        setsebool -P httpd_can_network_connect 1 2>/dev/null || true
        success "SELinux configuré"
    fi
}
