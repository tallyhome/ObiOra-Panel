#!/usr/bin/env bash
# Installation et configuration Laravel

OBIORA_REPO="${OBIORA_REPO:-https://github.com/tallyhome/ObiOra-Panel.git}"
OBIORA_BRANCH="${OBIORA_BRANCH:-main}"
OBIORA_TAG="${OBIORA_TAG:-}"

clone_panel() {
    info "Téléchargement d'ObiOra Panel..."

    if [[ -d "${OBIORA_INSTALL_DIR}/.git" ]]; then
        info "Mise à jour du dépôt existant..."
        cd "${OBIORA_INSTALL_DIR}"
        # --unshallow si nécessaire pour disposer de toutes les refs
        git config --global --add safe.directory "${OBIORA_INSTALL_DIR}" 2>/dev/null || true
        if [[ -n "${OBIORA_TAG}" ]]; then
            git fetch --depth 1 origin "tag" "${OBIORA_TAG}" 2>/dev/null || git fetch origin
            git checkout -f "${OBIORA_TAG}"
        else
            git fetch --depth 1 origin "${OBIORA_BRANCH}"
            git checkout -B "${OBIORA_BRANCH}" "origin/${OBIORA_BRANCH}"
        fi
        chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}"
    else
        # Clone en root : obiora ne peut pas créer de répertoire dans /opt
        rm -rf "${OBIORA_INSTALL_DIR}"
        git clone --depth 1 \
            ${OBIORA_TAG:+--branch "${OBIORA_TAG}"} \
            "${OBIORA_REPO}" "${OBIORA_INSTALL_DIR}"
        chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}"
        chmod 750 "${OBIORA_INSTALL_DIR}"
    fi

    success "Code source installé dans ${OBIORA_INSTALL_DIR}"
}

setup_laravel() {
    info "Configuration Laravel..."

    # Redis doit tourner avant les migrations (CACHE_STORE=redis, sinon
    # "Connection refused" au moment du reset de cache spatie/permission).
    systemctl_enable_start redis 2>/dev/null || systemctl_enable_start redis-server 2>/dev/null || true

    # shellcheck source=/dev/null
    source /root/.obiora_db_credentials

    local app_url="http://$(get_server_ip)"
    local install_uuid
    install_uuid="$(cat /proc/sys/kernel/random/uuid)"

    if [[ ! -f "${OBIORA_INSTALL_DIR}/.env" ]]; then
        cp "${OBIORA_INSTALL_DIR}/.env.example" "${OBIORA_INSTALL_DIR}/.env"
    fi

    cd "${OBIORA_INSTALL_DIR}"

    # Configuration .env
    sed -i "s|^APP_NAME=.*|APP_NAME=\"ObiOra Panel\"|" .env
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^APP_URL=.*|APP_URL=${app_url}|" .env
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|^DB_PORT=.*|DB_PORT=3306|" .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|" .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
    sed -i "s|^CACHE_STORE=.*|CACHE_STORE=redis|" .env
    sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" .env
    sed -i "s|^OBIORA_INSTALLATION_UUID=.*|OBIORA_INSTALLATION_UUID=${install_uuid}|" .env

    chown -R "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}"

    # Composer & NPM
    # sudo réinitialise le PATH via secure_path (souvent sans /usr/local/bin sur
    # RHEL/AlmaLinux), d'où "composer: command not found". On préserve le PATH.
    local run_as="sudo -u ${OBIORA_USER} env PATH=/usr/local/bin:/usr/bin:/bin:${PATH}"

    ${run_as} composer install --no-dev --optimize-autoloader --no-interaction

    if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
        ${run_as} php artisan key:generate --force
    fi

    if [[ ! -d node_modules ]] || [[ ! -f public/build/manifest.json ]]; then
        ${run_as} npm ci --ignore-scripts 2>/dev/null || ${run_as} npm install
        ${run_as} npm run build
    else
        info "Assets frontend déjà compilés, étape npm ignorée"
    fi

    # Migrations
    ${run_as} php artisan migrate --force
    ${run_as} php artisan db:seed --force

    # Permissions storage (apache sur RHEL, www-data sur Debian)
    chmod -R 775 storage bootstrap/cache
    if getent group www-data &>/dev/null; then
        chown -R "${OBIORA_USER}:www-data" storage bootstrap/cache
    elif getent group apache &>/dev/null; then
        chown -R "${OBIORA_USER}:apache" storage bootstrap/cache
    else
        chown -R "${OBIORA_USER}:nginx" storage bootstrap/cache
    fi

    ${run_as} php artisan storage:link 2>/dev/null || true
    ${run_as} php artisan optimize

    sync_master_server
    setup_agent_config

    success "Laravel configuré"
}

setup_agent_config() {
    info "Configuration de l'agent ObiOra..."

    local token
    token=$(mysql -N -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" \
        -e "SELECT agent_token FROM servers WHERE is_master = 1 LIMIT 1;" 2>/dev/null || true)

    if [[ -z "${token}" ]]; then
        warn "Token agent non trouvé, configuration manuelle requise."
        return
    fi

    mkdir -p "${OBIORA_INSTALL_DIR}/agent/config"
    cat > "${OBIORA_INSTALL_DIR}/agent/config/agent.json" <<JSON
{
    "host": "127.0.0.1",
    "port": 9100,
    "token": "${token}"
}
JSON
    chmod 600 "${OBIORA_INSTALL_DIR}/agent/config/agent.json"
    chown "${OBIORA_USER}:${OBIORA_GROUP}" "${OBIORA_INSTALL_DIR}/agent/config/agent.json"
    chmod +x "${OBIORA_INSTALL_DIR}/agent/bin/obiOra-agent"

    success "Agent configuré (port 9100)"
}

sync_master_server() {
    local ip hostname db_user db_pass db_name

    ip="$(get_server_ip)"
    hostname="$(hostname -f 2>/dev/null || hostname)"

    # "|| true" évite qu'un grep sans correspondance (pipefail) ne tue tout
    # le script de mise à jour à cause de "set -e" (voir sudoers.sh).
    db_user="$(grep '^DB_USERNAME=' "${OBIORA_INSTALL_DIR}/.env" 2>/dev/null | cut -d= -f2- || true)"
    db_pass="$(grep '^DB_PASSWORD=' "${OBIORA_INSTALL_DIR}/.env" 2>/dev/null | cut -d= -f2- || true)"
    db_name="$(grep '^DB_DATABASE=' "${OBIORA_INSTALL_DIR}/.env" 2>/dev/null | cut -d= -f2- || true)"

    if [[ -z "${db_user}" || -z "${db_name}" ]]; then
        warn "Sync serveur maître ignoré (.env incomplet)"
        return
    fi

    mysql -N -u "${db_user}" -p"${db_pass}" "${db_name}" 2>/dev/null <<SQL || true
UPDATE servers SET ip_address='${ip}', hostname='${hostname}' WHERE is_master = 1;
SQL

    info "Serveur maître : ${ip} (${hostname})"
}
