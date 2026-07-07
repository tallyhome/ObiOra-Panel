#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if dpkg -s autodl-irssi &>/dev/null || rpm -q autodl-irssi &>/dev/null; then
    echo "OK:autodl (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq autodl-irssi
elif command -v dnf &>/dev/null; then
    dnf install -y autodl-irssi 2>/dev/null || { echo "Paquet autodl-irssi non disponible" >&2; exit 1; }
elif command -v yum &>/dev/null; then
    yum install -y autodl-irssi 2>/dev/null || { echo "Paquet autodl-irssi non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

systemctl enable autodl-irssi 2>/dev/null || true
systemctl start autodl-irssi 2>/dev/null || true

echo "OK:autodl"