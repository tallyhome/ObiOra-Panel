#!/usr/bin/env bash
# Met à jour les paquets système (apt/dnf) — exécuté en root via sudo
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERREUR: privilèges root requis" >&2
    exit 1
fi

if command -v apt-get &>/dev/null; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get upgrade -y -qq
    echo "OK:apt upgrade terminé"
    exit 0
fi

if command -v dnf &>/dev/null; then
    dnf upgrade -y -q --best
    echo "OK:dnf upgrade terminé"
    exit 0
fi

if command -v yum &>/dev/null; then
    yum upgrade -y -q
    echo "OK:yum upgrade terminé"
    exit 0
fi

echo "ERREUR: gestionnaire de paquets non supporté (apt/dnf/yum)" >&2
exit 1
