#!/usr/bin/env bash
# Ajoute la clé publique ObiOra Panel dans /root/.ssh/authorized_keys (serveur local)
set -euo pipefail

PUBLIC_KEY="${1:-}"
MARKER="${2:-obiora-panel}"

if [[ -z "${PUBLIC_KEY}" ]]; then
    echo "Usage: ssh-authorize-panel-key.sh <public_key> [marker]" >&2
    exit 1
fi

mkdir -p /root/.ssh
chmod 700 /root/.ssh

if ! grep -qF "${PUBLIC_KEY}" /root/.ssh/authorized_keys 2>/dev/null; then
    echo "${PUBLIC_KEY}" >> /root/.ssh/authorized_keys
fi

chmod 600 /root/.ssh/authorized_keys
echo "OBIORA_KEY_INSTALLED:${MARKER}"
