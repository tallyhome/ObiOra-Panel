#!/usr/bin/env bash
# Configuration MariaDB/MySQL

OBIORA_DB_NAME="${OBIORA_DB_NAME:-obiora_panel}"
OBIORA_DB_USER="${OBIORA_DB_USER:-obiora}"
OBIORA_DB_PASS="${OBIORA_DB_PASS:-}"

setup_database() {
    info "Configuration de la base de données..."

    if [[ -z "${OBIORA_DB_PASS}" ]]; then
        OBIORA_DB_PASS="$(generate_password)"
    fi

    systemctl_enable_start mariadb 2>/dev/null || systemctl_enable_start mysqld

    # Sécurisation initiale si première install
    if [[ ! -f /root/.obiora_mysql_configured ]]; then
        mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${OBIORA_DB_PASS}';" 2>/dev/null || \
        mysql -e "UPDATE mysql.user SET authentication_string=PASSWORD('${OBIORA_DB_PASS}') WHERE User='root' AND Host='localhost';" 2>/dev/null || true
        mysql -e "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true
        mysql -e "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
        touch /root/.obiora_mysql_configured
    fi

    mysql -u root -p"${OBIORA_DB_PASS}" 2>/dev/null <<SQL || mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${OBIORA_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${OBIORA_DB_USER}'@'localhost' IDENTIFIED BY '${OBIORA_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${OBIORA_DB_NAME}\`.* TO '${OBIORA_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

    # Sauvegarde credentials
    cat > /root/.obiora_db_credentials <<CREDS
DB_DATABASE=${OBIORA_DB_NAME}
DB_USERNAME=${OBIORA_DB_USER}
DB_PASSWORD=${OBIORA_DB_PASS}
CREDS
    chmod 600 /root/.obiora_db_credentials

    success "Base de données ${OBIORA_DB_NAME} configurée"
}
