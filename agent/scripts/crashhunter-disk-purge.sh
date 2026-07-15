#!/usr/bin/env bash
# Purge CrashHunter disk (bundles / reports) — exécuté via sudo NOPASSWD
# Usage:
#   crashhunter-disk-purge.sh audit
#   crashhunter-disk-purge.sh keep 3
#   crashhunter-disk-purge.sh all
set -euo pipefail

BASE="${CRASHHUNTER_DIR:-/opt/crashhunter}"
ACTION="${1:-audit}"
KEEP="${2:-3}"

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n "$(readlink -f "${BASH_SOURCE[0]}")" "$@"
fi

dir_size_bytes() {
    local path="$1"
    if [[ -d "${path}" ]]; then
        du -sb "${path}" 2>/dev/null | awk '{print $1}'
    else
        echo 0
    fi
}

count_entries() {
    local path="$1"
    if [[ -d "${path}" ]]; then
        find "${path}" -mindepth 1 -maxdepth 1 2>/dev/null | wc -l | tr -d ' '
    else
        echo 0
    fi
}

audit_json() {
    local bundles reports logs data total
    bundles="$(dir_size_bytes "${BASE}/bundles")"
    reports="$(dir_size_bytes "${BASE}/reports")"
    logs="$(dir_size_bytes "${BASE}/logs")"
    data="$(dir_size_bytes "${BASE}/data")"
    total="$(dir_size_bytes "${BASE}")"
    local bundle_count report_count
    bundle_count="$(count_entries "${BASE}/bundles")"
    report_count="$(count_entries "${BASE}/reports")"
    printf '{"path":"%s","total_bytes":%s,"bundles_bytes":%s,"reports_bytes":%s,"logs_bytes":%s,"data_bytes":%s,"bundle_count":%s,"report_count":%s}\n' \
        "${BASE}" "${total}" "${bundles}" "${reports}" "${logs}" "${data}" "${bundle_count}" "${report_count}"
}

prune_keep() {
    local dir="$1"
    local keep="$2"
    local deleted=0
    if [[ ! -d "${dir}" ]]; then
        echo 0
        return
    fi
    # shellcheck disable=SC2012
    mapfile -t entries < <(ls -1dt "${dir}"/*/ 2>/dev/null || true)
    local i=0
    for entry in "${entries[@]}"; do
        i=$((i + 1))
        if [[ ${i} -gt ${keep} ]]; then
            rm -rf "${entry}"
            deleted=$((deleted + 1))
        fi
    done
    # Also drop orphaned archives
    if [[ ${keep} -eq 0 ]]; then
        find "${dir}" -mindepth 1 -maxdepth 1 -type f \( -name '*.tar' -o -name '*.tar.gz' -o -name '*.tar.zst' -o -name '*.zip' \) -delete 2>/dev/null || true
    fi
    echo "${deleted}"
}

case "${ACTION}" in
    audit)
        audit_json
        ;;
    keep)
        if [[ ! "${KEEP}" =~ ^[0-9]+$ ]]; then
            echo "ERREUR: keep doit être un entier" >&2
            exit 1
        fi
        bundles_deleted="$(prune_keep "${BASE}/bundles" "${KEEP}")"
        reports_deleted="$(prune_keep "${BASE}/reports" "${KEEP}")"
        echo "OK:keep=${KEEP}:bundles_deleted=${bundles_deleted}:reports_deleted=${reports_deleted}"
        audit_json
        ;;
    all)
        bundles_deleted="$(prune_keep "${BASE}/bundles" 0)"
        reports_deleted="$(prune_keep "${BASE}/reports" 0)"
        # Empty leftover files in those dirs
        find "${BASE}/bundles" -mindepth 1 -delete 2>/dev/null || true
        find "${BASE}/reports" -mindepth 1 -delete 2>/dev/null || true
        echo "OK:all:bundles_deleted=${bundles_deleted}:reports_deleted=${reports_deleted}"
        audit_json
        ;;
    *)
        echo "Usage: $0 {audit|keep N|all}" >&2
        exit 1
        ;;
esac
