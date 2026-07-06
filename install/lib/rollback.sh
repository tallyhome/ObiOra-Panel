#!/usr/bin/env bash
# Snapshot et rollback

OBIORA_ROLLBACK_SNAPSHOT=""

create_snapshot() {
    local step="$1"
    OBIORA_SNAPSHOT_DIR="${OBIORA_SNAPSHOT_DIR}/$(date +%Y%m%d_%H%M%S)_${step}"
    mkdir -p "${OBIORA_SNAPSHOT_DIR}"

    if [[ -d "${OBIORA_INSTALL_DIR}" ]]; then
        cp -a "${OBIORA_INSTALL_DIR}/.env" "${OBIORA_SNAPSHOT_DIR}/.env" 2>/dev/null || true
    fi

    if [[ -f /root/.obiora_db_credentials ]]; then
        cp /root/.obiora_db_credentials "${OBIORA_SNAPSHOT_DIR}/"
    fi

    OBIORA_ROLLBACK_SNAPSHOT="${OBIORA_SNAPSHOT_DIR}"
    info "Snapshot créé: ${OBIORA_ROLLBACK_SNAPSHOT}"
}

rollback() {
    warn "Rollback en cours depuis ${OBIORA_ROLLBACK_SNAPSHOT}..."

    if [[ -n "${OBIORA_ROLLBACK_SNAPSHOT}" && -d "${OBIORA_ROLLBACK_SNAPSHOT}" ]]; then
        if [[ -f "${OBIORA_ROLLBACK_SNAPSHOT}/.env" && -d "${OBIORA_INSTALL_DIR}" ]]; then
            cp "${OBIORA_ROLLBACK_SNAPSHOT}/.env" "${OBIORA_INSTALL_DIR}/.env"
        fi
    fi

    error "Installation échouée. Consultez ${OBIORA_LOG_FILE}"
    exit 1
}

trap 'rollback' ERR
