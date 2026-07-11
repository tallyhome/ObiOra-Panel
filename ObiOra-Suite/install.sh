#!/usr/bin/env bash
# Crash Hunter installer for AlmaLinux 10 / KVM / Virtualizor
set -euo pipefail

INSTALL_DIR="/opt/crashhunter"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="${SCRIPT_DIR}/crashhunter"

echo "=== Crash Hunter Installer ==="

if [[ $EUID -ne 0 ]]; then
  echo "Ce script doit être exécuté en root."
  exit 1
fi

# Dépendances système
if command -v dnf &>/dev/null; then
  dnf install -y python3.12 python3.12-pip ipmitool smartmontools sysstat lm_sensors \
    gdb perf fio stress-ng zstd 2>/dev/null || true
fi

mkdir -p "$INSTALL_DIR" /dev/shm/crashhunter-ring
rsync -a --delete \
  --exclude='.pytest_cache' \
  --exclude='__pycache__' \
  --exclude='*.pyc' \
  --exclude='.venv' \
  "$SOURCE_DIR/" "$INSTALL_DIR/src/"

python3.12 -m venv "$INSTALL_DIR/venv"
"$INSTALL_DIR/venv/bin/pip" install --upgrade pip
"$INSTALL_DIR/venv/bin/pip" install -e "$INSTALL_DIR/src"

mkdir -p "$INSTALL_DIR/data/ring" "$INSTALL_DIR/data/state" "$INSTALL_DIR/data/incidents" "$INSTALL_DIR/reports" "$INSTALL_DIR/logs"

# Configuration YAML
if [[ ! -f "$INSTALL_DIR/config.yaml" ]]; then
  cp "$INSTALL_DIR/src/crashhunter/config/default.yaml" "$INSTALL_DIR/config.yaml"
fi

cp "$SOURCE_DIR/systemd/crashhunter.service" /etc/systemd/system/crashhunter.service

# Conserver l'ancien script bash comme fallback
if [[ -f "${SCRIPT_DIR}/crashhunter.sh" ]]; then
  cp "${SCRIPT_DIR}/crashhunter.sh" "$INSTALL_DIR/crashhunter.sh.legacy"
  chmod +x "$INSTALL_DIR/crashhunter.sh.legacy"
fi

systemctl daemon-reload
systemctl enable crashhunter
systemctl restart crashhunter

echo ""
echo "Crash Hunter installé dans $INSTALL_DIR"
echo "  Status:  systemctl status crashhunter"
echo "  Logs:    journalctl -u crashhunter -f"
echo "  CLI:     $INSTALL_DIR/venv/bin/crashhunter status"
echo "  Rapport: $INSTALL_DIR/venv/bin/crashhunter report --force"
echo "  OVH:     $INSTALL_DIR/venv/bin/crashhunter ovh-report"
echo "  Web UI:  $INSTALL_DIR/venv/bin/crashhunter web"
echo ""
echo "Remote Witness (VPS): crashhunter witness-server"
echo "Remote Witness (dédié): activer witness.enabled dans config.yaml"
echo ""
