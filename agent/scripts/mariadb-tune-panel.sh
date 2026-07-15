#!/usr/bin/env bash
# Applique le tuning MariaDB ObiOra sur une install existante (root).
set -euo pipefail

INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
LIB="${INSTALL_DIR}/install/lib/mariadb-tuning.sh"

if [[ ! -f "${LIB}" ]]; then
    echo "ERREUR: ${LIB} introuvable" >&2
    exit 1
fi

# shellcheck source=/dev/null
source "${INSTALL_DIR}/install/lib/common.sh"
# shellcheck source=/dev/null
source "${LIB}"

tune_mariadb_for_panel
echo "OK: MariaDB tuning appliqué"
