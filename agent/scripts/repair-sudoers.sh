#!/usr/bin/env bash
# Regénère /etc/sudoers.d/obiora-agent (sans lancer install.sh complet)
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"
OBIORA_GROUP="${OBIORA_GROUP:-obiora}"
export OBIORA_INSTALL_DIR OBIORA_USER OBIORA_GROUP

# shellcheck source=/dev/null
source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
# shellcheck source=/dev/null
source "${OBIORA_INSTALL_DIR}/install/lib/sudoers.sh"

setup_sudoers
echo "OK: sudoers ObiOra régénéré"
