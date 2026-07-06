#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -n bash "$0" "$@"
fi

install -d -m 0755 /opt/duckdns
cat > /opt/duckdns/duck.sh << 'EOF'
#!/bin/bash
echo url="https://www.duckdns.org/update?domains=${DUCKDNS_DOMAIN}&token=${DUCKDNS_TOKEN}&ip=" | curl -k -o /opt/duckdns/duck.log -K -
EOF
chmod +x /opt/duckdns/duck.sh
echo "Configurez DUCKDNS_DOMAIN et DUCKDNS_TOKEN dans /opt/duckdns/duck.sh"

echo "OK:duckdns"