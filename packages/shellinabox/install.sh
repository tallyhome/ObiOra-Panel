#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if dpkg -s shellinabox &>/dev/null || rpm -q shellinabox &>/dev/null; then
    echo "OK:shellinabox (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq shellinabox
elif command -v dnf &>/dev/null; then
    dnf install -y shellinabox 2>/dev/null || { echo "Paquet shellinabox non disponible" >&2; exit 1; }
elif command -v yum &>/dev/null; then
    yum install -y shellinabox 2>/dev/null || { echo "Paquet shellinabox non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

systemctl enable shellinabox 2>/dev/null || true
systemctl start shellinabox 2>/dev/null || true

echo "OK:shellinabox"