#!/usr/bin/env bash
set -euo pipefail

CONTAINER="${1:-}"
LINES="${2:-100}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if [[ -z "${CONTAINER}" ]]; then
    echo "Usage: docker-logs.sh <container> [lines]" >&2
    exit 1
fi

validate_container_ref "${CONTAINER}" || { echo "Référence conteneur invalide" >&2; exit 1; }

LINES=$((LINES))
if (( LINES < 10 )); then LINES=10; fi
if (( LINES > 500 )); then LINES=500; fi

docker_cmd logs --tail "${LINES}" "${CONTAINER}" 2>&1
echo ""
echo "OK"
