#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

web_user="$(obiora_auth_user)"
web_pass="$(obiora_auth_pass)"
data_dir="/var/lib/obiora/transmission"
mkdir -p "${data_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"
obiora_docker_install "transmission" "lscr.io/linuxserver/transmission:latest" 9091 \
    -e "USER=${web_user}" -e "PASS=${web_pass}" -e "HOST_WHITELIST="
echo "OK:transmission (port 9091) credentials:${web_user}"