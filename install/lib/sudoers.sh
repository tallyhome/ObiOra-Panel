#!/usr/bin/env bash
# Sudoers ObiOra — scripts agent sans mot de passe

setup_sudoers() {
    info "Configuration sudoers agent..."

    mkdir -p /etc/obiora
    chmod 755 /etc/obiora

    local sudoers_file="/etc/sudoers.d/obiora-agent"
    local scripts_glob="${OBIORA_INSTALL_DIR}/agent/scripts/*.sh"

    cat > "${sudoers_file}" <<SUDOERS
# ObiOra Panel — exécution scripts agent et marketplace
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/agent/scripts/*.sh
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/packages/*/install.sh
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/packages/*/uninstall.sh
SUDOERS

    chmod 440 "${sudoers_file}"
    visudo -cf "${sudoers_file}" || die "Configuration sudoers invalide"

    success "Sudoers agent configuré"
}
