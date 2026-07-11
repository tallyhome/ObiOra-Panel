#!/usr/bin/env bash
# Attend MariaDB + Redis après reboot, puis prépare le panel Laravel.
set -euo pipefail

INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
MAX_WAIT=90
ELAPSED=0

wait_for() {
    local desc="$1"
    local cmd="$2"
    ELAPSED=0
    while (( ELAPSED < MAX_WAIT )); do
        if eval "${cmd}"; then
            return 0
        fi
        sleep 2
        ELAPSED=$((ELAPSED + 2))
    done
    echo "obiora-panel-ready: timeout waiting for ${desc}" >&2
    return 1
}

wait_for "MariaDB" "mysqladmin ping --silent 2>/dev/null || mariadb-admin ping --silent 2>/dev/null"
wait_for "Redis" "redis-cli ping 2>/dev/null | grep -q PONG"

cd "${INSTALL_DIR}"

sudo -u "${OBIORA_USER:-obiora}" php artisan config:clear --quiet 2>/dev/null || true
sudo -u "${OBIORA_USER:-obiora}" php artisan route:clear --quiet 2>/dev/null || true
sudo -u "${OBIORA_USER:-obiora}" php artisan view:clear --quiet 2>/dev/null || true

if [[ -f "${INSTALL_DIR}/public/build/manifest.json" ]]; then
    sudo -u "${OBIORA_USER:-obiora}" php artisan config:cache --quiet 2>/dev/null || true
fi

echo "obiora-panel-ready: panel dependencies OK"
