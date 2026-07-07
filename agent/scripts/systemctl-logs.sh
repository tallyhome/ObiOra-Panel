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

UNITS=(-u "${SERVICE}")

# Timer associé (ex. obiora-scheduler.service → inclure le .timer)
if [[ "${SERVICE}" == *.service ]]; then
    TIMER="${SERVICE%.service}.timer"
    if systemctl cat "${TIMER}" &>/dev/null 2>&1; then
        UNITS+=(-u "${TIMER}")
    fi
fi

# Service associé si on consulte un timer
if [[ "${SERVICE}" == *.timer ]]; then
    SVC="${SERVICE%.timer}.service"
    if systemctl cat "${SVC}" &>/dev/null 2>&1; then
        UNITS+=(-u "${SVC}")
    fi
fi

exec journalctl "${UNITS[@]}" -n "${LINES}" --no-pager -o short-precise
