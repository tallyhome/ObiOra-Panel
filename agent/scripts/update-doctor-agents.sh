#!/usr/bin/env bash
# ObiOra — mise à jour des agents Doctor & Suite (sans réinstallation complète)
# Préserve config.yaml CrashHunter et relance les services.
set -euo pipefail

PANEL_URL="${OBIORA_PANEL_URL:?OBIORA_PANEL_URL requis}"
SERVER_ID="${OBIORA_SERVER_ID:?OBIORA_SERVER_ID requis}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:?OBIORA_AGENT_TOKEN requis}"

UPDATE_DOCTOR="${OBIORA_UPDATE_DOCTOR:-no}"
UPDATE_CRASH_ANALYZER="${OBIORA_UPDATE_CRASH_ANALYZER:-yes}"
UPDATE_CRASH_HUNTER="${OBIORA_UPDATE_CRASH_HUNTER:-yes}"

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n env \
        OBIORA_PANEL_URL="${PANEL_URL}" \
        OBIORA_SERVER_ID="${SERVER_ID}" \
        OBIORA_AGENT_TOKEN="${AGENT_TOKEN}" \
        OBIORA_UPDATE_DOCTOR="${UPDATE_DOCTOR}" \
        OBIORA_UPDATE_CRASH_ANALYZER="${UPDATE_CRASH_ANALYZER}" \
        OBIORA_UPDATE_CRASH_HUNTER="${UPDATE_CRASH_HUNTER}" \
        bash "$0" "$@"
fi

export OBIORA_PANEL_URL="${PANEL_URL}"
export OBIORA_SERVER_ID="${SERVER_ID}"
export OBIORA_AGENT_TOKEN="${AGENT_TOKEN}"
export OBIORA_PRESERVE_CONFIG=yes

echo "=== ObiOra — mise à jour agents (Doctor=${UPDATE_DOCTOR}, CrashAnalyzer=${UPDATE_CRASH_ANALYZER}, CrashHunter=${UPDATE_CRASH_HUNTER}) ==="

if [[ "${UPDATE_CRASH_HUNTER}" == "yes" ]]; then
    if curl -fsSL "${PANEL_URL%/}/install/crash-hunter.sh" -o /tmp/obiora-crashhunter-update.sh 2>/dev/null; then
        bash /tmp/obiora-crashhunter-update.sh
        rm -f /tmp/obiora-crashhunter-update.sh
    else
        echo "ERREUR: script CrashHunter introuvable" >&2
        exit 1
    fi
fi

if [[ "${UPDATE_CRASH_ANALYZER}" == "yes" ]]; then
    if curl -fsSL "${PANEL_URL%/}/install/crash-analyzer.sh" -o /tmp/obiora-crash-update.sh 2>/dev/null; then
        bash /tmp/obiora-crash-update.sh
        rm -f /tmp/obiora-crash-update.sh
    else
        echo "AVERTISSEMENT: Crash Analyzer non mis à jour" >&2
    fi
fi

if [[ "${UPDATE_DOCTOR}" == "yes" ]]; then
    if curl -fsSL "${PANEL_URL%/}/install/doctor-agent.sh" -o /tmp/obiora-doctor-update.sh 2>/dev/null; then
        bash /tmp/obiora-doctor-update.sh
        rm -f /tmp/obiora-doctor-update.sh
    else
        echo "AVERTISSEMENT: Doctor agent non mis à jour" >&2
    fi
fi

echo "OK: Mise à jour agents terminée"
