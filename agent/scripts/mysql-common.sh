#!/usr/bin/env bash
# Fonctions communes MySQL/MariaDB ObiOra
set -euo pipefail

mysql_root_exec() {
    if [[ "${EUID}" -eq 0 ]]; then
        _mysql_as_root "$@"
        return
    fi

    if sudo -n true 2>/dev/null; then
        sudo -n bash -c "$(declare -f _mysql_as_root); _mysql_as_root $(printf '%q ' "$@")"
        return
    fi

    _mysql_as_root "$@"
}

_mysql_as_root() {
    if mysql -u root -e "SELECT 1" &>/dev/null; then
        mysql -u root "$@"
    elif [[ -f /etc/obiora/mysql-admin.cnf ]]; then
        mysql --defaults-file=/etc/obiora/mysql-admin.cnf "$@"
    elif [[ -f /root/.obiora_mysql.cnf ]]; then
        mysql --defaults-file=/root/.obiora_mysql.cnf "$@"
    else
        mysql "$@"
    fi
}

validate_db_name() {
    local name="${1}"
    [[ "${name}" =~ ^[a-zA-Z0-9_]{1,64}$ ]] || return 1
}

validate_db_user() {
    local user="${1}"
    [[ "${user}" =~ ^[a-zA-Z0-9_]{1,32}$ ]] || return 1
}

generate_db_password() {
    openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24
}
