#!/usr/bin/env bash
# Planificateur ObiOra Panel — timer systemd (équivalent crontab * * * * *)

ensure_panel_scheduler() {
    local install_dir="${OBIORA_INSTALL_DIR:-/opt/obiora-panel}"
    local user="${OBIORA_USER:-obiora}"
    local group="${OBIORA_GROUP:-obiora}"

    cat > /etc/systemd/system/obiora-scheduler.service <<SERVICE
[Unit]
Description=ObiOra Panel Scheduler
After=network.target

[Service]
User=${user}
Group=${group}
Type=oneshot
ExecStart=/usr/bin/php ${install_dir}/artisan schedule:run
WorkingDirectory=${install_dir}
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

    systemctl daemon-reload
    systemctl enable obiora-scheduler.timer >/dev/null 2>&1 || true
    systemctl start obiora-scheduler.timer >/dev/null 2>&1 || true

    if systemctl is-active --quiet obiora-scheduler.timer; then
        info "obiora-scheduler.timer actif (schedule:run chaque minute)"
        return 0
    fi

    warn "obiora-scheduler.timer non actif — vérifiez: systemctl status obiora-scheduler.timer"
    return 1
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    set -euo pipefail
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    # shellcheck source=/dev/null
    source "${SCRIPT_DIR}/common.sh"
    require_root
    ensure_panel_scheduler
fi
