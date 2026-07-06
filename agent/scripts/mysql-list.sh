#!/usr/bin/env bash
# Liste les bases utilisateur MySQL/MariaDB
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=mysql-common.sh
source "${SCRIPT_DIR}/mysql-common.sh"

OUTPUT="$(mysql_root_exec -N -e "
SELECT SCHEMA_NAME
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME NOT IN ('mysql','information_schema','performance_schema','sys')
ORDER BY SCHEMA_NAME;
" 2>&1)" || {
    echo "ERR:${OUTPUT}" >&2
    exit 1
}

if [[ -z "${OUTPUT}" ]]; then
    echo "OK:"
    exit 0
fi

echo "OK:${OUTPUT//$'\n'/,}"
