#!/usr/bin/env bash
# ObiOra — Installation Netdata
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if command -v netdata &>/dev/null; then
    echo "OK:netdata (déjà installé)"
    exit 0
fi

curl -fsSL https://get.netdata.cloud/kickstart.sh | bash -s -- --non-interactive --disable-telemetry

systemctl enable netdata 2>/dev/null || true
systemctl start netdata 2>/dev/null || true

echo "OK:netdata"
