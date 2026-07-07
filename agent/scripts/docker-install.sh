#!/usr/bin/env bash
# Installation Docker CE (AlmaLinux/RHEL/Debian)
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
OBIORA_USER="${OBIORA_USER:-obiora}"

progress() {
    local pct="$1"
    local msg="$2"

    echo "[${pct}%] ${msg}"

    if [[ -f "${OBIORA_INSTALL_DIR}/artisan" ]]; then
        sudo -u "${OBIORA_USER}" php "${OBIORA_INSTALL_DIR}/artisan" obiora:progress \
            docker_install "${pct}" "${msg}" >/dev/null 2>&1 || true
    fi
}

on_error() {
    echo "ERREUR: échec installation Docker (ligne ${1})" >&2
    progress 100 "Échec de l'installation Docker"
    exit 1
}

trap 'on_error ${LINENO}' ERR

if command -v docker &>/dev/null && docker info &>/dev/null 2>&1; then
    progress 100 "Docker déjà installé et actif"
    echo "OK:Docker déjà installé et actif"
    exit 0
fi

progress 8 "Vérification du système…"

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
        progress 20 "Mise à jour des dépôts APT…"
        apt-get update -qq
        progress 45 "Installation des paquets Docker…"
        DEBIAN_FRONTEND=noninteractive apt-get install -y docker.io docker-compose-plugin
        ;;
    dnf)
        progress 15 "Nettoyage du cache DNF…"
        dnf clean packages 2>/dev/null || true
        rm -rf /var/cache/dnf/*/packages/*.rpm 2>/dev/null || true

        progress 25 "Installation des plugins DNF…"
        dnf install -y dnf-plugins-core

        if [[ ! -f /etc/yum.repos.d/docker-ce.repo ]]; then
            progress 35 "Ajout du dépôt Docker CE…"
            dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
        fi

        progress 55 "Téléchargement et installation Docker (peut prendre plusieurs minutes)…"
        dnf install -y --refresh docker-ce docker-ce-cli containerd.io docker-compose-plugin
        ;;
    *)
        echo "ERREUR: gestionnaire de paquets non supporté pour Docker" >&2
        exit 1
        ;;
esac

progress 82 "Activation du service Docker…"
systemctl enable docker >/dev/null 2>&1
systemctl start docker >/dev/null 2>&1

progress 90 "Configuration des permissions utilisateurs…"
for u in "${OBIORA_USER}" apache nginx www-data; do
    id "${u}" &>/dev/null && usermod -aG docker "${u}" 2>/dev/null || true
done

progress 96 "Vérification finale…"
if docker info &>/dev/null; then
    version="$(docker version --format '{{.Server.Version}}' 2>/dev/null || echo 'ok')"
    progress 100 "Docker installé (v${version})"
    echo "OK:Docker installé (${version})"
else
    echo "ERREUR: Docker installé mais daemon inaccessible" >&2
    exit 1
fi
