#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
obiora_require_docker

name="obiora-filebrowser"
image="filebrowser/filebrowser:latest"
port=8080
data_dir="/var/lib/obiora/filebrowser"

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:filebrowser (déjà installé)"
    exit 0
fi

mkdir -p "${data_dir}/srv" "${data_dir}/database" "${data_dir}/config"
chown -R 1000:1000 "${data_dir}"

# L'image officielle génère un mot de passe admin ALÉATOIRE au premier
# démarrage ("quick setup"), visible uniquement une fois dans les logs du
# conteneur — ce qui rend "admin/admin" (annoncé dans le manifest) invalide.
# On pré-initialise donc la base avec un compte admin/admin connu, via le
# binaire filebrowser lui-même en écrasant l'entrypoint (pas de serveur lancé
# tant que la base n'existe pas encore).
if [[ ! -f "${data_dir}/database/filebrowser.db" ]]; then
    docker run --rm \
        -v "${data_dir}/database:/database" \
        -v "${data_dir}/config:/config" \
        --entrypoint filebrowser \
        "${image}" \
        -d /database/filebrowser.db config init >/dev/null

    docker run --rm \
        -v "${data_dir}/database:/database" \
        -v "${data_dir}/config:/config" \
        --entrypoint filebrowser \
        "${image}" \
        -d /database/filebrowser.db users add admin admin --perm.admin >/dev/null

    chown -R 1000:1000 "${data_dir}"
fi

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    -p "${port}:80" \
    -v "${data_dir}/srv:/srv" \
    -v "${data_dir}/database:/database" \
    -v "${data_dir}/config:/config" \
    "${image}"

echo "OK:filebrowser (port ${port})"
