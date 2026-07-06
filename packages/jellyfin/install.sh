#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if command -v jellyfin &>/dev/null || systemctl is-active jellyfin &>/dev/null; then
    echo "OK:jellyfin (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq apt-transport-https ca-certificates gnupg curl
    curl -fsSL https://repo.jellyfin.org/jellyfin_team.gpg.key | gpg --dearmor -o /usr/share/keyrings/jellyfin.gpg
    echo "deb [signed-by=/usr/share/keyrings/jellyfin.gpg arch=$(dpkg --print-architecture)] https://repo.jellyfin.org/debian bookworm main" > /etc/apt/sources.list.d/jellyfin.list
    apt-get update -qq
    apt-get install -y -qq jellyfin
elif command -v dnf &>/dev/null; then
    dnf install -y jellyfin 2>/dev/null || {
        echo "Jellyfin non disponible via dnf — utilisez Docker" >&2
        exit 1
    }
else
    echo "OS non supporté" >&2
    exit 1
fi

systemctl enable jellyfin
systemctl start jellyfin

echo "OK:jellyfin (port 8096)"
