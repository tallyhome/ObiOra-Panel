#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"
obiora_docker_install "sonarrold" "lscr.io/linuxserver/sonarr:version-2.0.0.5161-ls3" 8990 8989