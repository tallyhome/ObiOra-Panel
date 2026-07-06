#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if ! command -v docker &>/dev/null; then
    echo "ERR:Docker non installé"
    exit 1
fi

docker_cmd images --format '{{.ID}}|{{.Repository}}|{{.Tag}}|{{.Size}}' | while IFS='|' read -r id repo tag size; do
    echo "ROW:${id}:${repo}:${tag}:${size}"
done

echo "OK"
