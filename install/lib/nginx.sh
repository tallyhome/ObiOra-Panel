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

    systemctl_enable_start php8.3-fpm 2>/dev/null || systemctl_enable_start php-fpm

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

    setup_web_permissions
    setup_selinux_panel

    nginx -t
    systemctl_enable_start nginx

    success "Nginx configuré pour ${server_name}"
}
