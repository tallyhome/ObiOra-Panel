#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if command -v rclone &>/dev/null; then
    echo "OK:rclone (déjà installé)"
    exit 0
fi

curl -fsSL https://rclone.org/install.sh | bash

echo "OK:rclone"