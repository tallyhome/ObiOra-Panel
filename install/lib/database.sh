#!/usr/bin/env bash
# Configuration MariaDB/MySQL

OBIORA_DB_NAME="${OBIORA_DB_NAME:-obiora_panel}"
OBIORA_DB_USER="${OBIORA_DB_USER:-obiora}"
OBIORA_DB_PASS="${OBIORA_DB_PASS:-}"

mysql_exec() {
    if mysql -u root -e "SELECT 1" &>/dev/null; then
        mysql -u root "$@"
    elif [[ -n "${OBIORA_DB_PASS}" ]] && mysql -u root -p"${OBIORA_DB_PASS}" -e "SELECT 1" &>/dev/null; then
        mysql -u root -p"${OBIORA_DB_PASS}" "$@"
    else
        mysql "$@"
    fi
}

setup_database() {
    info "Configuration de la base de données..."

    # Réutiliser le mot de passe existant si une install précédente l'a déjà
    # défini, sinon MySQL et .env se désynchronisent (Access denied).
    if [[ -z "${OBIORA_DB_PASS}" && -f /root/.obiora_db_credentials ]]; then
        OBIORA_DB_PASS="$(grep '^DB_PASSWORD=' /root/.obiora_db_credentials | cut -d= -f2-)"
    fi

    if [[ -z "${OBIORA_DB_PASS}" ]]; then
        OBIORA_DB_PASS="$(generate_password)"
    fi

    systemctl_enable_start mariadb 2>/dev/null || systemctl_enable_start mysqld

    # ALTER USER force la synchro du mot de passe même si l'utilisateur existe
    # déjà. On couvre localhost ET 127.0.0.1 (connexion TCP depuis Laravel).
    mysql_exec <<SQL
CREATE DATABASE IF NOT EXISTS \`${OBIORA_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${OBIORA_DB_USER}'@'localhost' IDENTIFIED BY '${OBIORA_DB_PASS}';
ALTER USER '${OBIORA_DB_USER}'@'localhost' IDENTIFIED BY '${OBIORA_DB_PASS}';
CREATE USER IF NOT EXISTS '${OBIORA_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${OBIORA_DB_PASS}';
ALTER USER '${OBIORA_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${OBIORA_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${OBIORA_DB_NAME}\`.* TO '${OBIORA_DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${OBIORA_DB_NAME}\`.* TO '${OBIORA_DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

    cat > /root/.obiora_db_credentials <<CREDS
DB_DATABASE=${OBIORA_DB_NAME}
DB_USERNAME=${OBIORA_DB_USER}
DB_PASSWORD=${OBIORA_DB_PASS}
CREDS
    chmod 600 /root/.obiora_db_credentials

    mkdir -p /etc/obiora
    chmod 755 /etc/obiora

    success "Base de données ${OBIORA_DB_NAME} configurée"
}
