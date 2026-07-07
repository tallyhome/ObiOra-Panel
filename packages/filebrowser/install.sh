#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
obiora_require_docker

name="obiora-filebrowser"
port=8080

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:filebrowser (déjà installé)"
    exit 0
fi

mkdir -p /var/lib/obiora/filebrowser
# L'image officielle filebrowser tourne en UID 1000 et écrit settings.json dans /config
chown -R 1000:1000 /var/lib/obiora/filebrowser

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    -p "${port}:80" \
    -v /var/lib/obiora/filebrowser:/config \
    -v /:/srv \
    filebrowser/filebrowser:latest

echo "OK:filebrowser (port ${port})"
