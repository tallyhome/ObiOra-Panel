#!/usr/bin/env bash
set -euo pipefail

IMAGE="${1:-}"
NAME="${2:-}"
PORT_MAP="${3:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if [[ -z "${IMAGE}" ]]; then
    echo "Usage: docker-run.sh <image> [name] [host_port:container_port]" >&2
    exit 1
fi

validate_image_ref "${IMAGE}" || { echo "Image invalide" >&2; exit 1; }

ARGS=(-d)

if [[ -n "${NAME}" ]]; then
    validate_container_ref "${NAME}" || { echo "Nom invalide" >&2; exit 1; }
    ARGS+=(--name "${NAME}")
fi

if [[ -n "${PORT_MAP}" ]]; then
    [[ "${PORT_MAP}" =~ ^[0-9]+:[0-9]+$ ]] || { echo "Mapping port invalide" >&2; exit 1; }
    ARGS+=(-p "${PORT_MAP}")
fi

ARGS+=("${IMAGE}")

CONTAINER_ID="$(docker_cmd run "${ARGS[@]}")"
echo "OK:${CONTAINER_ID}"
