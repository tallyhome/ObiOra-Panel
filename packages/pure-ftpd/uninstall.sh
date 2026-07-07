#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

systemctl stop pure-ftpd 2>/dev/null || systemctl stop pure-ftpd.service 2>/dev/null || true
systemctl disable pure-ftpd 2>/dev/null || systemctl disable pure-ftpd.service 2>/dev/null || true

if command -v apt-get &>/dev/null; then
    apt-get remove -y -qq pure-ftpd 2>/dev/null || true
elif command -v dnf &>/dev/null; then
    dnf remove -y pure-ftpd 2>/dev/null || true
fi

echo "OK:pure-ftpd removed"
