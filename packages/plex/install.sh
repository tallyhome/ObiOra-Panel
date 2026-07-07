#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
obiora_require_docker

name="obiora-plex"
image="lscr.io/linuxserver/plex:latest"
data_dir="/var/lib/obiora/plex"
plex_claim="${OBIORA_APP_PLEX_CLAIM:-}"

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:plex (déjà installé)"
    exit 0
fi

mkdir -p "${data_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"

claim_env=()
if [[ -n "${plex_claim}" ]]; then
    claim_env=(-e "PLEX_CLAIM=${plex_claim}")
fi

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    --network=host \
    -v "${data_dir}:/config" \
    -e PUID=1000 \
    -e PGID=1000 \
    -e VERSION=docker \
    "${claim_env[@]}" \
    "${image}"

echo "OK:plex (port 32400, réseau host)${plex_claim:+ claim:ok}"
