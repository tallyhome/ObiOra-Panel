#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
obiora_require_docker

name="obiora-nextcloud"
image="lscr.io/linuxserver/nextcloud:latest"
port=8443
data_dir="/var/lib/obiora/nextcloud"

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:nextcloud (déjà installé)"
    exit 0
fi

mkdir -p "${data_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"

# --add-host permet d'utiliser host.docker.internal comme hôte MySQL dans
# l'assistant Nextcloud (évite localhost = socket inexistant dans le conteneur).
# Port 8443 au lieu de 443 pour ne pas entrer en conflit avec Nginx du panel.
docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    --add-host=host.docker.internal:host-gateway \
    -p "${port}:443" \
    -v "${data_dir}:/config" \
    -e PUID=1000 \
    -e PGID=1000 \
    "${image}"

echo "OK:nextcloud (port ${port}) dbhost:host.docker.internal"
