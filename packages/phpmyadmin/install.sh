#!/usr/bin/env bash
# Installe phpMyAdmin (image Docker officielle :latest) pour ObiOra Panel.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
obiora_require_docker

name="obiora-phpmyadmin"
image="phpmyadmin:latest"
port="${OBIORA_PHPMYADMIN_PORT:-8099}"
data_dir="/var/lib/obiora/phpmyadmin"

# MariaDB hôte via gateway Docker (déjà géré par mysql-docker-enable).
pma_host="${OBIORA_PHPMYADMIN_DB_HOST:-}"
if [[ -z "${pma_host}" ]]; then
    if [[ -x "${SCRIPT_DIR}/../../agent/scripts/mysql-docker-enable.sh" ]]; then
        enable_out="$("${SCRIPT_DIR}/../../agent/scripts/mysql-docker-enable.sh" 2>&1)" || true
        if [[ "${enable_out}" == OK:* ]]; then
            pma_host="${enable_out#OK:}"
        fi
    fi
fi
pma_host="${pma_host:-172.17.0.1}"

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    docker start "${name}" >/dev/null 2>&1 || true
    if docker ps --format '{{.Names}}' | grep -q "^${name}$"; then
        echo "OK:phpmyadmin (port ${port}, host ${pma_host}) already"
        exit 0
    fi
    docker rm -f "${name}" >/dev/null 2>&1 || true
fi

mkdir -p "${data_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || true

docker pull "${image}" >/dev/null

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    --add-host=host.docker.internal:host-gateway \
    -p "${port}:80" \
    -e PMA_HOST="${pma_host}" \
    -e PMA_PORT=3306 \
    -e PMA_ABSOLUTE_URI="" \
    -e UPLOAD_LIMIT=256M \
    -e MEMORY_LIMIT=512M \
    -e MAX_EXECUTION_TIME=600 \
    "${image}"

echo "OK:phpmyadmin (port ${port}, host ${pma_host})"
