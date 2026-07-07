#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

domain="${OBIORA_APP_DOMAIN:-}"
token="${OBIORA_APP_TOKEN:-}"

if [[ -z "${domain}" || -z "${token}" ]]; then
    echo "ERREUR: domaine et token DuckDNS requis (wizard d'installation)." >&2
    exit 1
fi

install -d -m 0755 /opt/duckdns

cat > /opt/duckdns/duck.env <<EOF
DUCKDNS_DOMAIN=${domain}
DUCKDNS_TOKEN=${token}
EOF
chmod 600 /opt/duckdns/duck.env

cat > /opt/duckdns/duck.sh <<'EOF'
#!/usr/bin/env bash
set -a
source /opt/duckdns/duck.env
set +a
curl -fsS "https://www.duckdns.org/update?domains=${DUCKDNS_DOMAIN}&token=${DUCKDNS_TOKEN}&ip=" -o /opt/duckdns/duck.log
EOF
chmod 755 /opt/duckdns/duck.sh

cat > /etc/systemd/system/duckdns.service <<EOF
[Unit]
Description=DuckDNS IP updater
After=network-online.target

[Service]
Type=oneshot
ExecStart=/opt/duckdns/duck.sh
EOF

cat > /etc/systemd/system/duckdns.timer <<EOF
[Unit]
Description=DuckDNS periodic update

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now duckdns.timer
/opt/duckdns/duck.sh

echo "OK:duckdns credentials:${domain}"
