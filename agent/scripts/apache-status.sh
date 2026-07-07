#!/usr/bin/env bash
set -euo pipefail

active="inactive"
vhosts=()

if command -v httpd &>/dev/null || command -v apache2 &>/dev/null; then
    systemctl is-active httpd &>/dev/null && active="active" || systemctl is-active apache2 &>/dev/null && active="active" || true
    for f in /etc/httpd/conf.d/*.conf /etc/apache2/sites-enabled/*; do
        [[ -f "${f}" ]] || continue
        name="$(basename "${f}")"
        server_name="$(grep -m1 'ServerName' "${f}" 2>/dev/null | awk '{print $2}' || true)"
        vhosts+=("{\"file\":\"${name}\",\"server_name\":\"${server_name}\"}")
    done
fi

json="[]"
if ((${#vhosts[@]} > 0)); then
    json="[$(IFS=,; echo "${vhosts[*]}")]"
fi

echo "OK:{\"active\":\"${active}\",\"vhosts\":${json}}"
