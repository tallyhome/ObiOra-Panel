#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if [[ -f /etc/webmin/miniserv.conf ]]; then
    echo "OK:webmin (déjà installé)"
    exit 0
fi
curl -fsSL https://raw.githubusercontent.com/webmin/webmin/master/setup.sh -o /tmp/webmin-setup.sh
bash /tmp/webmin-setup.sh --unattended
rm -f /tmp/webmin-setup.sh

echo "OK:webmin"