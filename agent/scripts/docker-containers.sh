#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if ! command -v docker &>/dev/null; then
    echo "ERR:Docker non installé"
    exit 1
fi

docker_cmd ps -a --no-trunc --format '{{.ID}}|{{.Names}}|{{.Image}}|{{.Status}}|{{.Ports}}' | while IFS='|' read -r id name image status ports; do
    echo "ROW:${id}:${name}:${image}:${status}:${ports}"
done

echo "OK"
