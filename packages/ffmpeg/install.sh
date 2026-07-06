#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if dpkg -s ffmpeg &>/dev/null; then
    echo "OK:ffmpeg (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq ffmpeg
elif command -v dnf &>/dev/null; then
    dnf install -y ffmpeg 2>/dev/null || { echo "Paquet ffmpeg non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

systemctl enable ffmpeg 2>/dev/null || true
systemctl start ffmpeg 2>/dev/null || true

echo "OK:ffmpeg"