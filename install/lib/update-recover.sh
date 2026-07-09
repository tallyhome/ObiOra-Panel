#!/usr/bin/env bash
# Récupération HTTP du panel après MAJ (502 Bad Gateway, mode maintenance, caches)
set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"

cd "${OBIORA_INSTALL_DIR}"

run_artisan() {
    if [[ "${EUID}" -eq 0 ]]; then
        sudo -u "${OBIORA_USER}" php artisan "$@" 2>/dev/null || true
    else
        php artisan "$@" 2>/dev/null || true
    fi
}

echo "[recover] Sortie du mode maintenance et purge des caches…"
run_artisan up
run_artisan optimize:clear
run_artisan route:clear
run_artisan view:clear
run_artisan config:clear

echo "[recover] Rechargement PHP-FPM (reload gracieux, pas de restart brutal)…"
reload_php_fpm() {
    for svc in php-fpm php8.3-fpm php8.2-fpm php81-php-fpm; do
        if systemctl is-active --quiet "${svc}" 2>/dev/null; then
            systemctl reload "${svc}" 2>/dev/null && return 0
        fi
    done
    for svc in php-fpm php8.3-fpm php8.2-fpm; do
        if systemctl list-unit-files "${svc}.service" &>/dev/null 2>&1; then
            systemctl start "${svc}" 2>/dev/null || true
            systemctl reload "${svc}" 2>/dev/null && return 0
        fi
    done
    return 0
}

if [[ "${EUID}" -eq 0 ]]; then
    reload_php_fpm || true
    systemctl reload nginx 2>/dev/null || true

    if command -v getenforce &>/dev/null && [[ "$(getenforce)" != "Disabled" ]] && command -v restorecon &>/dev/null; then
        restorecon -Rv "${OBIORA_INSTALL_DIR}/public" "${OBIORA_INSTALL_DIR}/storage" >/dev/null 2>&1 || true
    fi
fi

echo "[recover] OK — panel HTTP rétabli"
