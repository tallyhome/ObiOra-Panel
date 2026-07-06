#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

echo "Utilisez le module Sites ObiOra pour les certificats SSL." >&2
exit 1

echo "OK:letsencrypt"