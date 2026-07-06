#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if dpkg -s quota &>/dev/null; then
    echo "OK:quota (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq quota
elif command -v dnf &>/dev/null; then
    dnf install -y quota 2>/dev/null || { echo "Paquet quota non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

systemctl enable quota 2>/dev/null || true
systemctl start quota 2>/dev/null || true

echo "OK:quota"