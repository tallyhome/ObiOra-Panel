#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

docker stop obiora-plex 2>/dev/null || true
docker rm obiora-plex 2>/dev/null || true

echo "OK:plex removed"
