#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"
obiora_docker_install "quassel" "lscr.io/linuxserver/quassel-core:latest" 4242