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

admin_user="${OBIORA_APP_USERNAME:-admin}"
admin_pass="${OBIORA_APP_PASS:-}"
db_host="${OBIORA_APP_DB_HOST:-host.docker.internal}"
db_name="${OBIORA_APP_DB_NAME:-}"
db_user="${OBIORA_APP_DB_USER:-}"
db_pass="${OBIORA_APP_DB_PASS:-}"

if [[ -z "${admin_pass}" ]]; then
    echo "ERREUR: mot de passe administrateur requis." >&2
    exit 1
fi

if [[ -z "${db_name}" || -z "${db_user}" || -z "${db_pass}" ]]; then
    echo "ERREUR: base de données non provisionnée (db_name/db_user/db_pass)." >&2
    exit 1
fi

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:nextcloud (déjà installé)"
    exit 0
fi

mkdir -p "${data_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    --add-host=host.docker.internal:host-gateway \
    -p "${port}:443" \
    -v "${data_dir}:/config" \
    -e PUID=1000 \
    -e PGID=1000 \
    "${image}"

ready=0
for _ in $(seq 1 60); do
    if docker exec "${name}" test -f /config/www/nextcloud/occ 2>/dev/null; then
        ready=1
        break
    fi
    sleep 3
done

if [[ "${ready}" -ne 1 ]]; then
    echo "ERREUR: Nextcloud occ introuvable après démarrage du conteneur." >&2
    exit 1
fi

if ! docker exec -u abc "${name}" php /config/www/nextcloud/occ status 2>/dev/null | grep -q "installed: true"; then
    docker exec -u abc "${name}" php /config/www/nextcloud/occ maintenance:install \
        --admin_user="${admin_user}" \
        --admin_pass="${admin_pass}" \
        --database="mysql" \
        --database-name="${db_name}" \
        --database-user="${db_user}" \
        --database-pass="${db_pass}" \
        --database-host="${db_host}" \
        --data-dir="/data"
fi

echo "OK:nextcloud (port ${port}) credentials:${admin_user} dbhost:${db_host}"
