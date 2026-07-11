#!/usr/bin/env bash
# Services systemd (panel, queue, scheduler, agent)

setup_systemd() {
    info "Configuration des services systemd..."

    # Service principal PHP-FPM géré par le paquet

    # Queue worker
    cat > /etc/systemd/system/obiora-queue.service <<SERVICE
[Unit]
Description=ObiOra Panel Queue Worker
After=network.target mariadb.service redis.service

[Service]
User=${OBIORA_USER}
Group=${OBIORA_GROUP}
Restart=always
ExecStart=/usr/bin/php ${OBIORA_INSTALL_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=${OBIORA_INSTALL_DIR}

[Install]
WantedBy=multi-user.target
SERVICE

    # shellcheck source=/dev/null
    source "${OBIORA_INSTALL_DIR}/install/lib/scheduler.sh"
    ensure_panel_scheduler || die "Impossible de démarrer obiora-scheduler.timer (vérifiez: systemctl status obiora-scheduler.timer)"

    # Agent
    if [[ -f "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" ]]; then
        sed "s|/opt/obiora-panel|${OBIORA_INSTALL_DIR}|g" \
            "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" \
            > /etc/systemd/system/obiora-agent.service
        ensure_agent_executables
    fi

    systemctl daemon-reload
    systemctl_enable_start obiora-queue

    if [[ -f /etc/systemd/system/obiora-agent.service ]]; then
        systemctl_enable_start obiora-agent
    fi

    if [[ -f /etc/systemd/system/obiora-reverb.service ]]; then
        systemctl_enable_start obiora-reverb 2>/dev/null || warn "Reverb non demarre"
    fi

    systemctl_enable_start redis 2>/dev/null || systemctl_enable_start redis-server 2>/dev/null || true
    systemctl enable supervisor 2>/dev/null && systemctl start supervisor 2>/dev/null || warn "Supervisor non démarré (optionnel)"

    ensure_boot_service_order

    success "Services systemd démarrés"
}

ensure_boot_service_order() {
    info "Ordre de démarrage au boot (MariaDB/Redis avant le panel)..."

    local dropin='[Unit]
After=mariadb.service mysqld.service redis.service redis-server.service network-online.target
Wants=mariadb.service mysqld.service redis.service redis-server.service
'

    local svc
    for svc in php-fpm php8.3-fpm php82-php-fpm nginx; do
        if systemctl list-unit-files --type=service 2>/dev/null | grep -q "^${svc}.service"; then
            mkdir -p "/etc/systemd/system/${svc}.service.d"
            printf '%s' "${dropin}" > "/etc/systemd/system/${svc}.service.d/obiora-after-deps.conf"
        fi
    done

    chmod +x "${OBIORA_INSTALL_DIR}/install/lib/panel-boot-wait.sh"

    cat > /etc/systemd/system/obiora-panel-ready.service <<SERVICE
[Unit]
Description=ObiOra Panel warm-up after boot
After=mariadb.service mysqld.service redis.service redis-server.service network-online.target
Wants=mariadb.service mysqld.service redis.service redis-server.service

[Service]
Type=oneshot
RemainAfterExit=yes
Environment=OBIORA_INSTALL_DIR=${OBIORA_INSTALL_DIR}
Environment=OBIORA_USER=${OBIORA_USER}
ExecStart=/bin/bash ${OBIORA_INSTALL_DIR}/install/lib/panel-boot-wait.sh
TimeoutStartSec=120

[Install]
WantedBy=multi-user.target
SERVICE

    systemctl daemon-reload
    systemctl enable obiora-panel-ready.service 2>/dev/null || true
    systemctl start obiora-panel-ready.service 2>/dev/null || true
}
