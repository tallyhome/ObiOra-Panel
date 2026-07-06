#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

rm -rf /opt/duckdns 2>/dev/null || true

echo "OK:duckdns removed"