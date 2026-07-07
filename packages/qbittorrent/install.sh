#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
obiora_require_docker

name="obiora-qbittorrent"
image="lscr.io/linuxserver/qbittorrent:latest"
port=8080
web_user="${OBIORA_APP_USERNAME:-admin}"
web_pass="${OBIORA_APP_PASS:-adminadmin}"

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:qbittorrent (déjà installé)"
    exit 0
fi

mkdir -p "/var/lib/obiora/qbittorrent"
chown -R 1000:1000 "/var/lib/obiora/qbittorrent" 2>/dev/null || chmod -R 0777 "/var/lib/obiora/qbittorrent"

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    -p "${port}:8080" \
    -v "/var/lib/obiora/qbittorrent:/config" \
    -e PUID=1000 -e PGID=1000 \
    -e WEBUI_PORT=8080 \
    -e WEBUI_USERNAME="${web_user}" \
    -e WEBUI_PASSWORD="${web_pass}" \
    "${image}"

echo "OK:qbittorrent (port ${port}) credentials:${web_user}/${web_pass}"
