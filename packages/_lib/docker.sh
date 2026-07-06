#!/usr/bin/env bash
# ObiOra — helper installation Docker
set -euo pipefail

obiora_require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        exec sudo -n bash "$0" "$@"
    fi
}

obiora_require_docker() {
    if ! command -v docker &>/dev/null; then
        echo "Docker requis. Installez Docker via le module ObiOra." >&2
        exit 1
    fi
}

obiora_docker_install() {
    local slug="$1"
    local image="$2"
    local port="$3"
    local name="obiora-${slug}"

    obiora_require_root
    obiora_require_docker

    if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
        echo "OK:${slug} (déjà installé)"
        exit 0
    fi

    mkdir -p "/var/lib/obiora/${slug}"

    docker run -d \
        --name "${name}" \
        --restart unless-stopped \
        -p "${port}:${port}" \
        -v "/var/lib/obiora/${slug}:/config" \
        -e PUID=1000 -e PGID=1000 \
        "${image}"

    echo "OK:${slug} (port ${port})"
}

obiora_docker_uninstall() {
    local slug="$1"
    local name="obiora-${slug}"

    obiora_require_root
    docker stop "${name}" 2>/dev/null || true
    docker rm "${name}" 2>/dev/null || true
    echo "OK:${slug} removed"
}