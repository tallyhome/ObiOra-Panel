#!/usr/bin/env bash
# Installe ou réactive le timer systemd du panel (sans crontab manuel)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OBIORA_INSTALL_DIR="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"

exec bash "${SCRIPT_DIR}/lib/scheduler.sh"
