#!/usr/bin/env bash
# Assure que phpMyAdmin (Docker latest) tourne. Appelé depuis le panel.
# Usage: phpmyadmin-ensure.sh [port]
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
PORT="${1:-${OBIORA_PHPMYADMIN_PORT:-8099}}"
NAME="obiora-phpmyadmin"

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if ! command -v docker &>/dev/null; then
    echo "ERREUR: Docker requis pour phpMyAdmin." >&2
    exit 1
fi

# Conteneur déjà actif ?
if docker ps --format '{{.Names}}' | grep -q "^${NAME}$"; then
    echo "OK:running:${PORT}"
    exit 0
fi

# Conteneur arrêté → start
if docker ps -a --format '{{.Names}}' | grep -q "^${NAME}$"; then
    docker start "${NAME}" >/dev/null
    echo "OK:started:${PORT}"
    exit 0
fi

# Install via package marketplace
install_sh="${OBIORA_INSTALL_DIR}/packages/phpmyadmin/install.sh"
if [[ ! -x "${install_sh}" ]]; then
    chmod +x "${install_sh}" 2>/dev/null || true
fi

if [[ ! -f "${install_sh}" ]]; then
    echo "ERREUR: packages/phpmyadmin/install.sh introuvable." >&2
    exit 1
fi

OBIORA_PHPMYADMIN_PORT="${PORT}" bash "${install_sh}"
echo "OK:installed:${PORT}"
