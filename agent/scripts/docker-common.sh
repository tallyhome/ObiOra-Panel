#!/usr/bin/env bash
# Fonctions communes Docker ObiOra
set -euo pipefail

docker_cmd() {
    if command -v docker &>/dev/null; then
        if docker info &>/dev/null 2>&1; then
            docker "$@"
            return
        fi
    fi

    if [[ "${EUID}" -eq 0 ]]; then
        docker "$@"
    elif sudo -n true 2>/dev/null; then
        sudo -n docker "$@"
    else
        echo "Docker indisponible ou permissions insuffisantes" >&2
        exit 1
    fi
}

validate_container_ref() {
    local ref="${1}"
    [[ "${ref}" =~ ^[a-f0-9]{12,64}$ ]] && return 0
    [[ "${ref}" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$ ]] && return 0
    return 1
}

validate_image_ref() {
    local ref="${1}"
    [[ "${ref}" =~ ^[a-zA-Z0-9][a-zA-Z0-9._/:@-]{0,255}$ ]]
}
