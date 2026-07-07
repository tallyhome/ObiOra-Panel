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

    # Scheduler
    cat > /etc/systemd/system/obiora-scheduler.service <<SERVICE
[Unit]
Description=ObiOra Panel Scheduler
After=network.target

[Service]
User=${OBIORA_USER}
Group=${OBIORA_GROUP}
Type=oneshot
ExecStart=/usr/bin/php ${OBIORA_INSTALL_DIR}/artisan schedule:run
WorkingDirectory=${OBIORA_INSTALL_DIR}
SERVICE

    cat > /etc/systemd/system/obiora-scheduler.timer <<TIMER
[Unit]
Description=ObiOra Panel Scheduler Timer
Requires=obiora-scheduler.service

[Timer]
OnCalendar=minutely
AccuracySec=1s
Persistent=true
Unit=obiora-scheduler.service

[Install]
WantedBy=timers.target
TIMER

    # Agent
    if [[ -f "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" ]]; then
        sed "s|/opt/obiora-panel|${OBIORA_INSTALL_DIR}|g" \
            "${OBIORA_INSTALL_DIR}/agent/systemd/obiOra-agent.service" \
            > /etc/systemd/system/obiora-agent.service
        chmod +x "${OBIORA_INSTALL_DIR}/agent/bin/obiOra-agent"
    fi

    systemctl daemon-reload
    systemctl_enable_start obiora-queue
    systemctl enable obiora-scheduler.timer
    systemctl start obiora-scheduler.timer || die "Impossible de démarrer obiora-scheduler.timer (vérifiez: systemctl status obiora-scheduler.timer)"

    if [[ -f /etc/systemd/system/obiora-agent.service ]]; then
        systemctl_enable_start obiora-agent
    fi

    if [[ -f /etc/systemd/system/obiora-reverb.service ]]; then
        systemctl_enable_start obiora-reverb 2>/dev/null || warn "Reverb non demarre"
    fi

    systemctl_enable_start redis 2>/dev/null || systemctl_enable_start redis-server 2>/dev/null || true
    systemctl enable supervisor 2>/dev/null && systemctl start supervisor 2>/dev/null || warn "Supervisor non démarré (optionnel)"

    success "Services systemd démarrés"
}
