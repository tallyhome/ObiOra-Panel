#!/usr/bin/env bash
# Détection OS — Debian, Ubuntu, AlmaLinux, Rocky Linux

detect_os() {
    load_os_vars
    echo "${OBIORA_OS_ID}|${OBIORA_OS_VERSION}"
}

is_supported_os() {
    load_os_vars

    case "${OBIORA_OS_ID}" in
        debian)
            [[ "${OBIORA_OS_VERSION}" == "11" || "${OBIORA_OS_VERSION}" == "12" ]]
            ;;
        ubuntu)
            [[ "${OBIORA_OS_VERSION}" == "20.04" || "${OBIORA_OS_VERSION}" == "22.04" || "${OBIORA_OS_VERSION}" == "24.04" ]]
            ;;
        almalinux|rocky)
            local major="${OBIORA_OS_VERSION%%.*}"
            [[ "${major}" == "8" || "${major}" == "9" || "${major}" == "10" ]]
            ;;
        *)
            return 1
            ;;
    esac
}

assert_supported_os() {
    load_os_vars
    if ! is_supported_os; then
        die "OS non supporté: ${OBIORA_OS_NAME} (${OBIORA_OS_ID} ${OBIORA_OS_VERSION}). Supporté: Debian 11/12, Ubuntu 20.04/22.04/24.04, AlmaLinux/Rocky 8/9/10"
    fi
    info "OS détecté: ${OBIORA_OS_NAME} (${OBIORA_OS_ID} ${OBIORA_OS_VERSION})"
}

setup_php_repo() {
    case "${OBIORA_OS_ID}" in
        ubuntu)
            if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
                apt-get install -y -qq software-properties-common
                add-apt-repository -y ppa:ondrej/php
                apt-get update -qq
            fi
            ;;
        debian)
            if ! grep -rq "packages.sury.org" /etc/apt/ 2>/dev/null; then
                apt-get install -y -qq ca-certificates apt-transport-https lsb-release curl
                curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php.gpg
                echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
                apt-get update -qq
            fi
            ;;
        almalinux|rocky)
            if ! rpm -q remi-release &>/dev/null; then
                local major="${OBIORA_OS_VERSION%%.*}"
                dnf install -y -q "https://rpms.remirepo.net/enterprise/remi-release-${major}.rpm" || true
            fi
            dnf module reset php -y -q || true
            dnf module enable php:remi-8.3 -y -q || true
            ;;
    esac
}
