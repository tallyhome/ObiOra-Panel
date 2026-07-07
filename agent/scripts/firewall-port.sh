#!/usr/bin/env bash
# Ouvre ou ferme un port TCP (UFW / firewalld)
set -euo pipefail

ACTION="${1:-}"
PORT="${2:-}"

if [[ ! "${ACTION}" =~ ^(open|close)$ ]] || [[ ! "${PORT}" =~ ^[0-9]+$ ]]; then
    echo "ERREUR: usage firewall-port.sh open|close PORT" >&2
    exit 1
fi

if command -v ufw &>/dev/null; then
    if [[ "${ACTION}" == "open" ]]; then
        ufw allow "${PORT}/tcp"
    else
        ufw delete allow "${PORT}/tcp" 2>/dev/null || ufw deny "${PORT}/tcp"
    fi
elif command -v firewall-cmd &>/dev/null && systemctl is-active --quiet firewalld 2>/dev/null; then
    if [[ "${ACTION}" == "open" ]]; then
        firewall-cmd --permanent --add-port="${PORT}/tcp"
    else
        firewall-cmd --permanent --remove-port="${PORT}/tcp" 2>/dev/null || true
    fi
    firewall-cmd --reload
else
    echo "ERREUR: ufw ou firewalld requis" >&2
    exit 1
fi

echo "OK:${ACTION}:${PORT}"
