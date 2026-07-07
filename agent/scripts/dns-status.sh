#!/usr/bin/env bash
set -euo pipefail

backend="none"
running=false
zones=()

if command -v named &>/dev/null; then
    backend="bind"
    systemctl is-active named &>/dev/null && running=true
elif command -v unbound &>/dev/null; then
    backend="unbound"
    systemctl is-active unbound &>/dev/null && running=true
fi

if [[ -f /etc/resolv.conf ]]; then
    zones+=("{\"type\":\"resolver\",\"content\":\"$(grep -m1 nameserver /etc/resolv.conf | xargs || true)\"}")
fi

json="[$(IFS=,; echo "${zones[*]:-}")]"
[[ "${json}" == "[]" ]] || true

echo "OK:{\"backend\":\"${backend}\",\"running\":${running},\"zones\":${json:-[]}}"
