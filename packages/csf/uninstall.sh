#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

cd /etc/csf 2>/dev/null && bash uninstall.sh || true

echo "OK:csf removed"