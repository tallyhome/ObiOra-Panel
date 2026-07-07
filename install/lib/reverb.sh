#!/usr/bin/env bash
# Configuration Laravel Reverb (Phase 11) — opt-in via OBIORA_REALTIME_ENABLED

setup_reverb() {
    if [[ "${OBIORA_REALTIME_ENABLED:-false}" != "true" ]]; then
        info "Reverb desactive (OBIORA_REALTIME_ENABLED=false)."
        return 0
    fi

    info "Configuration Laravel Reverb..."

    local env_file="${OBIORA_INSTALL_DIR}/.env"
    if [[ ! -f "${env_file}" ]]; then
        warn "Fichier .env introuvable, Reverb non configure."
        return 0
    fi

    local app_key app_secret
    app_key="$(openssl rand -hex 16 2>/dev/null || head -c 16 /dev/urandom | xxd -p)"
    app_secret="$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | xxd -p)"

    grep -q '^BROADCAST_CONNECTION=' "${env_file}" \
        && sed -i 's/^BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=reverb/' "${env_file}" \
        || echo 'BROADCAST_CONNECTION=reverb' >> "${env_file}"

    for kv in \
        "OBIORA_REALTIME_ENABLED=true" \
        "REVERB_APP_ID=obiora-panel" \
        "REVERB_APP_KEY=${app_key}" \
        "REVERB_APP_SECRET=${app_secret}" \
        "REVERB_SERVER_HOST=127.0.0.1" \
        "REVERB_SERVER_PORT=8080" \
        "REVERB_HOST=${OBIORA_DOMAIN:-127.0.0.1}" \
        "REVERB_PORT=8080" \
        "REVERB_SCHEME=http" \
        "VITE_REVERB_APP_KEY=${app_key}" \
        "VITE_REVERB_HOST=${OBIORA_DOMAIN:-127.0.0.1}" \
        "VITE_REVERB_PORT=8080" \
        "VITE_REVERB_SCHEME=http"
    do
        key="${kv%%=*}"
        if grep -q "^${key}=" "${env_file}"; then
            sed -i "s|^${key}=.*|${kv}|" "${env_file}"
        else
            echo "${kv}" >> "${env_file}"
        fi
    done

    cat > /etc/systemd/system/obiora-reverb.service <<SERVICE
[Unit]
Description=ObiOra Panel Reverb WebSocket Server
After=network.target redis.service mariadb.service

[Service]
User=${OBIORA_USER}
Group=${OBIORA_GROUP}
Restart=always
ExecStart=/usr/bin/php ${OBIORA_INSTALL_DIR}/artisan reverb:start --host=127.0.0.1 --port=8080
WorkingDirectory=${OBIORA_INSTALL_DIR}

[Install]
WantedBy=multi-user.target
SERVICE

    systemctl daemon-reload
    systemctl_enable_start obiora-reverb

    success "Reverb configure et demarre (port 8080 interne)"
}

append_reverb_nginx() {
    if [[ "${OBIORA_REALTIME_ENABLED:-false}" != "true" ]]; then
        return 0
    fi

    local nginx_conf
    if [[ -f /etc/nginx/sites-available/obiora-panel ]]; then
        nginx_conf="/etc/nginx/sites-available/obiora-panel"
    elif [[ -f /etc/nginx/conf.d/obiora-panel.conf ]]; then
        nginx_conf="/etc/nginx/conf.d/obiora-panel.conf"
    else
        return 0
    fi

    if grep -q 'location /app' "${nginx_conf}"; then
        return 0
    fi

    info "Ajout proxy WebSocket Reverb dans Nginx..."

    sed -i '/location ~ \\\\.php/i \
    location /app {\
        proxy_pass http://127.0.0.1:8080;\
        proxy_http_version 1.1;\
        proxy_set_header Upgrade $http_upgrade;\
        proxy_set_header Connection "Upgrade";\
        proxy_set_header Host $host;\
        proxy_read_timeout 86400;\
    }\
' "${nginx_conf}" 2>/dev/null || warn "Proxy WebSocket Reverb a ajouter manuellement (voir PHASE-11.md)"

    nginx -t && systemctl reload nginx
}
