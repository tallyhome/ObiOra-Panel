#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if ! command -v docker &>/dev/null; then
    echo "Docker requis. Installez Docker via le module ObiOra." >&2
    exit 1
fi

slug="wireguard"
name="obiora-${slug}"
image="lscr.io/linuxserver/wireguard:latest"

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:${slug} (déjà installé)"
    exit 0
fi

mkdir -p "/var/lib/obiora/${slug}"
chown -R 1000:1000 "/var/lib/obiora/${slug}" 2>/dev/null || chmod -R 0777 "/var/lib/obiora/${slug}"

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    --cap-add=NET_ADMIN \
    --cap-add=SYS_MODULE \
    -e PUID=1000 \
    -e PGID=1000 \
    -e TZ="${TZ:-UTC}" \
    -p 51820:51820/udp \
    -v "/var/lib/obiora/${slug}:/config" \
    -v /lib/modules:/lib/modules:ro \
    --sysctl="net.ipv4.conf.all.src_valid_mark=1" \
    "${image}"

echo "OK:${slug} (UDP 51820) — configurez les peers dans /var/lib/obiora/${slug}"
