#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if ! command -v docker &>/dev/null; then
    echo "Docker requis" >&2
    exit 1
fi

if docker ps -a --format '{{.Names}}' | grep -q '^obiora-sonarr$'; then
    echo "OK:sonarr (déjà installé)"
    exit 0
fi

mkdir -p /var/lib/obiora/sonarr

docker run -d \
    --name obiora-sonarr \
    --restart unless-stopped \
    -p 8989:8989 \
    -v /var/lib/obiora/sonarr:/config \
    -e PUID=1000 -e PGID=1000 \
    lscr.io/linuxserver/sonarr:latest

echo "OK:sonarr (port 8989)"
