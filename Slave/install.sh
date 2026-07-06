#!/usr/bin/env bash
# ObiOra Slave — Installation agent sur serveur distant
# Usage: bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)

set -euo pipefail

OBIORA_SLAVE_VERSION="1.8.2"
OBIORA_REPO="${OBIORA_REPO:-https://github.com/tallyhome/ObiOra-Panel.git}"
OBIORA_BRANCH="${OBIORA_BRANCH:-main}"
OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-slave}"
OBIORA_AGENT_PORT="${OBIORA_AGENT_PORT:-9100}"
OBIORA_AGENT_USER="${OBIORA_AGENT_USER:-obiora-slave}"

_obiora_slave_bootstrap_needed() {
    [[ "${OBIORA_FROM_CLONE:-}" == "1" ]] && return 1
    local src="${BASH_SOURCE[0]}"
    case "${src}" in
        /dev/fd/*|/proc/*/fd/*) return 0 ;;
    esac
    local dir
    dir="$(cd "$(dirname "${src}")" 2>/dev/null && pwd)" || return 0
    [[ ! -f "${dir}/lib/common.sh" ]]
}

_obiora_slave_bootstrap() {
    if [[ "${EUID}" -ne 0 ]]; then
        echo "Ce script doit être exécuté en root (utilisez: sudo -i)" >&2
        exit 1
    fi

    if ! command -v git &>/dev/null; then
        if command -v apt-get &>/dev/null; then
            apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git
        elif command -v dnf &>/dev/null; then
            dnf install -y -q git
        else
            echo "git requis pour l'installation." >&2
            exit 1
        fi
    fi

    local bootstrap_dir
    bootstrap_dir="$(mktemp -d)"
    trap 'rm -rf "${bootstrap_dir}"' EXIT
    echo "Téléchargement d'ObiOra Panel depuis ${OBIORA_REPO} (${OBIORA_BRANCH})..."
    if ! git clone --depth 1 --branch "${OBIORA_BRANCH}" "${OBIORA_REPO}" "${bootstrap_dir}"; then
        git clone --depth 1 "${OBIORA_REPO}" "${bootstrap_dir}"
    fi
    exec env OBIORA_FROM_CLONE=1 bash "${bootstrap_dir}/Slave/install.sh" "$@"
}

if _obiora_slave_bootstrap_needed; then
    _obiora_slave_bootstrap "$@"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"
# shellcheck source=lib/detect-os.sh
source "${SCRIPT_DIR}/lib/detect-os.sh"
# shellcheck source=lib/packages.sh
source "${SCRIPT_DIR}/lib/packages.sh"
# shellcheck source=lib/agent.sh
source "${SCRIPT_DIR}/lib/agent.sh"
# shellcheck source=lib/systemd.sh
source "${SCRIPT_DIR}/lib/systemd.sh"
# shellcheck source=lib/firewall.sh
source "${SCRIPT_DIR}/lib/firewall.sh"

usage() {
    cat <<EOF
ObiOra Slave v${OBIORA_SLAVE_VERSION} — Installateur agent

Usage: install.sh [options]

Options:
  --port PORT     Port agent (défaut: 9100)
  --dir PATH      Répertoire d'installation (défaut: /opt/obiora-slave)
  -h, --help      Aide

À la fin, une clé API sera affichée — entrez-la dans le panel maître (Serveurs → Ajouter).
EOF
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --port) OBIORA_AGENT_PORT="$2"; shift 2 ;;
            --dir)  OBIORA_INSTALL_DIR="$2"; shift 2 ;;
            -h|--help) usage; exit 0 ;;
            *) die "Option inconnue: $1" ;;
        esac
    done
}

print_summary() {
    local ip key
    ip="$(get_server_ip)"
    key="$(cat "${OBIORA_INSTALL_DIR}/agent/config/agent.json" | php -r 'echo json_decode(file_get_contents("php://stdin"))->token;')"

    cat <<EOF

${GREEN}╔══════════════════════════════════════════════════╗
║         ObiOra Slave installé avec succès        ║
╚══════════════════════════════════════════════════╝${NC}

  ${YELLOW}Clé API (à saisir sur le panel maître) :${NC}

  ${BLUE}${key}${NC}

  IP du slave  : ${ip}
  Port agent   : ${OBIORA_AGENT_PORT}
  Dossier      : ${OBIORA_INSTALL_DIR}
  Service      : obiora-agent

  ${YELLOW}Sur le panel maître : Serveurs → Ajouter un serveur${NC}
  Collez la clé API ci-dessus et l'adresse IP ${ip}

EOF
}

main() {
    parse_args "$@"

    echo ""
    echo "  ObiOra Slave v${OBIORA_SLAVE_VERSION}"
    echo "  =============================="
    echo ""

    require_root
    mkdir -p "$(dirname "${OBIORA_LOG_FILE}")"
    touch "${OBIORA_LOG_FILE}"

    assert_supported_os
    install_slave_packages
    create_slave_user
    clone_slave_agent
    configure_agent
    setup_agent_systemd
    setup_agent_firewall

    print_summary
}

main "$@"
