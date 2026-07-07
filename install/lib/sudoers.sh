#!/usr/bin/env bash
# Sudoers ObiOra — scripts agent sans mot de passe

setup_sudoers() {
    info "Configuration sudoers agent..."

    mkdir -p /etc/obiora
    chmod 755 /etc/obiora

    # Scripts exécutables (requis pour sudo NOPASSWD sur le chemin .sh direct)
    if [[ -d "${OBIORA_INSTALL_DIR}/agent/scripts" ]]; then
        chmod +x "${OBIORA_INSTALL_DIR}"/agent/scripts/*.sh 2>/dev/null || true
    fi
    if [[ -d "${OBIORA_INSTALL_DIR}/packages" ]]; then
        find "${OBIORA_INSTALL_DIR}/packages" -name 'install.sh' -o -name 'uninstall.sh' 2>/dev/null \
            | while read -r pkg_script; do
                chmod +x "${pkg_script}" 2>/dev/null || true
            done
    fi

    local sudoers_file="/etc/sudoers.d/obiora-agent"
    local update_script="${OBIORA_INSTALL_DIR}/install/update-panel.sh"
    chmod +x "${update_script}" 2>/dev/null || true

    cat > "${sudoers_file}" <<SUDOERS
# ObiOra Panel — exécution scripts agent, marketplace et mise à jour panel
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/agent/scripts/*.sh
${OBIORA_USER} ALL=(root) NOPASSWD: ${update_script}
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/packages/*/install.sh
${OBIORA_USER} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/packages/*/uninstall.sh
SUDOERS

    # PHP-FPM peut exécuter les scripts agent et la mise à jour panel via sudo
    for web_user in apache nginx www-data; do
        if id "${web_user}" &>/dev/null; then
            cat >> "${sudoers_file}" <<SUDOERS
${web_user} ALL=(root) NOPASSWD: ${OBIORA_INSTALL_DIR}/agent/scripts/*.sh
${web_user} ALL=(root) NOPASSWD: ${update_script}
${web_user} ALL=(root) NOPASSWD: /usr/bin/systemctl start obiora-queue
${web_user} ALL=(root) NOPASSWD: /usr/bin/systemctl restart obiora-queue
${web_user} ALL=(root) NOPASSWD: /usr/bin/systemctl is-active obiora-queue
${web_user} ALL=(root) NOPASSWD: /bin/systemctl start obiora-queue
${web_user} ALL=(root) NOPASSWD: /bin/systemctl restart obiora-queue
${web_user} ALL=(root) NOPASSWD: /bin/systemctl is-active obiora-queue
SUDOERS
        fi
    done

    # Répertoire web pour les sites clients
    local web_root
    # ATTENTION : sous "set -e" + "pipefail" (hérités de common.sh), un grep
    # qui ne trouve AUCUNE correspondance renvoie 1, ce qui ferait avorter tout
    # le script de mise à jour ici-même si on ne neutralise pas ce cas avec
    # "|| true" — OBIORA_WEB_ROOT est optionnel et absent du .env par défaut.
    web_root="$(grep '^OBIORA_WEB_ROOT=' "${OBIORA_INSTALL_DIR}/.env" 2>/dev/null | cut -d= -f2- || true)"
    web_root="${web_root:-/var/www}"
    mkdir -p "${web_root}"
    chmod 755 "${web_root}"

    chmod 440 "${sudoers_file}"
    visudo -cf "${sudoers_file}" || die "Configuration sudoers invalide"

    success "Sudoers agent configuré"
}
