#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

systemctl stop jellyfin 2>/dev/null || true
systemctl disable jellyfin 2>/dev/null || true

if command -v apt-get &>/dev/null; then
    apt-get remove -y jellyfin 2>/dev/null || true
    rm -f /etc/apt/sources.list.d/jellyfin.list
elif command -v dnf &>/dev/null; then
    dnf remove -y jellyfin 2>/dev/null || true
fi

echo "OK:jellyfin removed"
