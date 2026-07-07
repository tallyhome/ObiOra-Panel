#!/usr/bin/env bash
# Installation Docker CE (AlmaLinux/RHEL/Debian)
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

OBIORA_USER="${OBIORA_USER:-obiora}"

if command -v docker &>/dev/null && docker info &>/dev/null 2>&1; then
    echo "OK:Docker déjà installé et actif"
    exit 0
fi

detect_pkg_manager() {
    if command -v dnf &>/dev/null; then
        echo "dnf"
    elif command -v apt-get &>/dev/null; then
        echo "apt"
    else
        echo "unknown"
    fi
}

PKG_MGR="$(detect_pkg_manager)"

case "${PKG_MGR}" in
    apt)
        apt-get update -qq
        DEBIAN_FRONTEND=noninteractive apt-get install -y docker.io docker-compose-plugin
        ;;
    dnf)
        dnf install -y dnf-plugins-core
        if [[ ! -f /etc/yum.repos.d/docker-ce.repo ]]; then
            dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
        fi
        dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
        ;;
    *)
        echo "ERREUR: gestionnaire de paquets non supporté pour Docker" >&2
        exit 1
        ;;
esac

systemctl enable docker
systemctl start docker

for u in "${OBIORA_USER}" apache nginx www-data; do
    id "${u}" &>/dev/null && usermod -aG docker "${u}" 2>/dev/null || true
done

if docker info &>/dev/null; then
    echo "OK:Docker installé ($(docker version --format '{{.Server.Version}}' 2>/dev/null || echo 'ok'))"
else
    echo "ERREUR: Docker installé mais daemon inaccessible" >&2
    exit 1
fi
