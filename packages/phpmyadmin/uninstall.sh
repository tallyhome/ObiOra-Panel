#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "${SCRIPT_DIR}/../_lib/docker.sh"

obiora_require_root
docker stop obiora-phpmyadmin 2>/dev/null || true
docker rm obiora-phpmyadmin 2>/dev/null || true
echo "OK:phpmyadmin removed"
