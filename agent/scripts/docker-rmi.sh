#!/usr/bin/env bash
set -euo pipefail

IMAGE_REF="${1:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if [[ -z "${IMAGE_REF}" ]]; then
    echo "Usage: docker-rmi.sh <image_id_or_name>" >&2
    exit 1
fi

validate_image_ref "${IMAGE_REF}" || { echo "Image invalide" >&2; exit 1; }

docker_cmd rmi -f "${IMAGE_REF}"
echo "OK:removed"
