#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

if [[ -d /etc/csf ]]; then
    echo "OK:csf (déjà installé)"
    exit 0
fi
cd /usr/src
curl -fsSL https://download.configserver.com/csf.tgz -o csf.tgz
tar -xzf csf.tgz
cd csf
bash install.sh

echo "OK:csf"