#!/usr/bin/env bash
# Active SSL Let's Encrypt pour un site ObiOra
set -euo pipefail

DOMAIN="${1:-}"
EMAIL="${2:-}"
WEB_ROOT="${3:-/var/www}"

if [[ -z "${DOMAIN}" || -z "${EMAIL}" ]]; then
    echo "Usage: website-ssl.sh <domain> <email> [web_root]" >&2
    exit 1
fi

SITE_DIR="${WEB_ROOT}/${DOMAIN}"
META_FILE="${SITE_DIR}/.obiora.json"

certbot --nginx \
    -d "${DOMAIN}" \
    -d "www.${DOMAIN}" \
    --non-interactive \
    --agree-tos \
    -m "${EMAIL}" \
    --redirect

EXPIRES=""
if [[ -f "/etc/letsencrypt/live/${DOMAIN}/cert.pem" ]]; then
    EXPIRES="$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/${DOMAIN}/cert.pem" | cut -d= -f2)"
fi

if [[ -f "${META_FILE}" ]]; then
  python3 -c "
import json, sys
from datetime import datetime
path = sys.argv[1]
expires = sys.argv[2]
with open(path) as f:
    data = json.load(f)
data['ssl_enabled'] = True
data['ssl_email'] = sys.argv[3]
if expires:
    try:
        data['ssl_expires_at'] = datetime.strptime(expires, '%b %d %H:%M:%S %Y %Z').isoformat()
    except Exception:
        data['ssl_expires_at'] = expires
with open(path, 'w') as f:
    json.dump(data, f, indent=2)
" "${META_FILE}" "${EXPIRES}" "${EMAIL}" 2>/dev/null || true
fi

echo "OK:${EXPIRES}"
