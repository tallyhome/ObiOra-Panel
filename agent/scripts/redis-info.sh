#!/usr/bin/env bash
set -euo pipefail

running=false
version=""
memory=""
clients="0"

if command -v redis-cli &>/dev/null; then
    if redis-cli ping 2>/dev/null | grep -q PONG; then
        running=true
        version="$(redis-cli INFO server 2>/dev/null | awk -F: '/redis_version/ {print $2}' | tr -d '\r' || true)"
        memory="$(redis-cli INFO memory 2>/dev/null | awk -F: '/used_memory_human/ {print $2}' | tr -d '\r' || true)"
        clients="$(redis-cli INFO clients 2>/dev/null | awk -F: '/connected_clients/ {print $2}' | tr -d '\r' || true)"
    fi
fi

echo "OK:{\"running\":${running},\"version\":\"${version}\",\"memory\":\"${memory}\",\"clients\":\"${clients}\"}"
