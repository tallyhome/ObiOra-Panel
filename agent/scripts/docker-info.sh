#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if ! command -v docker &>/dev/null; then
    echo "ERR:Docker non installé"
    exit 1
fi

VERSION="$(docker_cmd version --format '{{.Server.Version}}' 2>/dev/null || echo 'unknown')"
RUNNING="$(docker_cmd ps -q 2>/dev/null | wc -l | tr -d ' ')"
TOTAL="$(docker_cmd ps -aq 2>/dev/null | wc -l | tr -d ' ')"
IMAGES="$(docker_cmd images -q 2>/dev/null | wc -l | tr -d ' ')"

echo "OK:${VERSION}:${RUNNING}:${TOTAL}:${IMAGES}"
