#!/usr/bin/env bash
# Logs journalctl pour le panel (exécuté via sudo par l'utilisateur web)
set -euo pipefail

SERVICE="${1:-}"
LINES="${2:-100}"

if [[ ! "$SERVICE" =~ ^[a-zA-Z0-9@._-]+$ ]]; then
    echo "ERREUR: nom de service invalide" >&2
    exit 1
fi

if [[ ! "$LINES" =~ ^[0-9]+$ ]]; then
    LINES=100
fi

if (( LINES < 10 )); then
    LINES=10
elif (( LINES > 500 )); then
    LINES=500
fi

exec journalctl -u "${SERVICE}" -n "${LINES}" --no-pager
