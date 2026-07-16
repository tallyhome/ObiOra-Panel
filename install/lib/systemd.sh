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
    ensure_panel_watchdog
    ensure_mariadb_oom_protection

    success "Services systemd démarrés"
}

ensure_boot_service_order() {
    info "Ordre de démarrage au boot (MariaDB/Redis avant le panel)..."

    local dropin='[Unit]
After=obiora-panel-ready.service mariadb.service mysqld.service redis.service redis-server.service network-online.target
Wants=obiora-panel-ready.service mariadb.service mysqld.service redis.service redis-server.service
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

# Watchdog overnight : probe /panel-health + /login toutes les 2 min, auto-heal si KO.
ensure_panel_watchdog() {
    local script="${OBIORA_INSTALL_DIR}/agent/scripts/panel-watchdog.sh"

    if [[ ! -f "${script}" ]]; then
        warn "panel-watchdog.sh introuvable — timer non installé"
        return 0
    fi

    chmod +x "${script}" 2>/dev/null || true

    cat > /etc/systemd/system/obiora-panel-watchdog.service <<SERVICE
[Unit]
Description=ObiOra Panel health watchdog (auto-heal)
After=network.target

[Service]
Type=oneshot
Nice=10
Environment=OBIORA_INSTALL_DIR=${OBIORA_INSTALL_DIR}
Environment=OBIORA_USER=${OBIORA_USER}
Environment=OBIORA_GROUP=${OBIORA_GROUP}
ExecStart=/bin/bash ${script}
SERVICE

    cat > /etc/systemd/system/obiora-panel-watchdog.timer <<SERVICE
[Unit]
Description=ObiOra Panel watchdog every 2 minutes

[Timer]
OnBootSec=90s
OnUnitActiveSec=2min
AccuracySec=15s
Persistent=true
Unit=obiora-panel-watchdog.service

[Install]
WantedBy=timers.target
SERVICE

    systemctl daemon-reload
    systemctl enable --now obiora-panel-watchdog.timer 2>/dev/null || true
    info "Watchdog panel actif (obiora-panel-watchdog.timer)"
}

# Préférer tuer Reverb/autres plutôt que MariaDB (cause typique 500 overnight).
ensure_mariadb_oom_protection() {
    local unit dropin_dir

    for unit in mariadb mysqld; do
        if systemctl list-unit-files --type=service 2>/dev/null | grep -q "^${unit}.service"; then
            dropin_dir="/etc/systemd/system/${unit}.service.d"
            mkdir -p "${dropin_dir}"
            cat > "${dropin_dir}/obiora-oom.conf" <<'EOF'
[Service]
OOMScoreAdjust=-800
EOF
        fi
    done

    if [[ -f /etc/systemd/system/obiora-reverb.service ]]; then
        mkdir -p /etc/systemd/system/obiora-reverb.service.d
        cat > /etc/systemd/system/obiora-reverb.service.d/obiora-oom.conf <<'EOF'
[Service]
OOMScoreAdjust=300
Restart=always
RestartSec=5
EOF
    fi

    systemctl daemon-reload 2>/dev/null || true
}
