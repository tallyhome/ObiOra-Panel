#!/usr/bin/env bash
# Supprime un site web ObiOra
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

DOMAIN="${1:-}"
WEB_ROOT="${2:-/var/www}"

if [[ -z "${DOMAIN}" ]]; then
    echo "Usage: website-delete.sh <domain> [web_root]" >&2
    exit 1
fi

SAFE_NAME="${DOMAIN//./-}"
SITE_DIR="${WEB_ROOT}/${DOMAIN}"

if [[ -d /etc/nginx/sites-available ]]; then
    rm -f "/etc/nginx/sites-enabled/obiora-${SAFE_NAME}"
    rm -f "/etc/nginx/sites-available/obiora-${SAFE_NAME}"
fi
rm -f "/etc/nginx/conf.d/obiora-${SAFE_NAME}.conf"
rm -rf "${SITE_DIR}"

nginx -t
systemctl reload nginx

echo "OK:deleted"
