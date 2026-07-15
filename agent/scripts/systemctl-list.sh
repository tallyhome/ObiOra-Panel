#!/usr/bin/env bash
# Liste les unités systemd (exécuté via sudo par PHP-FPM apache/nginx/www-data)
set -euo pipefail

UNIT_TYPE="${1:-service}"

if [[ ! "${UNIT_TYPE}" =~ ^(service|timer|socket|target)$ ]]; then
    echo "ERREUR: type d'unité invalide" >&2
    exit 1
fi

systemctl list-units --type="${UNIT_TYPE}" --all --no-pager --no-legend 2>/dev/null
