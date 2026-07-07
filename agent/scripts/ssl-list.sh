#!/usr/bin/env bash
# Liste certificats Let's Encrypt / certbot — OK:{json}
set -euo pipefail

certs=()

if command -v certbot &>/dev/null; then
    while IFS= read -r domain; do
        [[ -z "${domain}" ]] && continue
        expiry=""
        if [[ -f "/etc/letsencrypt/live/${domain}/cert.pem" ]]; then
            expiry="$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/${domain}/cert.pem" 2>/dev/null | cut -d= -f2 || true)"
        fi
        certs+=("{\"domain\":\"${domain}\",\"expiry\":\"${expiry}\",\"issuer\":\"letsencrypt\"}")
    done <<< "$(certbot certificates 2>/dev/null | awk '/Certificate Name:/ {print $3}' || true)"
fi

if ((${#certs[@]} == 0)) && [[ -d /etc/letsencrypt/live ]]; then
    for dir in /etc/letsencrypt/live/*/; do
        [[ -d "${dir}" ]] || continue
        domain="$(basename "${dir}")"
        [[ "${domain}" == "README" ]] && continue
        expiry="$(openssl x509 -enddate -noout -in "${dir}/cert.pem" 2>/dev/null | cut -d= -f2 || true)"
        certs+=("{\"domain\":\"${domain}\",\"expiry\":\"${expiry}\",\"issuer\":\"letsencrypt\"}")
    done
fi

json="[]"
if ((${#certs[@]} > 0)); then
    json="[$(IFS=,; echo "${certs[*]}")]"
fi

echo "OK:${json}"
