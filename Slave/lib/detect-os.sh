#!/usr/bin/env bash

assert_supported_os() {
    load_os_vars
    case "${OBIORA_OS_ID}" in
        debian) [[ "${OBIORA_OS_VERSION}" == "11" || "${OBIORA_OS_VERSION}" == "12" ]] || die "Debian ${OBIORA_OS_VERSION} non supporté" ;;
        ubuntu) [[ "${OBIORA_OS_VERSION}" == "20.04" || "${OBIORA_OS_VERSION}" == "22.04" || "${OBIORA_OS_VERSION}" == "24.04" ]] || die "Ubuntu ${OBIORA_OS_VERSION} non supporté" ;;
        almalinux|rocky)
            local m="${OBIORA_OS_VERSION%%.*}"
            [[ "${m}" == "8" || "${m}" == "9" || "${m}" == "10" ]] || die "Version non supportée"
            ;;
        *) die "OS non supporté: ${OBIORA_OS_NAME}" ;;
    esac
    info "OS: ${OBIORA_OS_NAME}"
}

setup_php_repo() {
    case "${OBIORA_OS_ID}" in
        ubuntu)
            apt-get install -y -qq software-properties-common
            add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
            apt-get update -qq
            ;;
        debian)
            apt-get install -y -qq ca-certificates curl
            curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php.gpg 2>/dev/null || true
            echo "deb https://packages.sury.org/php/ $(. /etc/os-release && echo "${VERSION_CODENAME}") main" > /etc/apt/sources.list.d/php.list 2>/dev/null || true
            apt-get update -qq
            ;;
        almalinux|rocky)
            local m="${OBIORA_OS_VERSION%%.*}"
            dnf install -y -q "https://rpms.remirepo.net/enterprise/remi-release-${m}.rpm" 2>/dev/null || true
            dnf module enable php:remi-8.3 -y -q 2>/dev/null || true
            ;;
    esac
}
