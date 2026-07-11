#!/usr/bin/env bash

setup_agent_systemd() {
    info "Configuration systemd..."

    ensure_agent_executables

    sed "s|User=obiora|User=${OBIORA_AGENT_USER}|g; s|Group=obiora|Group=${OBIORA_AGENT_USER}|g; s|/opt/obiora-panel|${OBIORA_INSTALL_DIR}|g" \
        "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" \
        > /etc/systemd/system/obiora-agent.service

    systemctl daemon-reload
    systemctl enable obiora-agent
    systemctl restart obiora-agent

    success "Service obiora-agent démarré"
}
