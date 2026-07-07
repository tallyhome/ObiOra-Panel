#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

web_user="$(obiora_auth_user)"
web_pass="$(obiora_auth_pass)"
data_dir="/var/lib/obiora/nzbget"
mkdir -p "${data_dir}"
obiora_seed_nzbget_auth "${data_dir}" "${web_user}" "${web_pass}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"
obiora_docker_install "nzbget" "lscr.io/linuxserver/nzbget:latest" 6789 6789
echo "OK:nzbget (port 6789) credentials:${web_user}"