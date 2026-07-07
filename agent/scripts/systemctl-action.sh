#!/usr/bin/env bash
# Actions systemctl pour le panel (exécuté via sudo par l'utilisateur web)
set -euo pipefail

ACTION="${1:-}"
SERVICE="${2:-}"

ALLOWED_ACTIONS='^(start|stop|restart|reload|enable|disable|status|is-active)$'

if [[ ! "$ACTION" =~ $ALLOWED_ACTIONS ]]; then
    echo "ERREUR: action non autorisée : ${ACTION}" >&2
    exit 1
fi

if [[ ! "$SERVICE" =~ ^[a-zA-Z0-9@._-]+$ ]]; then
    echo "ERREUR: nom de service invalide" >&2
    exit 1
fi

# Bloque les services système internes (non gérables / dangereux)
case "${SERVICE}" in
    systemd-*|dbus-*|dev-*|sys-*|dracut-*|kmod-*|user@*|session-*|auditd|chronyd|rsyslog|polkit|getty@*)
        echo "ERREUR: service système protégé : ${SERVICE}" >&2
        exit 1
        ;;
esac

if ! systemctl "${ACTION}" "${SERVICE}" 2>&1; then
    exit 1
fi

echo "OK:${ACTION}:${SERVICE}"
