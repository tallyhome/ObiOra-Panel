#!/usr/bin/env bash
set -euo pipefail

CONTAINER="${1:-}"
ACTION="${2:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=docker-common.sh
source "${SCRIPT_DIR}/docker-common.sh"

if [[ -z "${CONTAINER}" || -z "${ACTION}" ]]; then
    echo "Usage: docker-action.sh <container> <start|stop|restart|remove>" >&2
    exit 1
fi

validate_container_ref "${CONTAINER}" || { echo "Référence conteneur invalide" >&2; exit 1; }

case "${ACTION}" in
    start)   docker_cmd start "${CONTAINER}" ;;
    stop)    docker_cmd stop "${CONTAINER}" ;;
    restart) docker_cmd restart "${CONTAINER}" ;;
    remove)  docker_cmd rm -f "${CONTAINER}" ;;
    *)       echo "Action non autorisée" >&2; exit 1 ;;
esac

echo "OK:${ACTION}"
