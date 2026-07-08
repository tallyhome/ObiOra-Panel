#!/usr/bin/env bash
# ObiOra Panel — Désinstallation

set -euo pipefail

OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"

echo "ATTENTION: Cette action va supprimer ObiOra Panel et ses services."
read -rp "Continuer ? [y/N] " confirm

[[ "${confirm}" == "y" || "${confirm}" == "Y" ]] || exit 0

systemctl stop obiora-queue obiora-scheduler.timer obiora-agent 2>/dev/null || true
systemctl disable obiora-queue obiora-scheduler.timer obiora-agent 2>/dev/null || true

rm -f /etc/systemd/system/obiora-*.service /etc/systemd/system/obiora-*.timer
rm -f /etc/nginx/sites-enabled/obiora-panel /etc/nginx/conf.d/obiora-panel.conf
rm -f /etc/nginx/sites-available/obiora-panel

systemctl daemon-reload
systemctl reload nginx 2>/dev/null || true

rm -rf "${OBIORA_INSTALL_DIR}"

echo "ObiOra Panel désinstallé. Les paquets système (nginx, php, mariadb) sont conservés."
