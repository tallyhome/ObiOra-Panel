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
        sudo -u "${OBIORA_USER}" git fetch origin
        if [[ -n "${OBIORA_TAG}" ]]; then
            sudo -u "${OBIORA_USER}" git checkout "tags/${OBIORA_TAG}"
        else
            sudo -u "${OBIORA_USER}" git checkout "${OBIORA_BRANCH}"
            sudo -u "${OBIORA_USER}" git pull origin "${OBIORA_BRANCH}"
        fi
    else
        rm -rf "${OBIORA_INSTALL_DIR}"
        sudo -u "${OBIORA_USER}" git clone --depth 1 \
            ${OBIORA_TAG:+--branch "${OBIORA_TAG}"} \
            "${OBIORA_REPO}" "${OBIORA_INSTALL_DIR}"
    fi

    success "Code source installé dans ${OBIORA_INSTALL_DIR}"
}

setup_laravel() {
    info "Configuration Laravel..."

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
    sudo -u "${OBIORA_USER}" composer install --no-dev --optimize-autoloader --no-interaction
    sudo -u "${OBIORA_USER}" php artisan key:generate --force
    sudo -u "${OBIORA_USER}" npm ci --ignore-scripts 2>/dev/null || sudo -u "${OBIORA_USER}" npm install
    sudo -u "${OBIORA_USER}" npm run build

    # Migrations
    sudo -u "${OBIORA_USER}" php artisan migrate --force
    sudo -u "${OBIORA_USER}" php artisan db:seed --force

    # Permissions storage
    chmod -R 775 storage bootstrap/cache
    chown -R "${OBIORA_USER}:www-data" storage bootstrap/cache 2>/dev/null || \
    chown -R "${OBIORA_USER}:nginx" storage bootstrap/cache

    sudo -u "${OBIORA_USER}" php artisan storage:link 2>/dev/null || true
    sudo -u "${OBIORA_USER}" php artisan optimize

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
