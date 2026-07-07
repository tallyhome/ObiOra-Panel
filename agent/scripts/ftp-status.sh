#!/usr/bin/env bash
set -euo pipefail

services=()
for svc in pure-ftpd vsftpd; do
    if systemctl list-unit-files "${svc}.service" &>/dev/null 2>&1; then
        active="$(systemctl is-active "${svc}" 2>/dev/null || echo inactive)"
        services+=("{\"service\":\"${svc}\",\"active\":\"${active}\"}")
    fi
done

json="[]"
if ((${#services[@]} > 0)); then
    json="[$(IFS=,; echo "${services[*]}")]"
fi

echo "OK:{\"services\":${json}}"
