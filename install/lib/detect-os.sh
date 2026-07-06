# Phase 2 — Détection OS (Debian, Ubuntu, AlmaLinux, Rocky Linux)

detect_os() {
    if [[ -f /etc/os-release ]]; then
        # shellcheck source=/dev/null
        . /etc/os-release
        echo "${ID}|${VERSION_ID}"
    else
        echo "unknown|unknown"
    fi
}

is_supported_os() {
    local id version
    IFS='|' read -r id version <<< "$(detect_os)"

    case "${id}" in
        debian)
            [[ "${version}" == "11" || "${version}" == "12" ]]
            ;;
        ubuntu)
            [[ "${version}" == "20.04" || "${version}" == "22.04" || "${version}" == "24.04" ]]
            ;;
        almalinux|rocky)
            [[ "${version%%.*}" == "8" || "${version%%.*}" == "9" || "${version%%.*}" == "10" ]]
            ;;
        *)
            return 1
            ;;
    esac
}
