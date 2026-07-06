#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup-common.sh
source "${SCRIPT_DIR}/backup-common.sh"

ensure_backup_root

shopt -s nullglob
for file in "${BACKUP_ROOT}"/*; do
    [[ -f "${file}" ]] || continue
    base="$(basename "${file}")"
    size="$(stat -c%s "${file}" 2>/dev/null || stat -f%z "${file}")"
    mtime="$(date -r "${file}" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || date -r "${file} " '+%Y-%m-%d %H:%M:%S')"
    type="files"
    [[ "${base}" == *-db-* ]] && type="database"
    [[ "${base}" == *-full-* ]] && type="full"
    echo "ROW:${base}:${size}:${mtime}:${type}"
done

echo "OK"
