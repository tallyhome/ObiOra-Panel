#!/usr/bin/env bash
# Redémarre le serveur dans 1 minute — exécuté en root via sudo
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: privilèges root requis" >&2
    exit 1
fi

delay="${1:-60}"
message="${2:-Redémarrage planifié par ObiOra Panel}"

if command -v shutdown &>/dev/null; then
    shutdown -r +$((delay / 60 > 0 ? delay / 60 : 1)) "${message}" 2>/dev/null \
        || shutdown -r "+1" "${message}"
    echo "OK:reboot planifié dans ${delay}s"
    exit 0
fi

if command -v systemctl &>/dev/null; then
    systemctl reboot
    echo "OK:reboot immédiat"
    exit 0
fi

echo "ERREUR: commande de redémarrage indisponible" >&2
exit 1
