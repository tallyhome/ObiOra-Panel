#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

web_user="$(obiora_auth_user)"
web_pass="$(obiora_auth_pass)"
data_dir="/var/lib/obiora/deluge"
mkdir -p "${data_dir}"
obiora_seed_deluge_auth "${data_dir}" "${web_user}" "${web_pass}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"
obiora_docker_install "deluge" "lscr.io/linuxserver/deluge:latest" 8112 8112
echo "OK:deluge (port 8112) credentials:${web_user}"