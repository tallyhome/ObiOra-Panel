#!/usr/bin/env bash
# Fonctions communes MySQL/MariaDB ObiOra
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

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
    if mysql -u root -e "SELECT 1" &>/dev/null 2>&1; then
        mysql -u root "$@"
        return
    fi

    if [[ -f /etc/obiora/mysql-admin.cnf ]]; then
        mysql --defaults-file=/etc/obiora/mysql-admin.cnf "$@"
        return
    fi

    if [[ -f /root/.obiora_mysql.cnf ]]; then
        mysql --defaults-file=/root/.obiora_mysql.cnf "$@"
        return
    fi

    if [[ -f /root/.obiora_db_credentials ]]; then
        local user pass
        user="$(grep '^DB_USERNAME=' /root/.obiora_db_credentials | cut -d= -f2-)"
        pass="$(grep '^DB_PASSWORD=' /root/.obiora_db_credentials | cut -d= -f2-)"
        user="${user:-obiora}"
        if [[ -n "${pass}" ]]; then
            MYSQL_PWD="${pass}" mysql -u "${user}" "$@"
            return
        fi
    fi

    mysql "$@" || {
        echo "ERREUR: impossible de se connecter à MySQL (vérifiez que mariadb/mysqld est démarré : systemctl start mariadb)" >&2
        return 1
    }
}

mysqldump_root_exec() {
    if [[ "${EUID}" -eq 0 ]]; then
        _mysqldump_as_root "$@"
        return
    fi

    if sudo -n true 2>/dev/null; then
        sudo -n bash -c "$(declare -f _mysqldump_as_root _mysql_as_root); _mysqldump_as_root $(printf '%q ' "$@")"
        return
    fi

    _mysqldump_as_root "$@"
}

_mysqldump_bin() {
    if command -v mysqldump &>/dev/null; then
        command -v mysqldump
        return
    fi

    if command -v mariadb-dump &>/dev/null; then
        command -v mariadb-dump
        return
    fi

    echo "ERREUR: mysqldump introuvable (dnf install -y mariadb)" >&2
    return 1
}

_mysqldump_as_root() {
    local dump_bin
    dump_bin="$(_mysqldump_bin)" || return 1

    if mysql -u root -e "SELECT 1" &>/dev/null 2>&1; then
        "${dump_bin}" -u root "$@"
        return
    fi

    if [[ -f /etc/obiora/mysql-admin.cnf ]]; then
        "${dump_bin}" --defaults-file=/etc/obiora/mysql-admin.cnf "$@"
        return
    fi

    if [[ -f /root/.obiora_mysql.cnf ]]; then
        "${dump_bin}" --defaults-file=/root/.obiora_mysql.cnf "$@"
        return
    fi

    if [[ -f /root/.obiora_db_credentials ]]; then
        local user pass
        user="$(grep '^DB_USERNAME=' /root/.obiora_db_credentials | cut -d= -f2-)"
        pass="$(grep '^DB_PASSWORD=' /root/.obiora_db_credentials | cut -d= -f2-)"
        user="${user:-obiora}"
        if [[ -n "${pass}" ]]; then
            MYSQL_PWD="${pass}" "${dump_bin}" -u "${user}" "$@"
            return
        fi
    fi

    "${dump_bin}" "$@" || {
        echo "ERREUR: mysqldump impossible (vérifiez mariadb/mysqld et les droits root)" >&2
        return 1
    }
}

ensure_mysql_service_running() {
    if systemctl is-active --quiet mariadb 2>/dev/null || systemctl is-active --quiet mysqld 2>/dev/null; then
        return 0
    fi

    echo "ERREUR: MariaDB/MySQL n'est pas démarré — lancez : sudo systemctl start mariadb" >&2
    return 1
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
