#!/usr/bin/env bash
# Installe ObiOra-Doctor Python pour scans securite complets
set -euo pipefail

INSTALL_DIR="${OBIORA_DOCTOR_DIR:-/opt/obiora-doctor}"
SOURCE_DIR="${OBIORA_DOCTOR_SOURCE:-}"

if [[ -z "${SOURCE_DIR}" && -n "${BASH_SOURCE[0]:-}" && "${BASH_SOURCE[0]}" != bash ]]; then
    _script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ -d "${_script_dir}/../../ObiOra-Doctor" ]]; then
        SOURCE_DIR="$(cd "${_script_dir}/../../ObiOra-Doctor" && pwd)"
    fi
fi

if [[ -z "${SOURCE_DIR}" || ! -d "${SOURCE_DIR}" ]]; then
    echo "ERREUR: source ObiOra-Doctor introuvable (OBIORA_DOCTOR_SOURCE)" >&2
    exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
    echo "ERREUR: python3 requis pour ObiOra-Doctor" >&2
    exit 1
fi

mkdir -p "${INSTALL_DIR}"
rsync -a --delete \
    --exclude reports --exclude cache --exclude .tmp-reports \
    --exclude __pycache__ --exclude .pytest_cache \
    "${SOURCE_DIR}/" "${INSTALL_DIR}/" 2>/dev/null \
    || cp -a "${SOURCE_DIR}/." "${INSTALL_DIR}/"

chmod +x "${INSTALL_DIR}/obiora.sh" "${INSTALL_DIR}/obiora-doctor.sh" 2>/dev/null || true
find "${INSTALL_DIR}/bin" -type f -name '*.py' -exec chmod +x {} + 2>/dev/null || true

echo "OK: ObiOra-Doctor installe dans ${INSTALL_DIR}"
