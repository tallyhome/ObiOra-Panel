#!/usr/bin/env bash
# Obiora Doctor - delegue vers le lanceur principal obiora.sh

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "$SCRIPT_DIR/obiora.sh" "$@"
