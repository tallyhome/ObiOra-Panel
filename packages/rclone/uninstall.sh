#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

rm -f /usr/bin/rclone /usr/local/bin/rclone 2>/dev/null || true

echo "OK:rclone removed"