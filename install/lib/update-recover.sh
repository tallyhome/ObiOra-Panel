#!/usr/bin/env bash
# Récupération HTTP du panel après MAJ (500, 502, maintenance, caches, assets manquants)
set -uo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"
OBIORA_GROUP="${OBIORA_GROUP:-obiora}"

cd "${OBIORA_INSTALL_DIR}" || exit 1

run_artisan() {
    if [[ "${EUID}" -eq 0 ]]; then
        sudo -u "${OBIORA_USER}" php artisan "$@" 2>/dev/null || true
    else
        php artisan "$@" 2>/dev/null || true
    fi
}

run_as_obiora() {
    if [[ "${EUID}" -eq 0 ]]; then
        sudo -u "${OBIORA_USER}" env PATH=/usr/local/bin:/usr/bin:/bin:"${PATH}" "$@" 2>/dev/null || true
    else
        env PATH=/usr/local/bin:/usr/bin:/bin:"${PATH}" "$@" 2>/dev/null || true
    fi
}

ensure_frontend_manifest() {
    local manifest="${OBIORA_INSTALL_DIR}/public/build/manifest.json"

    if [[ -f "${manifest}" ]]; then
        return 0
    fi

    echo "[recover] Manifest Vite absent — tentative de compilation frontend…"

    if ! command -v npm &>/dev/null || [[ ! -f package.json ]]; then
        echo "[recover] WARN: npm indisponible — impossible de recompiler les assets"
        return 1
    fi

    mkdir -p "${OBIORA_INSTALL_DIR}/public/build"
    chown "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/public/build" 2>/dev/null || true
    chmod 775 "${OBIORA_INSTALL_DIR}/public/build" 2>/dev/null || true

    run_as_obiora npm ci --ignore-scripts \
        || run_as_obiora env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" npm install --ignore-scripts

    if command -v timeout &>/dev/null; then
        run_as_obiora env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" timeout 900 npm run build
    else
        run_as_obiora env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" npm run build
    fi

    [[ -f "${manifest}" ]]
}

ensure_composer_autoload() {
    if [[ ! -f vendor/autoload.php ]]; then
        echo "[recover] vendor/ absent — composer install…"
        run_as_obiora composer install --no-dev --optimize-autoloader --no-interaction
        return
    fi

    run_as_obiora composer dump-autoload --optimize --no-interaction
}

fix_filesystem_permissions() {
    if [[ "${EUID}" -ne 0 ]]; then
        return 0
    fi

    if [[ -f "${OBIORA_INSTALL_DIR}/install/lib/common.sh" ]]; then
        # shellcheck source=/dev/null
        source "${OBIORA_INSTALL_DIR}/install/lib/common.sh"
        ensure_agent_executables
        if [[ -f "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" ]] \
            && [[ -f /etc/systemd/system/obiora-agent.service ]]; then
            sed "s|/opt/obiora-panel|${OBIORA_INSTALL_DIR}|g" \
                "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" \
                > /etc/systemd/system/obiora-agent.service
            systemctl daemon-reload 2>/dev/null || true
            systemctl restart obiora-agent 2>/dev/null || systemctl start obiora-agent 2>/dev/null || true
        fi
    fi

    chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/storage" "${OBIORA_INSTALL_DIR}/bootstrap/cache" 2>/dev/null || true
    chmod -R 775 "${OBIORA_INSTALL_DIR}/storage" "${OBIORA_INSTALL_DIR}/bootstrap/cache" 2>/dev/null || true

    if id apache &>/dev/null; then
        chown -R "${OBIORA_USER}:apache" "${OBIORA_INSTALL_DIR}/storage" "${OBIORA_INSTALL_DIR}/bootstrap/cache" 2>/dev/null || true
    fi
}

if [[ -f "${OBIORA_INSTALL_DIR}/agent/scripts/mysql-docker-recover.sh" ]]; then
    bash "${OBIORA_INSTALL_DIR}/agent/scripts/mysql-docker-recover.sh" || true
fi

if [[ -f "${OBIORA_INSTALL_DIR}/agent/scripts/mysql-ensure-admin-cnf.sh" ]]; then
    bash "${OBIORA_INSTALL_DIR}/agent/scripts/mysql-ensure-admin-cnf.sh" || true
fi

echo "[recover] Dépendances PHP et assets frontend…"
ensure_composer_autoload
ensure_frontend_manifest || true

echo "[recover] RBAC, permissions et caches Laravel…"
run_artisan up
run_artisan obiora:post-deploy --skip-migrate
run_artisan optimize:clear
run_artisan route:clear
run_artisan view:clear
run_artisan config:clear

fix_filesystem_permissions

echo "[recover] Rechargement PHP-FPM (reload gracieux)…"
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
