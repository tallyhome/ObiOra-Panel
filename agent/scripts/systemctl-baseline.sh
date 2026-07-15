#!/usr/bin/env bash
# Unités panel connues — fallback si list-units complet échoue (PHP-FPM sans accès direct)
set -euo pipefail

units=(
    nginx.service
    php-fpm.service
    php8.3-fpm.service
    php8.2-fpm.service
    mariadb.service
    mysqld.service
    mysql.service
    redis.service
    redis-server.service
    obiora-queue.service
    obiora-agent.service
    obiora-reverb.service
    obiora-scheduler.timer
    docker.service
    fail2ban.service
    firewalld.service
    supervisord.service
)

for unit in "${units[@]}"; do
    systemctl list-units "${unit}" --all --no-pager --no-legend 2>/dev/null || true
done
