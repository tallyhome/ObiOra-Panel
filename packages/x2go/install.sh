#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if dpkg -s x2goserver &>/dev/null || rpm -q x2goserver &>/dev/null; then
    echo "OK:x2go (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq x2goserver
elif command -v dnf &>/dev/null; then
    dnf install -y x2goserver 2>/dev/null || { echo "Paquet x2goserver non disponible" >&2; exit 1; }
elif command -v yum &>/dev/null; then
    yum install -y x2goserver 2>/dev/null || { echo "Paquet x2goserver non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

systemctl enable x2goserver 2>/dev/null || true
systemctl start x2goserver 2>/dev/null || true

echo "OK:x2go"