#!/usr/bin/env bash

setup_agent_firewall() {
    if command -v ufw &>/dev/null && ufw status 2>/dev/null | grep -q "Status: active"; then
        ufw allow "${OBIORA_AGENT_PORT}/tcp" comment "ObiOra Agent"
        success "UFW : port ${OBIORA_AGENT_PORT} ouvert"
    elif command -v firewall-cmd &>/dev/null; then
        firewall-cmd --permanent --add-port="${OBIORA_AGENT_PORT}/tcp" 2>/dev/null || true
        firewall-cmd --reload 2>/dev/null || true
        success "firewalld : port ${OBIORA_AGENT_PORT} ouvert"
    else
        warn "Pare-feu non détecté — ouvrez le port ${OBIORA_AGENT_PORT}/tcp manuellement"
    fi
}
