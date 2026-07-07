#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
obiora_require_docker

name="obiora-filebrowser"
image="filebrowser/filebrowser:latest"
port=8082
data_dir="/var/lib/obiora/filebrowser"
fb_user="${OBIORA_APP_USERNAME:-admin}"
fb_pass="${OBIORA_APP_PASS:-}"

if [[ -z "${fb_pass}" ]] || [[ ${#fb_pass} -lt 12 ]]; then
    echo "ERREUR: mot de passe administrateur requis (minimum 12 caractères)." >&2
    exit 1
fi

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:filebrowser (déjà installé)"
    exit 0
fi

mkdir -p "${data_dir}/srv" "${data_dir}/database" "${data_dir}/config"
chown -R 1000:1000 "${data_dir}"

db_file="${data_dir}/database/filebrowser.db"
rm -f "${db_file}"

docker run --rm \
    -v "${data_dir}/database:/database" \
    -v "${data_dir}/config:/config" \
    --entrypoint filebrowser \
    "${image}" \
    -d /database/filebrowser.db config init

docker run --rm \
    -v "${data_dir}/database:/database" \
    -v "${data_dir}/config:/config" \
    --entrypoint filebrowser \
    "${image}" \
    -d /database/filebrowser.db config set --minimumPasswordLength 12

docker run --rm \
    -v "${data_dir}/database:/database" \
    -v "${data_dir}/config:/config" \
    --entrypoint filebrowser \
    "${image}" \
    -d /database/filebrowser.db users add "${fb_user}" "${fb_pass}" --perm.admin

if [[ ! -f "${db_file}" ]]; then
    echo "ERREUR: base File Browser non créée dans ${db_file}" >&2
    exit 1
fi

chown -R 1000:1000 "${data_dir}"

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    -p "${port}:80" \
    -v "${data_dir}/srv:/srv" \
    -v "${data_dir}/database:/database" \
    -v "${data_dir}/config:/config" \
    "${image}"

echo "OK:filebrowser (port ${port}) credentials:${fb_user}"
