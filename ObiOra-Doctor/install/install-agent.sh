#!/usr/bin/env bash
# Installation minimale Obiora Agent sur un VPS/slave
# Usage: curl -sSL ... | bash  OU  ./install-agent.sh

set -euo pipefail

INSTALL_DIR="${OBIORA_AGENT_DIR:-/opt/obiora-agent}"
PANEL_URL="${OBIORA_PANEL_URL:-}"
SERVER_ID="${OBIORA_SERVER_ID:-}"
AGENT_TOKEN="${OBIORA_AGENT_TOKEN:-}"

echo "=== Obiora Agent - installation minimale ==="

if ! command -v python3 >/dev/null 2>&1; then
    echo "Python 3 requis."
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

mkdir -p "$INSTALL_DIR"
rsync -a --exclude reports --exclude cache --exclude .tmp-reports \
    "$SOURCE_DIR/" "$INSTALL_DIR/" 2>/dev/null || cp -r "$SOURCE_DIR"/* "$INSTALL_DIR/"

chmod +x "$INSTALL_DIR/obiora.sh" "$INSTALL_DIR/bin/obiora-doctor.py"

if [ -n "$PANEL_URL" ] && [ -n "$SERVER_ID" ] && [ -n "$AGENT_TOKEN" ]; then
    SIGNING_KEY="${OBIORA_SIGNING_KEY:-}"
    if [ -z "$SIGNING_KEY" ]; then
        SIGNING_KEY="$(python3 -c "import secrets; print(secrets.token_hex(32))")"
        echo "Cle signing generee (copiez dans OBIORA_DOCTOR_SIGNING_KEY du panel): $SIGNING_KEY"
    fi
    cat > "$INSTALL_DIR/config/agent-panel.json" <<EOF
{
  "panel_url": "$PANEL_URL",
  "server_id": $SERVER_ID,
  "agent_token": "$AGENT_TOKEN",
  "signing_key": "$SIGNING_KEY"
}
EOF
    chmod 600 "$INSTALL_DIR/config/agent-panel.json"
fi

cat > /etc/systemd/system/obiora-agent.service <<EOF
[Unit]
Description=Obiora Agent - diagnostic push vers panel
After=network.target

[Service]
Type=simple
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/bin/python3 $INSTALL_DIR/bin/obiora-doctor.py agent --interval 300
Restart=always
RestartSec=30

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable obiora-agent
systemctl start obiora-agent

echo "=== Obiora Agent installe dans $INSTALL_DIR ==="
echo "Service: systemctl status obiora-agent"
