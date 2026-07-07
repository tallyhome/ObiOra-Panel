#!/usr/bin/env bash
# ObiOra — helper installation Docker
set -euo pipefail

obiora_require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        exec sudo -n bash "$0" "$@"
    fi
}

obiora_require_docker() {
    if ! command -v docker &>/dev/null; then
        echo "Docker requis. Installez Docker via le module ObiOra." >&2
        exit 1
    fi
}

obiora_auth_user() {
    echo "${OBIORA_APP_USERNAME:-admin}"
}

obiora_auth_pass() {
    local pass="${OBIORA_APP_PASS:-}"

    if [[ -z "${pass}" ]]; then
        echo "ERREUR: mot de passe requis (wizard d'installation)." >&2
        exit 1
    fi

    echo "${pass}"
}

obiora_docker_install() {
    local slug="$1"
    local image="$2"
    local host_port="$3"
    local internal_port="${host_port}"
    local name="obiora-${slug}"
    local docker_args=()

    obiora_require_root
    obiora_require_docker

    if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
        echo "OK:${slug} (déjà installé)"
        exit 0
    fi

    shift 3
    if [[ $# -gt 0 && "${1}" =~ ^[0-9]+$ ]]; then
        internal_port="$1"
        shift
    fi
    docker_args=("$@")

    mkdir -p "/var/lib/obiora/${slug}"
    chown -R 1000:1000 "/var/lib/obiora/${slug}" 2>/dev/null || chmod -R 0777 "/var/lib/obiora/${slug}"

    docker run -d \
        --name "${name}" \
        --restart unless-stopped \
        -p "${host_port}:${internal_port}" \
        -v "/var/lib/obiora/${slug}:/config" \
        -e PUID=1000 -e PGID=1000 \
        "${docker_args[@]}" \
        "${image}"

    echo "OK:${slug} (port ${host_port})"
}

obiora_docker_uninstall() {
    local slug="$1"
    local name="obiora-${slug}"

    obiora_require_root
    docker stop "${name}" 2>/dev/null || true
    docker rm "${name}" 2>/dev/null || true
    echo "OK:${slug} removed"
}

obiora_seed_deluge_auth() {
    local data_dir="$1"
    local user="$2"
    local pass="$3"

    mkdir -p "${data_dir}/.config/deluge"
    printf '%s:%s:10\n' "${user}" "${pass}" > "${data_dir}/.config/deluge/auth"
}

obiora_seed_nzbget_auth() {
    local data_dir="$1"
    local user="$2"
    local pass="$3"
    local conf="${data_dir}/nzbget.conf"

    mkdir -p "${data_dir}"

    if [[ ! -f "${conf}" ]]; then
        cat > "${conf}" <<EOF
MainDir=/downloads
ControlUsername=${user}
ControlPassword=${pass}
EOF
    else
        sed -i '/^ControlUsername=/d' "${conf}" 2>/dev/null || true
        sed -i '/^ControlPassword=/d' "${conf}" 2>/dev/null || true
        {
            echo "ControlUsername=${user}"
            echo "ControlPassword=${pass}"
        } >> "${conf}"
    fi
}

obiora_seed_sabnzbd_auth() {
    local data_dir="$1"
    local user="$2"
    local pass="$3"

    mkdir -p "${data_dir}"
    cat > "${data_dir}/sabnzbd.ini" <<EOF
[misc]
username = ${user}
password = ${pass}
host = 0.0.0.0
port = 8080
EOF
}

obiora_seed_calibreweb_auth() {
    local data_dir="$1"
    local user="$2"
    local pass="$3"

    mkdir -p "${data_dir}"
    cat > "${data_dir}/obiora-admin.env" <<EOF
OBIORA_ADMIN_USER=${user}
OBIORA_ADMIN_PASS=${pass}
EOF
}
