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

disable_broadcast_if_reverb_down() {
    if ! systemctl list-unit-files 2>/dev/null | grep -q '^obiora-reverb\.service'; then
        return 0
    fi
    if systemctl is-active --quiet obiora-reverb 2>/dev/null; then
        return 0
    fi

    echo "[recover] obiora-reverb inactif — BROADCAST_CONNECTION=null"
    if grep -q '^BROADCAST_CONNECTION=' .env 2>/dev/null; then
        sed -i 's|^BROADCAST_CONNECTION=.*|BROADCAST_CONNECTION=null|' .env
    else
        echo 'BROADCAST_CONNECTION=null' >> .env
    fi
    if grep -q '^OBIORA_REALTIME_ENABLED=' .env 2>/dev/null; then
        sed -i 's|^OBIORA_REALTIME_ENABLED=.*|OBIORA_REALTIME_ENABLED=false|' .env
    else
        echo 'OBIORA_REALTIME_ENABLED=false' >> .env
    fi
}

ensure_frontend_manifest() {
    local manifest="${OBIORA_INSTALL_DIR}/public/build/manifest.json"
    local version_file="${OBIORA_INSTALL_DIR}/VERSION"
    local stamp="${OBIORA_INSTALL_DIR}/storage/app/.frontend-build-version"
    local need_build=0
    local build_next="${OBIORA_INSTALL_DIR}/public/build-next"
    local build_live="${OBIORA_INSTALL_DIR}/public/build"
    local build_prev="${OBIORA_INSTALL_DIR}/public/build-prev"

    if [[ ! -f "${manifest}" ]]; then
        need_build=1
    elif [[ -f "${version_file}" ]]; then
        if [[ ! -f "${stamp}" ]] || [[ "$(tr -d '\r\n' < "${version_file}")" != "$(tr -d '\r\n' < "${stamp}")" ]]; then
            need_build=1
        fi
    fi

    if [[ "${need_build}" -eq 0 ]]; then
        return 0
    fi

    echo "[recover] Assets frontend absents ou version panel changée — recompilation atomique…"

    if ! command -v npm &>/dev/null || [[ ! -f package.json ]]; then
        echo "[recover] WARN: npm indisponible — impossible de recompiler les assets"
        return 1
    fi

    rm -rf "${build_next}"
    mkdir -p "${build_next}"
    chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/public" 2>/dev/null || true
    chmod 775 "${build_next}" 2>/dev/null || true

    run_as_obiora npm ci --ignore-scripts \
        || run_as_obiora env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" npm install --ignore-scripts

    local build_ok=0
    if command -v timeout &>/dev/null; then
        if run_as_obiora env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" \
            timeout 900 npx vite build --outDir "${build_next}"; then
            build_ok=1
        fi
    elif run_as_obiora env NODE_OPTIONS="${NODE_OPTIONS:-} --max-old-space-size=4096" \
        npx vite build --outDir "${build_next}"; then
        build_ok=1
    fi

    if [[ "${build_ok}" -ne 1 ]] || [[ ! -f "${build_next}/manifest.json" ]]; then
        rm -rf "${build_next}"
        echo "[recover] WARN: rebuild frontend échoué (ancien build conservé si présent)"
        return 1
    fi

    rm -rf "${build_prev}"
    if [[ -d "${build_live}" ]]; then
        mv "${build_live}" "${build_prev}"
    fi
    mv "${build_next}" "${build_live}"
    rm -rf "${build_prev}"
    chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${build_live}" 2>/dev/null || true

    if [[ -f "${version_file}" ]]; then
        install -d -m 775 -o "${OBIORA_USER}" -g "${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/storage/app" 2>/dev/null || true
        cp "${version_file}" "${stamp}" 2>/dev/null || true
    fi
}

ensure_composer_autoload() {
    local need_install=0

    if [[ ! -f vendor/autoload.php ]]; then
        need_install=1
    elif [[ -f composer.lock ]] && [[ -f VERSION ]]; then
        # Après checkout, vendor peut être présent mais incomplet — réinstaller si stamp VERSION change
        local stamp="${OBIORA_INSTALL_DIR}/storage/app/.composer-install-version"
        local ver
        ver="$(tr -d '\r\n' < VERSION)"
        if [[ ! -f "${stamp}" ]] || [[ "$(tr -d '\r\n' < "${stamp}" 2>/dev/null || true)" != "${ver}" ]]; then
            need_install=1
        fi
    fi

    if [[ "${need_install}" -eq 1 ]]; then
        echo "[recover] composer install (vendor manquant ou VERSION changée)…"
        run_as_obiora composer install --no-dev --optimize-autoloader --no-interaction
        if [[ -f VERSION ]]; then
            install -d -m 775 -o "${OBIORA_USER}" -g "${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/storage/app" 2>/dev/null || true
            cp VERSION "${OBIORA_INSTALL_DIR}/storage/app/.composer-install-version" 2>/dev/null || true
        fi
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

    if [[ -d "${OBIORA_INSTALL_DIR}/storage/logs" ]]; then
        chmod g+s "${OBIORA_INSTALL_DIR}/storage/logs" 2>/dev/null || true
        find "${OBIORA_INSTALL_DIR}/storage/logs" -type f -user root -exec chown "${OBIORA_USER}:${OBIORA_GROUP}" {} \; 2>/dev/null || true
    fi

    if [[ -d "${OBIORA_INSTALL_DIR}/storage/app/ssh" ]]; then
        chmod 770 "${OBIORA_INSTALL_DIR}/storage/app/ssh" 2>/dev/null || true
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
disable_broadcast_if_reverb_down

echo "[recover] Migrations, RBAC, permissions et caches Laravel…"
run_artisan up
run_artisan migrate --force
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

login_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 8 http://127.0.0.1/login 2>/dev/null || echo '000')"
echo "[recover] login HTTP ${login_code}"
echo "[recover] OK — panel HTTP rétabli"
