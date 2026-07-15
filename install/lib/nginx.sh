#!/usr/bin/env bash
# Configuration Nginx

OBIORA_DOMAIN="${OBIORA_DOMAIN:-}"

setup_nginx() {
    info "Configuration Nginx..."

    local server_name
    if [[ -n "${OBIORA_DOMAIN}" ]]; then
        server_name="${OBIORA_DOMAIN}"
    else
        server_name="$(get_server_ip)"
    fi

    # Désactiver le vhost par défaut RHEL/AlmaLinux (conflit server_name "_")
    if [[ -f /etc/nginx/conf.d/default.conf ]]; then
        mv -f /etc/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf.obiora-bak
    fi

    # Debian/Ubuntu utilisent sites-available/enabled ; RHEL/AlmaLinux conf.d
    local nginx_conf
    if [[ -d /etc/nginx/sites-available ]]; then
        nginx_conf="/etc/nginx/sites-available/obiora-panel"
    else
        mkdir -p /etc/nginx/conf.d
        nginx_conf="/etc/nginx/conf.d/obiora-panel.conf"
    fi

    # Permissions AVANT le démarrage PHP-FPM : /opt/obiora-panel est en 750,
    # sans groupe obiora le worker répond « File not found » à index.php.
    setup_web_permissions
    setup_selinux_panel

    systemctl_quiet_enable_start php8.3-fpm 2>/dev/null || systemctl_quiet_enable_start php-fpm

    local php_sock
    php_sock="$(detect_php_fpm_socket)"
    info "Socket PHP-FPM : ${php_sock}"

    cat > "${nginx_conf}" <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${server_name};

    root ${OBIORA_INSTALL_DIR}/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:${php_sock};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

    # Activation du site (Debian : lien symbolique ; RHEL : déjà dans conf.d)
    if [[ -d /etc/nginx/sites-enabled ]]; then
        ln -sf "${nginx_conf}" /etc/nginx/sites-enabled/obiora-panel
        rm -f /etc/nginx/sites-enabled/default
    fi

    nginx_remove_foreign_default_servers "${nginx_conf}"

    if ! nginx -t >> "${OBIORA_LOG_FILE}" 2>&1; then
        error "Configuration Nginx invalide — voir ${OBIORA_LOG_FILE}"
        nginx -t
        return 1
    fi

    systemctl_quiet_enable_start nginx

    # Recharge les workers PHP-FPM après usermod (groupe obiora).
    systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true

    success "Nginx configuré pour ${server_name}"
}

verify_panel_http() {
    local url code body
    url="http://127.0.0.1/up"
    info "Vérification HTTP du panel (${url})..."

    for _ in 1 2 3 4 5; do
        code="$(curl -sS -o /tmp/obiora-verify-body.txt -w '%{http_code}' --max-time 10 "${url}" 2>/dev/null || echo "000")"
        body="$(cat /tmp/obiora-verify-body.txt 2>/dev/null || true)"
        rm -f /tmp/obiora-verify-body.txt

        if [[ "${code}" == "200" ]]; then
            success "Panel accessible (HTTP ${code})"
            return 0
        fi

        if [[ "${body}" == *"File not found"* ]]; then
            warn "PHP-FPM ne trouve pas index.php — redémarrage des services web..."
            setup_web_permissions
            systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
            systemctl restart nginx
            sleep 2
            continue
        fi

        sleep 2
    done

    warn "Le panel ne répond pas correctement (HTTP ${code:-?})."
    warn "Consultez : ${OBIORA_LOG_FILE}, journalctl -u nginx -u php-fpm --no-pager -n 50"
    warn "Test manuel : curl -v http://127.0.0.1/up && ls -la ${OBIORA_INSTALL_DIR}/public/index.php"
    return 1
}
