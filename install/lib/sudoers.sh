#!/usr/bin/env bash
# Sudoers ObiOra — scripts agent sans mot de passe

setup_sudoers() {
    info "Configuration sudoers agent..."

    mkdir -p /etc/obiora
    chmod 755 /etc/obiora

    local sudoers_file="/etc/sudoers.d/obiora-agent"
    local update_script="${OBIORA_INSTALL_DIR}/install/update-panel.sh"
    chmod +x "${update_script}" 2>/dev/null || true

    cat > "${sudoers_file}" <<SUDOERS
# ObiOra Panel — exécution scripts agent, marketplace et mise à jour panel
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/agent/scripts/*.sh
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/packages/*/install.sh
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/packages/*/uninstall.sh
SUDOERS

    # PHP-FPM (apache/nginx/www-data) peut lancer la mise à jour panel via sudo
    for web_user in apache nginx www-data; do
        if id "${web_user}" &>/dev/null; then
            cat >> "${sudoers_file}" <<SUDOERS
${web_user} ALL=(root) NOPASSWD: ${update_script}
SUDOERS
        fi
    done

    chmod 440 "${sudoers_file}"
    visudo -cf "${sudoers_file}" || die "Configuration sudoers invalide"

    success "Sudoers agent configuré"
}
