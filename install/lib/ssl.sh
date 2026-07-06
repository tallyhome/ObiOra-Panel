#!/usr/bin/env bash
# SSL Let's Encrypt

OBIORA_SSL_EMAIL="${OBIORA_SSL_EMAIL:-}"

setup_ssl() {
    if [[ -z "${OBIORA_DOMAIN}" || -z "${OBIORA_SSL_EMAIL}" ]]; then
        warn "SSL ignoré (domaine ou email non fourni). Utilisez --domain et --email pour activer Let's Encrypt."
        return
    fi

    info "Configuration SSL Let's Encrypt pour ${OBIORA_DOMAIN}..."

    certbot --nginx \
        -d "${OBIORA_DOMAIN}" \
        --non-interactive \
        --agree-tos \
        -m "${OBIORA_SSL_EMAIL}" \
        --redirect

    # Mise à jour APP_URL
    if [[ -f "${OBIORA_INSTALL_DIR}/.env" ]]; then
        sed -i "s|^APP_URL=.*|APP_URL=https://${OBIORA_DOMAIN}|" "${OBIORA_INSTALL_DIR}/.env"
        cd "${OBIORA_INSTALL_DIR}"
        sudo -u "${OBIORA_USER}" php artisan config:clear
    fi

    success "SSL configuré pour https://${OBIORA_DOMAIN}"
}
