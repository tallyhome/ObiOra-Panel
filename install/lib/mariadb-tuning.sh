#!/usr/bin/env bash
# Tuning MariaDB pour petits VPS (ObiOra Panel).
set -euo pipefail

OBIORA_MARIADB_DROPIN="${OBIORA_MARIADB_DROPIN:-/etc/my.cnf.d/obiora-panel.cnf}"

mariadb_buffer_pool_mb() {
    local ram_mb="${1:-4096}"

    if (( ram_mb <= 2048 )); then
        echo 128
    elif (( ram_mb <= 4096 )); then
        echo 256
    elif (( ram_mb <= 8192 )); then
        echo 512
    else
        echo 1024
    fi
}

tune_mariadb_for_panel() {
    local ram_mb buffer_pool_mb dir

    if [[ ! -r /proc/meminfo ]]; then
        warn "Impossible de lire /proc/meminfo — tuning MariaDB ignoré"
        return 0
    fi

    ram_mb="$(awk '/MemTotal:/ {print int($2/1024)}' /proc/meminfo)"
    buffer_pool_mb="$(mariadb_buffer_pool_mb "${ram_mb}")"
    dir="$(dirname "${OBIORA_MARIADB_DROPIN}")"
    mkdir -p "${dir}"

    cat > "${OBIORA_MARIADB_DROPIN}" <<CNF
# ObiOra Panel — mémoire MariaDB (RAM détectée : ${ram_mb} MiB)
# Regénéré : $(date -u +%Y-%m-%dT%H:%M:%SZ)
[mysqld]
innodb_buffer_pool_size = ${buffer_pool_mb}M
innodb_buffer_pool_instances = 1
max_connections = 50
performance_schema = OFF
table_open_cache = 256
CNF

    chmod 644 "${OBIORA_MARIADB_DROPIN}"
    info "MariaDB tuning : innodb_buffer_pool_size=${buffer_pool_mb}M (${OBIORA_MARIADB_DROPIN})"

    if systemctl is-active mariadb &>/dev/null; then
        systemctl restart mariadb
    elif systemctl is-active mysqld &>/dev/null; then
        systemctl restart mysqld
    else
        warn "MariaDB/MySQL inactif — redémarrage ignoré"
    fi
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    # shellcheck source=common.sh
    source "$(dirname "$0")/common.sh" 2>/dev/null || true
    tune_mariadb_for_panel
fi
