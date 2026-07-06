#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if ! command -v docker &>/dev/null; then
    echo "Docker requis. Installez avec: install.sh --docker" >&2
    exit 1
fi

if docker ps -a --format '{{.Names}}' | grep -q '^obiora-plex$'; then
    echo "OK:plex (déjà installé)"
    exit 0
fi

mkdir -p /var/lib/obiora/plex

docker run -d \
    --name obiora-plex \
    --restart unless-stopped \
    -p 32400:32400 \
    -v /var/lib/obiora/plex:/config \
    -e PUID=1000 -e PGID=1000 \
    plexinc/pms:latest

echo "OK:plex (port 32400)"
