#!/usr/bin/env bash
# Crée un site web Nginx + PHP-FPM (ObiOra)
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

DOMAIN="${1:-}"
PHP_VERSION="${2:-8.3}"
WEB_ROOT="${3:-/var/www}"

if [[ -z "${DOMAIN}" ]]; then
    echo "Usage: website-create.sh <domain> [php_version] [web_root]" >&2
    exit 1
fi

if ! [[ "${DOMAIN}" =~ ^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$ ]]; then
    echo "Domaine invalide: ${DOMAIN}" >&2
    exit 1
fi

SAFE_NAME="${DOMAIN//./-}"
SITE_DIR="${WEB_ROOT}/${DOMAIN}"
PUBLIC_DIR="${SITE_DIR}/public"

if [[ -d /etc/nginx/sites-available ]]; then
    mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
    NGINX_CONF="/etc/nginx/sites-available/obiora-${SAFE_NAME}"
    NGINX_ENABLED="/etc/nginx/sites-enabled/obiora-${SAFE_NAME}"
else
    mkdir -p /etc/nginx/conf.d
    NGINX_CONF="/etc/nginx/conf.d/obiora-${SAFE_NAME}.conf"
    NGINX_ENABLED=""
fi

META_FILE="${SITE_DIR}/.obiora.json"

WEB_USER="nginx"
if id apache &>/dev/null; then
    WEB_USER="apache"
elif id www-data &>/dev/null; then
    WEB_USER="www-data"
fi

PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
for sock in /run/php-fpm/www.sock /var/run/php-fpm/www.sock /var/opt/remi/php${PHP_VERSION}/run/php-fpm/www.sock; do
    if [[ -S "${sock}" ]]; then
        PHP_SOCK="${sock}"
        break
    fi
done

mkdir -p "${WEB_ROOT}" "${PUBLIC_DIR}"

cat > "${PUBLIC_DIR}/index.php" <<'PHP'
<?php
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>ObiOra</title></head><body>';
echo '<h1>Site créé avec ObiOra Panel</h1>';
echo '<p>PHP ' . PHP_VERSION . '</p>';
echo '</body></html>';
PHP

chown -R "${WEB_USER}:${WEB_USER}" "${SITE_DIR}"
chmod -R 755 "${SITE_DIR}"

cat > "${NGINX_CONF}" <<NGINX
# ObiOra managed - domain: ${DOMAIN}
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    root ${PUBLIC_DIR};
    index index.php index.html;

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
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

if [[ -n "${NGINX_ENABLED}" ]]; then
    ln -sf "${NGINX_CONF}" "${NGINX_ENABLED}"
fi

nginx -t
systemctl reload nginx

cat > "${META_FILE}" <<JSON
{
    "domain": "${DOMAIN}",
    "php_version": "${PHP_VERSION}",
    "document_root": "${PUBLIC_DIR}",
    "nginx_config": "${NGINX_CONF}",
    "ssl_enabled": false,
    "created_at": "$(date -Iseconds)"
}
JSON

echo "OK:${PUBLIC_DIR}:${NGINX_CONF}"
