#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if dpkg -s nginx &>/dev/null || rpm -q nginx &>/dev/null; then
    echo "OK:nginx (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq nginx
elif command -v dnf &>/dev/null; then
    dnf install -y nginx 2>/dev/null || { echo "Paquet nginx non disponible" >&2; exit 1; }
elif command -v yum &>/dev/null; then
    yum install -y nginx 2>/dev/null || { echo "Paquet nginx non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

systemctl enable nginx 2>/dev/null || true
systemctl start nginx 2>/dev/null || true

echo "OK:nginx"
