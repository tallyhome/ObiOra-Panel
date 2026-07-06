#!/usr/bin/env bash
# Création utilisateurs et permissions

OBIORA_USER="${OBIORA_USER:-obiora}"
OBIORA_GROUP="${OBIORA_GROUP:-obiora}"

create_system_users() {
    info "Création des utilisateurs système..."

    if ! id "${OBIORA_USER}" &>/dev/null; then
        useradd -r -m -d "/home/${OBIORA_USER}" -s /bin/bash "${OBIORA_USER}"
        success "Utilisateur ${OBIORA_USER} créé"
    else
        info "Utilisateur ${OBIORA_USER} existe déjà"
    fi

    usermod -aG www-data "${OBIORA_USER}" 2>/dev/null || usermod -aG nginx "${OBIORA_USER}" 2>/dev/null || true

    mkdir -p "${OBIORA_INSTALL_DIR}"
    chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}"
    chmod 750 "${OBIORA_INSTALL_DIR}"

    success "Permissions configurées"
}
