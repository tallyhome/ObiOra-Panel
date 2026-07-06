#!/usr/bin/env bash

clone_slave_agent() {
    info "Téléchargement de l'agent ObiOra..."

    if [[ -d "${OBIORA_INSTALL_DIR}/.git" ]]; then
        cd "${OBIORA_INSTALL_DIR}"
        git pull origin "${OBIORA_BRANCH}" -q
    else
        rm -rf "${OBIORA_INSTALL_DIR}"
        git clone --depth 1 --branch "${OBIORA_BRANCH}" "${OBIORA_REPO}" "${OBIORA_INSTALL_DIR}"
    fi

    chown -R "${OBIORA_AGENT_USER}:${OBIORA_AGENT_USER}" "${OBIORA_INSTALL_DIR}"
    success "Agent téléchargé dans ${OBIORA_INSTALL_DIR}"
}

configure_agent() {
    info "Génération de la clé API..."

    local api_key
    api_key="$(generate_api_key)"

    mkdir -p "${OBIORA_INSTALL_DIR}/agent/config"
    cat > "${OBIORA_INSTALL_DIR}/agent/config/agent.json" <<JSON
{
    "host": "0.0.0.0",
    "port": ${OBIORA_AGENT_PORT},
    "token": "${api_key}",
    "role": "slave",
    "version": "${OBIORA_SLAVE_VERSION}"
}
JSON

    chmod 600 "${OBIORA_INSTALL_DIR}/agent/config/agent.json"
    chown "${OBIORA_AGENT_USER}:${OBIORA_AGENT_USER}" "${OBIORA_INSTALL_DIR}/agent/config/agent.json"
    chmod +x "${OBIORA_INSTALL_DIR}/agent/bin/obiOra-agent"

    success "Clé API générée"
}
