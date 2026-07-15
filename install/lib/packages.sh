#!/usr/bin/env bash
# Installation des paquets système

INSTALL_DOCKER="${INSTALL_DOCKER:-false}"
INSTALL_FTP="${INSTALL_FTP:-false}"

install_base_packages() {
    run_quiet "Synchronisation des dépôts (apt/dnf update)" pkg_update

    if [[ "${OBIORA_FULL_SYSTEM_UPGRADE}" == "true" ]]; then
        install_substep "Mise à niveau système complet (grub, kernel…) — mode install complète"
        run_quiet "Mise à niveau système complet (grub, kernel…)" pkg_upgrade
    else
        install_substep "Mode standard — paquets ObiOra uniquement (sans upgrade système)"
    fi

    install_substep "Configuration du dépôt PHP 8.3…"
    setup_php_repo >> "${OBIORA_LOG_FILE}" 2>&1

    case "$(get_pkg_manager)" in
        apt)
            run_quiet "Installation Nginx, PHP 8.3, MariaDB, Redis…" pkg_install \
                nginx mariadb-server redis-server \
                php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
                php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-redis \
                supervisor certbot python3-certbot-nginx \
                fail2ban ufw \
                unzip curl wget git ca-certificates gnupg tar gzip openssh-client

            if ! command -v node &>/dev/null; then
                install_substep "Node.js 20 LTS (NodeSource)…"
                curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >> "${OBIORA_LOG_FILE}" 2>&1
                run_quiet "Installation Node.js" pkg_install nodejs
            fi
            ;;
        dnf)
            run_quiet "Installation Nginx, PHP 8.3, MariaDB, Redis…" pkg_install \
                nginx mariadb-server redis \
                php php-fpm php-cli php-mysqlnd php-mbstring \
                php-xml php-curl php-zip php-bcmath php-intl php-pecl-redis \
                supervisor certbot python3-certbot-nginx \
                fail2ban firewalld \
                unzip curl wget git ca-certificates tar gzip openssh-clients

            if ! command -v node &>/dev/null; then
                install_substep "Node.js 20 LTS (NodeSource)…"
                curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - >> "${OBIORA_LOG_FILE}" 2>&1
                run_quiet "Installation Node.js" pkg_install nodejs
            fi
            ;;
    esac

    if ! command -v composer &>/dev/null; then
        install_substep "Composer (getcomposer.org)…"
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
            >> "${OBIORA_LOG_FILE}" 2>&1
    fi

    # Docker (optionnel)
    if [[ "${INSTALL_DOCKER}" == "true" ]]; then
        install_docker
    fi

    # FTP (optionnel)
    if [[ "${INSTALL_FTP}" == "true" ]]; then
        case "$(get_pkg_manager)" in
            apt) pkg_install vsftpd ;;
            dnf) pkg_install vsftpd ;;
        esac
    fi

    install_step_redisplay
    success "Paquets installés"
}

install_docker() {
    info "Installation de Docker..."
    if command -v docker &>/dev/null; then
        info "Docker déjà installé"
        return
    fi

    case "$(get_pkg_manager)" in
        apt)
            pkg_install docker.io docker-compose-plugin
            ;;
        dnf)
            pkg_install dnf-plugins-core
            dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
            pkg_install docker-ce docker-ce-cli containerd.io docker-compose-plugin
            ;;
    esac

    systemctl enable docker
    systemctl start docker
    usermod -aG docker "${OBIORA_USER}" 2>/dev/null || true
}
