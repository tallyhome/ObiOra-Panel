#!/usr/bin/env bash
# Binaire setuid root pour lancer update-panel.sh sans sudo (worker obiora-queue)

setup_panel_update_helper() {
    local helper="/usr/local/bin/obiora-panel-update"
    local update_script="${OBIORA_INSTALL_DIR}/install/update-panel.sh"
    local obiora_group="${OBIORA_GROUP:-obiora}"
    local install_dir="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"

    if [[ ! -f "${update_script}" ]]; then
        warn "update-panel.sh introuvable — helper MAJ non installé"
        return 0
    fi

    info "Installation du helper de mise à jour panel (setuid)..."

    cat > "${helper}" <<HELPER
#!/bin/bash
# ObiOra Panel — lance update-panel.sh en root (setuid, groupe ${obiora_group})
set -euo pipefail

if [[ "\${EUID}" -ne 0 ]]; then
    echo "ERREUR: privilèges insuffisants pour la mise à jour" >&2
    exit 1
fi

OBIORA_INSTALL_DIR="${install_dir}"
UPDATE_SCRIPT="\${OBIORA_INSTALL_DIR}/install/update-panel.sh"
HISTORY_ID="\${1:-0}"

if [[ ! "\${HISTORY_ID}" =~ ^[0-9]+\$ ]]; then
    HISTORY_ID="0"
fi

if [[ ! -f "\${UPDATE_SCRIPT}" ]]; then
    echo "ERREUR: script de mise à jour introuvable" >&2
    exit 1
fi

exec bash "\${UPDATE_SCRIPT}" "\${HISTORY_ID}"
HELPER

    chmod 4750 "${helper}"
    chown root:"${obiora_group}" "${helper}"

    success "Helper MAJ installé : ${helper}"
}
