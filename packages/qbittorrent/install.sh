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
web_pass="${OBIORA_APP_PASS:-}"

if [[ -z "${web_pass}" ]]; then
    echo "ERREUR: mot de passe WebUI requis (wizard d'installation)." >&2
    exit 1
fi

if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
    echo "OK:qbittorrent (déjà installé)"
    exit 0
fi

if ! command -v python3 &>/dev/null; then
    echo "ERREUR: python3 requis pour configurer le mot de passe WebUI." >&2
    exit 1
fi

data_dir="/var/lib/obiora/qbittorrent"
config_dir="${data_dir}/qBittorrent"
conf_file="${config_dir}/qBittorrent.conf"

mkdir -p "${config_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"

hash_pair="$(python3 "${SCRIPT_DIR}/webui-password-hash.py" "${web_pass}")"

if [[ -f "${conf_file}" ]]; then
    sed -i '/^WebUI\\Username=/d' "${conf_file}" 2>/dev/null || true
    sed -i '/^WebUI\\Password_PBKDF2=/d' "${conf_file}" 2>/dev/null || true
    sed -i '/^WebUI\\MaxAuthenticationFailCount=/d' "${conf_file}" 2>/dev/null || true
    sed -i '/^WebUI\\BanDuration=/d' "${conf_file}" 2>/dev/null || true
    if ! grep -q '^\[Preferences\]' "${conf_file}"; then
        printf '\n[Preferences]\n' >> "${conf_file}"
    fi
    {
        echo "WebUI\\Username=${web_user}"
        echo "WebUI\\Password_PBKDF2=\"@ByteArray(${hash_pair})\""
        echo "WebUI\\Port=8080"
        echo "WebUI\\MaxAuthenticationFailCount=0"
        echo "WebUI\\BanDuration=60"
    } >> "${conf_file}"
else
    cat > "${conf_file}" <<EOF
[LegalNotice]
Accepted=true

[Preferences]
WebUI\\Username=${web_user}
WebUI\\Password_PBKDF2="@ByteArray(${hash_pair})"
WebUI\\Port=8080
WebUI\\MaxAuthenticationFailCount=0
WebUI\\BanDuration=60
EOF
fi

rm -f "${config_dir}"/qBittorrent-data.conf 2>/dev/null || true
find "${data_dir}" -iname '*ban*' -type f -delete 2>/dev/null || true

chown -R 1000:1000 "${data_dir}"

docker run -d \
    --name "${name}" \
    --restart unless-stopped \
    -p "${port}:8080" \
    -v "${data_dir}:/config" \
    -e PUID=1000 -e PGID=1000 \
    -e WEBUI_PORT=8080 \
    "${image}"

echo "OK:qbittorrent (port ${port}) credentials:${web_user}"
