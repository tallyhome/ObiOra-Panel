#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

web_user="$(obiora_auth_user)"
web_pass="$(obiora_auth_pass)"
data_dir="/var/lib/obiora/pyload"
mkdir -p "${data_dir}"
chown -R 1000:1000 "${data_dir}" 2>/dev/null || chmod -R 0777 "${data_dir}"
obiora_docker_install "pyload" "lscr.io/linuxserver/pyload-ng:latest" 8000 8000
echo "OK:pyload (port 8000) credentials:${web_user}"