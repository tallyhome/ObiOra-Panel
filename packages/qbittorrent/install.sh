#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if ! command -v docker &>/dev/null; then
    echo "Docker requis" >&2
    exit 1
fi

if docker ps -a --format '{{.Names}}' | grep -q '^obiora-qbittorrent$'; then
    echo "OK:qbittorrent (déjà installé)"
    exit 0
fi

mkdir -p /var/lib/obiora/qbittorrent

docker run -d \
    --name obiora-qbittorrent \
    --restart unless-stopped \
    -p 8080:8080 -p 6881:6881 -p 6881:6881/udp \
    -v /var/lib/obiora/qbittorrent:/config \
    -e PUID=1000 -e PGID=1000 \
    -e WEBUI_PORT=8080 \
    lscr.io/linuxserver/qbittorrent:latest

echo "OK:qbittorrent (port 8080, défaut admin/adminadmin)"
