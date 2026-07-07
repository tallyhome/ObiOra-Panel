#!/usr/bin/env bash
set -euo pipefail

vhosts=()

if command -v nginx &>/dev/null; then
    for f in /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf; do
        [[ -f "${f}" ]] || continue
        name="$(basename "${f}")"
        server_name="$(grep -m1 'server_name' "${f}" 2>/dev/null | sed 's/.*server_name\s*//;s/;//' | xargs || true)"
        vhosts+=("{\"file\":\"${name}\",\"server_name\":\"${server_name}\"}")
    done
fi

json="[]"
if ((${#vhosts[@]} > 0)); then
    json="[$(IFS=,; echo "${vhosts[*]}")]"
fi

active="unknown"
systemctl is-active nginx &>/dev/null && active="active" || active="inactive"

echo "OK:{\"active\":\"${active}\",\"vhosts\":${json}}"
