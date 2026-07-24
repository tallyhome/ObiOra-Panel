#!/usr/bin/env bash
# Statut phpMyAdmin pour le panel.
# Usage: phpmyadmin-status.sh [port]
set -euo pipefail

NAME="obiora-phpmyadmin"
PORT="${1:-${OBIORA_PHPMYADMIN_PORT:-8099}}"

if ! command -v docker &>/dev/null; then
    echo "OK:missing:docker"
    exit 0
fi

if docker ps --format '{{.Names}}' | grep -q "^${NAME}$"; then
    echo "OK:running:${PORT}"
    exit 0
fi

if docker ps -a --format '{{.Names}}' | grep -q "^${NAME}$"; then
    echo "OK:stopped:${PORT}"
    exit 0
fi

echo "OK:absent:${PORT}"
