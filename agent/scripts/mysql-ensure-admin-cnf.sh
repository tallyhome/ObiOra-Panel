#!/usr/bin/env bash
# Crée /etc/obiora/mysql-admin.cnf pour les scripts backup/mysqldump (root via socket)
set -euo pipefail

socket=""
for candidate in /var/lib/mysql/mysql.sock /run/mysqld/mysqld.sock /tmp/mysql.sock; do
    if [[ -S "${candidate}" ]]; then
        socket="${candidate}"
        break
    fi
done

if [[ -z "${socket}" ]]; then
    echo "ERREUR: socket MySQL introuvable — MariaDB démarré ?" >&2
    exit 1
fi

mkdir -p /etc/obiora
cat > /etc/obiora/mysql-admin.cnf <<CNF
[client]
user=root
socket=${socket}
CNF
chmod 600 /etc/obiora/mysql-admin.cnf
echo "OK:/etc/obiora/mysql-admin.cnf"
