#!/usr/bin/env bash
# ObiOra Doctor & Suite — installation unifiée (Doctor + Crash Analyzer)
# Usage: curl -fsSL https://panel/install/doctor-suite.sh | sudo OBIORA_PANEL_URL=... OBIORA_SERVER_ID=... OBIORA_AGENT_TOKEN=... bash
set -euo pipefail

PANEL_URL="${OBIORA_PANEL_URL:?OBIORA_PANEL_URL requis}"
SERVER_ID="${OBIORA_SERVER_ID:?OBIORA_SERVER_ID requis}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?OBIORA_AGENT_TOKEN requis}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || echo "/tmp")"

echo "=== ObiOra Doctor & Suite — installation ==="

export OBIORA_PANEL_URL="${PANEL_URL}"
export OBIORA_SERVER_ID="${SERVER_ID}"
export OBIORA_AGENT_TOKEN="${AGENT_TOKEN}"

if [[ -f "${SCRIPT_DIR}/bootstrap-doctor-agent.sh" ]]; then
    bash "${SCRIPT_DIR}/bootstrap-doctor-agent.sh"
elif curl -fsSL "${PANEL_URL%/}/install/doctor-agent.sh" -o /tmp/obiora-doctor-install.sh 2>/dev/null; then
    bash /tmp/obiora-doctor-install.sh
    rm -f /tmp/obiora-doctor-install.sh
else
    echo "ERREUR: impossible de récupérer le script Doctor" >&2
    exit 1
fi

if [[ -f "${SCRIPT_DIR}/install-crash-analyzer.sh" ]]; then
    bash "${SCRIPT_DIR}/install-crash-analyzer.sh"
elif curl -fsSL "${PANEL_URL%/}/install/crash-analyzer.sh" -o /tmp/obiora-crash-install.sh 2>/dev/null; then
    bash /tmp/obiora-crash-install.sh
    rm -f /tmp/obiora-crash-install.sh
else
    echo "AVERTISSEMENT: Crash Analyzer non installé (script introuvable)" >&2
fi

echo "OK: ObiOra Doctor & Crash Analyzer installés"
