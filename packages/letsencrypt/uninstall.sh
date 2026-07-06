#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

echo "Certificats gérés par ObiOra" >&2

echo "OK:letsencrypt removed"