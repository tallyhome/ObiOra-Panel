#!/usr/bin/env bash
# Durcissement securite serveur Obiora — actions sans risque de lockout SSH
set -euo pipefail

ACTION="${1:-}"

usage() {
    echo "Usage: $0 {enable-fail2ban|secure-env-perms|enable-firewall|install-rkhunter|all}" >&2
    exit 1
}

[[ -z "${ACTION}" ]] && usage

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n "$0" "$@"
fi

BACKUP_DIR="/var/backups/obiora-security/$(date +%Y%m%d-%H%M%S)"
mkdir -p "${BACKUP_DIR}"

enable_fail2ban() {
    if command -v dnf >/dev/null 2>&1; then
        dnf install -y fail2ban 2>/dev/null || yum install -y fail2ban 2>/dev/null || true
    elif command -v apt-get >/dev/null 2>&1; then
        apt-get update -qq && apt-get install -y fail2ban 2>/dev/null || true
    fi
    systemctl enable --now fail2ban 2>/dev/null || true
    echo "OK:enable-fail2ban:fail2ban active"
}

secure_env_perms() {
    local fixed=0
    for f in \
        /opt/obiora-panel/.env \
        /opt/obiora-agent/config/agent.json \
        /opt/obiora-doctor-agent/agent.env \
        /opt/obiora-doctor/config/agent-panel.json; do
        if [[ -f "${f}" ]]; then
            chmod 600 "${f}"
            fixed=$((fixed + 1))
        fi
    done
    echo "OK:secure-env-perms:${fixed} fichier(s) chmod 600"
}

enable_firewall() {
    local ssh_port="${OBIORA_SSH_PORT:-22}"
    if command -v ufw >/dev/null 2>&1; then
        ufw --force default deny incoming
        ufw --force default allow outgoing
        ufw allow "${ssh_port}/tcp" >/dev/null 2>&1 || true
        ufw allow 80/tcp >/dev/null 2>&1 || true
        ufw allow 443/tcp >/dev/null 2>&1 || true
        ufw --force enable
        echo "OK:enable-firewall:ufw active"
    elif command -v firewall-cmd >/dev/null 2>&1; then
        systemctl enable --now firewalld
        if [[ "${ssh_port}" == "22" ]]; then
            firewall-cmd --permanent --add-service=ssh >/dev/null 2>&1 || true
        else
            firewall-cmd --permanent --add-port="${ssh_port}/tcp" >/dev/null 2>&1 || true
        fi
        firewall-cmd --permanent --add-service=http >/dev/null 2>&1 || true
        firewall-cmd --permanent --add-service=https >/dev/null 2>&1 || true
        firewall-cmd --reload
        echo "OK:enable-firewall:firewalld active"
    else
        echo "ERREUR: ni ufw ni firewalld disponible" >&2
        exit 1
    fi
}

install_rkhunter() {
    if command -v rkhunter >/dev/null 2>&1; then
        echo "OK:install-rkhunter:deja installe"
        return
    fi
    if command -v dnf >/dev/null 2>&1; then
        dnf install -y rkhunter 2>/dev/null || yum install -y rkhunter 2>/dev/null || true
    elif command -v apt-get >/dev/null 2>&1; then
        apt-get update -qq && apt-get install -y rkhunter chkrootkit 2>/dev/null || true
    fi
    if command -v rkhunter >/dev/null 2>&1; then
        rkhunter --update 2>/dev/null || true
        rkhunter --propupd 2>/dev/null || true
        echo "OK:install-rkhunter:rkhunter installe"
    else
        echo "ERREUR: installation rkhunter echouee" >&2
        exit 1
    fi
}

case "${ACTION}" in
    enable-fail2ban) enable_fail2ban ;;
    secure-env-perms) secure_env_perms ;;
    enable-firewall) enable_firewall ;;
    install-rkhunter) install_rkhunter ;;
    all)
        enable_fail2ban
        secure_env_perms
        enable_firewall
        install_rkhunter
        echo "OK:all:durcissement applique (backup ${BACKUP_DIR})"
        ;;
    *) usage ;;
esac
