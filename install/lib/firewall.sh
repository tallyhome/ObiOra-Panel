#!/usr/bin/env bash
# Firewall UFW / firewalld

setup_firewall() {
    info "Configuration du pare-feu..."

    if command -v ufw &>/dev/null; then
        ufw --force reset
        ufw default deny incoming
        ufw default allow outgoing
        ufw allow 22/tcp
        ufw allow 80/tcp
        ufw allow 443/tcp
        ufw --force enable
        success "UFW configuré (22, 80, 443)"
    elif command -v firewall-cmd &>/dev/null; then
        systemctl enable firewalld
        systemctl start firewalld
        firewall-cmd --permanent --add-service=ssh
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --reload
        success "firewalld configuré (ssh, http, https)"
    else
        warn "Aucun pare-feu détecté (ufw/firewalld)"
    fi

    # Fail2Ban
    if command -v fail2ban-client &>/dev/null; then
        systemctl_enable_start fail2ban
        success "Fail2Ban activé"
    fi
}
