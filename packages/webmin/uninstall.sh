#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

rm -rf /etc/webmin /usr/share/webmin /var/webmin 2>/dev/null || true

echo "OK:webmin removed"