#!/usr/bin/env bash
# Retourne l'IP du bridge Docker pour les apps conteneurisées.
# NE MODIFIE PAS la configuration MariaDB (bind-address) — trop risqué en production.
set -euo pipefail

docker_ip="172.17.0.1"
if ip link show docker0 &>/dev/null; then
    docker_ip="$(ip -4 addr show docker0 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1 | head -1 || echo "172.17.0.1")"
    docker_ip="${docker_ip:-172.17.0.1}"
fi

echo "OK:${docker_ip}"
