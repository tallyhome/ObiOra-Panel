#!/usr/bin/env bash

install_slave_packages() {
    info "Installation des paquets..."
    case "$(get_pkg_manager)" in
        apt)
            apt-get update -qq
            setup_php_repo
            pkg_install php8.3-cli curl git ca-certificates openssl
            ;;
        dnf)
            setup_php_repo
            pkg_install php-cli curl git ca-certificates openssl
            ;;
    esac
    success "Paquets installés"
}

create_slave_user() {
    if ! id "${OBIORA_AGENT_USER}" &>/dev/null; then
        useradd -r -m -d "/home/${OBIORA_AGENT_USER}" -s /sbin/nologin "${OBIORA_AGENT_USER}"
    fi
}
