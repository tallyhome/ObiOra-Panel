#!/usr/bin/env bash
# Ajoute les droits Docker et TCP à un utilisateur MySQL existant.
set -euo pipefail

DB_USER="${1:-}"
DB_PASS="${2:-}"
DB_NAME="${3:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=mysql-common.sh
source "${SCRIPT_DIR}/mysql-common.sh"

if [[ -z "${DB_USER}" || -z "${DB_PASS}" || -z "${DB_NAME}" ]]; then
    echo "Usage: mysql-grant-docker.sh <username> <password> <database>" >&2
    exit 1
fi

validate_db_user "${DB_USER}" || { echo "Nom utilisateur invalide" >&2; exit 1; }
validate_db_name "${DB_NAME}" || { echo "Nom de base invalide" >&2; exit 1; }

docker_ip="$("${SCRIPT_DIR}/mysql-docker-enable.sh" 2>/dev/null | sed -n 's/^OK://p' | head -1)"
docker_ip="${docker_ip:-172.17.0.1}"

mysql_root_exec <<SQL
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'172.%' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'172.%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'172.%';
FLUSH PRIVILEGES;
SQL

echo "OK:${DB_USER}:${docker_ip}"
