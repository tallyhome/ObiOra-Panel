#!/usr/bin/env bash
set -euo pipefail

CONTAINER="${1:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if [[ -z "${CONTAINER}" ]]; then
    echo "Usage: docker-status.sh <container>" >&2
    exit 1
fi

validate_container_ref "${CONTAINER}" || { echo "STATUS:unknown"; exit 0; }

if ! docker_cmd inspect "${CONTAINER}" &>/dev/null; then
    echo "STATUS:not_found"
    exit 0
fi

if docker_cmd inspect -f '{{.State.Running}}' "${CONTAINER}" 2>/dev/null | grep -q true; then
    echo "STATUS:running"
else
    echo "STATUS:stopped"
fi
