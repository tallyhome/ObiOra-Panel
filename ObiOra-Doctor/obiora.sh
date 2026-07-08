#!/usr/bin/env bash
# Obiora Doctor - Lanceur principal (un seul fichier a executer)
#
# Usage:
#   ./obiora.sh              # Menu interactif
#   ./obiora.sh scan         # Commande directe
#   ./obiora.sh web          # Interface web securisee (localhost)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if ! command -v python3 >/dev/null 2>&1; then
    printf '\033[31m✖ Python 3 introuvable\033[0m\n' >&2
    exit 127
fi

# Sans argument = menu principal
if [ $# -eq 0 ]; then
    exec python3 "$SCRIPT_DIR/bin/obiora-doctor.py" menu
fi

case "$1" in
    menu|scan|list-modules|interactive|watch|compare|history|clean|api|web|bench|agent|rescue|reboot-monitor|--help|--version|-h)
        exec python3 "$SCRIPT_DIR/bin/obiora-doctor.py" "$@"
        ;;
    *)
        exec python3 "$SCRIPT_DIR/bin/obiora-doctor.py" scan "$@"
        ;;
esac
