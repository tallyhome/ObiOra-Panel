#!/usr/bin/env bash
# Désinstallation Docker CE (AlmaLinux/RHEL/Debian)
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
            docker_uninstall "${pct}" "${msg}" >/dev/null 2>&1 || true
    fi
}

on_error() {
    echo "ERREUR: échec désinstallation Docker (ligne ${1})" >&2
    progress 100 "Échec de la désinstallation Docker"
    exit 1
}

trap 'on_error ${LINENO}' ERR

if ! command -v docker &>/dev/null; then
    progress 100 "Docker n'est pas installé"
    echo "OK:Docker déjà absent"
    exit 0
fi

progress 10 "Arrêt des conteneurs…"
if docker ps -q 2>/dev/null | grep -q .; then
    docker ps -q | xargs docker stop 2>/dev/null || true
fi

progress 25 "Arrêt du service Docker…"
systemctl stop docker docker.socket containerd 2>/dev/null || true
systemctl disable docker docker.socket 2>/dev/null || true

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

progress 45 "Suppression des paquets Docker…"

case "${PKG_MGR}" in
    apt)
        DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge \
            docker.io docker-doc docker-compose docker-compose-v2 podman-docker containerd runc \
            docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin 2>/dev/null || true
        apt-get autoremove -y 2>/dev/null || true
        ;;
    dnf)
        dnf remove -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin 2>/dev/null || true
        ;;
    *)
        echo "ERREUR: gestionnaire de paquets non supporté" >&2
        exit 1
        ;;
esac

progress 85 "Nettoyage…"
rm -f /etc/yum.repos.d/docker-ce.repo 2>/dev/null || true

progress 100 "Docker désinstallé (données conservées dans /var/lib/docker)"
echo "OK:Docker désinstallé"
