#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

systemctl stop ffmpeg 2>/dev/null || true
systemctl disable ffmpeg 2>/dev/null || true

if command -v apt-get &>/dev/null; then
    apt-get remove -y -qq ffmpeg 2>/dev/null || true
elif command -v dnf &>/dev/null; then
    dnf remove -y ffmpeg 2>/dev/null || true
fi

echo "OK:ffmpeg removed"