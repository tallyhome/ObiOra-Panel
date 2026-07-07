#!/usr/bin/env bash
# Rétablit MariaDB si le drop-in ObiOra Docker l'a fait planter.
set -euo pipefail

dropin="/etc/my.cnf.d/obiora-docker.cnf"
svc=""

if systemctl is-active mariadb &>/dev/null; then
    svc="mariadb"
elif systemctl is-active mysqld &>/dev/null; then
    svc="mysqld"
fi

if [[ -n "${svc}" ]]; then
    echo "OK:mariadb actif (${svc})"
    exit 0
fi

if [[ -f "${dropin}" ]]; then
    echo "WARN: MariaDB inactif — suppression de ${dropin}"
    rm -f "${dropin}"
fi

if systemctl start mariadb 2>/dev/null; then
    echo "OK:mariadb redémarré"
    exit 0
fi

if systemctl start mysqld 2>/dev/null; then
    echo "OK:mysqld redémarré"
    exit 0
fi

echo "ERREUR: impossible de démarrer MariaDB — vérifiez: journalctl -u mariadb -n 30" >&2
exit 1
