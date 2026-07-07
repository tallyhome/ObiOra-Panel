#!/usr/bin/env bash
# Active l'écoute MariaDB pour les conteneurs Docker (bridge docker0).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=mysql-common.sh
source "${SCRIPT_DIR}/mysql-common.sh"

docker_ip="172.17.0.1"
if ip link show docker0 &>/dev/null; then
    docker_ip="$(ip -4 addr show docker0 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1 | head -1 || echo "172.17.0.1")"
    docker_ip="${docker_ip:-172.17.0.1}"
fi

dropin="/etc/my.cnf.d/obiora-docker.cnf"

if [[ ! -f "${dropin}" ]] || ! grep -q "${docker_ip}" "${dropin}" 2>/dev/null; then
    cat > "${dropin}" <<EOF
# ObiOra Panel — accès MariaDB depuis les conteneurs Docker
[mysqld]
bind-address = 0.0.0.0
EOF
    if systemctl restart mariadb 2>/dev/null || systemctl restart mysqld 2>/dev/null; then
        sleep 1
    fi
    if ! systemctl is-active mariadb &>/dev/null && ! systemctl is-active mysqld &>/dev/null; then
        rm -f "${dropin}"
        systemctl start mariadb 2>/dev/null || systemctl start mysqld 2>/dev/null || true
        echo "WARN: redémarrage MariaDB échoué — droits Docker OK mais vérifiez bind-address manuellement" >&2
    fi
fi

echo "OK:${docker_ip}"
