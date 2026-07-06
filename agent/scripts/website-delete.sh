#!/usr/bin/env bash
# Supprime un site web ObiOra
set -euo pipefail

DOMAIN="${1:-}"
WEB_ROOT="${2:-/var/www}"

if [[ -z "${DOMAIN}" ]]; then
    echo "Usage: website-delete.sh <domain> [web_root]" >&2
    exit 1
fi

SAFE_NAME="${DOMAIN//./-}"
SITE_DIR="${WEB_ROOT}/${DOMAIN}"
NGINX_AVAILABLE="/etc/nginx/sites-available/obiora-${SAFE_NAME}"
NGINX_ENABLED="/etc/nginx/sites-enabled/obiora-${SAFE_NAME}"
NGINX_CONFD="/etc/nginx/conf.d/obiora-${SAFE_NAME}.conf"

rm -f "${NGINX_ENABLED}" "${NGINX_AVAILABLE}" "${NGINX_CONFD}"
rm -rf "${SITE_DIR}"

nginx -t
systemctl reload nginx

echo "OK:deleted"
