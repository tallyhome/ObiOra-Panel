#!/usr/bin/env bash
# ObiOra Panel — Installation automatique
# Usage:
#   bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/install/install.sh)
#   bash install.sh --domain panel.example.com --email admin@example.com
#   bash install.sh --docker --ftp

set -euo pipefail

OBIORA_VERSION="1.8.0"
OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_REPO="https://github.com/tallyhome/ObiOra-Panel.git"
OBIORA_BRANCH="main"
OBIORA_TAG="v1.8.0"
OBIORA_DOMAIN=""
OBIORA_SSL_EMAIL=""
INSTALL_DOCKER="false"
INSTALL_FTP="false"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Chargement des modules
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"
# shellcheck source=lib/detect-os.sh
source "${SCRIPT_DIR}/lib/detect-os.sh"
# shellcheck source=lib/prerequisites.sh
source "${SCRIPT_DIR}/lib/prerequisites.sh"
# shellcheck source=lib/users.sh
source "${SCRIPT_DIR}/lib/users.sh"
# shellcheck source=lib/packages.sh
source "${SCRIPT_DIR}/lib/packages.sh"
# shellcheck source=lib/database.sh
source "${SCRIPT_DIR}/lib/database.sh"
# shellcheck source=lib/laravel.sh
source "${SCRIPT_DIR}/lib/laravel.sh"
# shellcheck source=lib/nginx.sh
source "${SCRIPT_DIR}/lib/nginx.sh"
# shellcheck source=lib/ssl.sh
source "${SCRIPT_DIR}/lib/ssl.sh"
# shellcheck source=lib/systemd.sh
source "${SCRIPT_DIR}/lib/systemd.sh"
# shellcheck source=lib/sudoers.sh
source "${SCRIPT_DIR}/lib/sudoers.sh"
# shellcheck source=lib/firewall.sh
source "${SCRIPT_DIR}/lib/firewall.sh"
# shellcheck source=lib/rollback.sh
source "${SCRIPT_DIR}/lib/rollback.sh"

usage() {
    cat <<EOF
ObiOra Panel v${OBIORA_VERSION} — Installateur

Usage: install.sh [options]

Options:
  --domain DOMAIN     Nom de domaine pour le panel (SSL Let's Encrypt)
  --email EMAIL       Email pour Let's Encrypt
  --docker            Installer Docker
  --ftp               Installer vsftpd
  --tag TAG           Version Git à installer (défaut: ${OBIORA_TAG})
  --dir PATH          Répertoire d'installation (défaut: ${OBIORA_INSTALL_DIR})
  -h, --help          Afficher cette aide

OS supportés:
  Debian 11/12, Ubuntu 20.04/22.04/24.04, AlmaLinux/Rocky 8/9/10
EOF
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --domain)  OBIORA_DOMAIN="$2"; shift 2 ;;
            --email)   OBIORA_SSL_EMAIL="$2"; shift 2 ;;
            --docker)  INSTALL_DOCKER="true"; shift ;;
            --ftp)     INSTALL_FTP="true"; shift ;;
            --tag)     OBIORA_TAG="$2"; shift 2 ;;
            --dir)     OBIORA_INSTALL_DIR="$2"; shift 2 ;;
            -h|--help) usage; exit 0 ;;
            *) die "Option inconnue: $1" ;;
        esac
    done
}

print_summary() {
    local ip url
    ip="$(get_server_ip)"
    if [[ -n "${OBIORA_DOMAIN}" && -n "${OBIORA_SSL_EMAIL}" ]]; then
        url="https://${OBIORA_DOMAIN}"
    else
        url="http://${ip}"
    fi

    cat <<EOF

${GREEN}╔══════════════════════════════════════════════════╗
║       ObiOra Panel installé avec succès !        ║
╚══════════════════════════════════════════════════╝${NC}

  Version  : v${OBIORA_VERSION}
  URL      : ${url}
  Dossier  : ${OBIORA_INSTALL_DIR}
  Logs     : ${OBIORA_LOG_FILE}
  DB creds : /root/.obiora_db_credentials

  Prochaine étape (Phase 3) : création du compte admin via le panel.

EOF
}

main() {
    parse_args "$@"

    echo ""
    echo "  ObiOra Panel v${OBIORA_VERSION}"
    echo "  ================================"
    echo ""

    require_root
    mkdir -p "$(dirname "${OBIORA_LOG_FILE}")" "${OBIORA_SNAPSHOT_DIR}"
    touch "${OBIORA_LOG_FILE}"

    assert_supported_os
    check_prerequisites

    create_snapshot "pre-install"

    install_base_packages
    create_system_users
    setup_database
    clone_panel
    setup_laravel
    setup_nginx
    setup_ssl
    setup_systemd
    setup_sudoers
    setup_firewall

    # Désactiver le trap rollback après succès
    trap - ERR

    print_summary
}

main "$@"
