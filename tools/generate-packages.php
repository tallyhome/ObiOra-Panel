<?php

declare(strict_types=1);

/**
 * Génère packages/{slug}/ depuis tools/swizzin-apps.php
 *
 * Usage: php tools/generate-packages.php [--force]
 */

$root = dirname(__DIR__);
$apps = require $root.'/tools/swizzin-apps.php';
$force = in_array('--force', $argv ?? [], true);
$libDir = $root.'/packages/_lib';

if (! is_dir($libDir)) {
    mkdir($libDir, 0755, true);
}

writeSharedLib($libDir);

$created = 0;
$skipped = 0;

foreach ($apps as $app) {
    $slug = (string) $app['slug'];
    $type = (string) ($app['type'] ?? 'docker');
    $dir = $root.'/packages/'.$slug;

    if ($type === 'skip') {
        $skipped++;
        continue;
    }

    if ($type === 'native' && is_dir($dir) && ! $force) {
        echo "SKIP (native): {$slug}\n";
        $skipped++;
        continue;
    }

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $manifest = [
        'slug' => $slug,
        'name' => $app['name'],
        'description' => $app['description'],
        'version' => '1.0.0',
        'category' => $app['category'],
        'source' => 'swizzin-catalog',
        'scripts' => [
            'install' => 'install.sh',
            'uninstall' => 'uninstall.sh',
        ],
    ];

    $runtime = buildGeneratedRuntime($app);
    if ($runtime !== null) {
        $manifest['runtime'] = $runtime;
    }

    file_put_contents(
        $dir.'/manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
    );

    file_put_contents($dir.'/install.sh', generateInstallScript($app));
    file_put_contents($dir.'/uninstall.sh', generateUninstallScript($app));
    chmod($dir.'/install.sh', 0755);
    chmod($dir.'/uninstall.sh', 0755);

    echo "OK: {$slug}\n";
    $created++;
}

echo "\nTerminé : {$created} packages générés, {$skipped} ignorés.\n";

function writeSharedLib(string $libDir): void
{
    $dockerLib = <<<'BASH'
#!/usr/bin/env bash
# ObiOra — helper installation Docker
set -euo pipefail

obiora_require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        exec sudo -n bash "$0" "$@"
    fi
}

obiora_require_docker() {
    if ! command -v docker &>/dev/null; then
        echo "Docker requis. Installez Docker via le module ObiOra." >&2
        exit 1
    fi
}

obiora_docker_install() {
    local slug="$1"
    local image="$2"
    local port="$3"
    local name="obiora-${slug}"

    obiora_require_root
    obiora_require_docker

    if docker ps -a --format '{{.Names}}' | grep -q "^${name}$"; then
        echo "OK:${slug} (déjà installé)"
        exit 0
    fi

    mkdir -p "/var/lib/obiora/${slug}"

    docker run -d \
        --name "${name}" \
        --restart unless-stopped \
        -p "${port}:${port}" \
        -v "/var/lib/obiora/${slug}:/config" \
        -e PUID=1000 -e PGID=1000 \
        "${image}"

    echo "OK:${slug} (port ${port})"
}

obiora_docker_uninstall() {
    local slug="$1"
    local name="obiora-${slug}"

    obiora_require_root
    docker stop "${name}" 2>/dev/null || true
    docker rm "${name}" 2>/dev/null || true
    echo "OK:${slug} removed"
}
BASH;

    file_put_contents($libDir.'/docker.sh', $dockerLib);
    chmod($libDir.'/docker.sh', 0755);
}

/**
 * @param  array<string, mixed>  $app
 */
function generateInstallScript(array $app): string
{
    $slug = (string) $app['slug'];
    $type = (string) $app['type'];

    return match ($type) {
        'docker' => dockerInstallScript($slug, (string) $app['image'], (int) $app['port']),
        'apt' => aptInstallScript($slug, (string) $app['package']),
        'curl' => curlInstallScript($slug, (string) $app['url']),
        'script' => scriptInstallScript($slug, (string) $app['service']),
        default => stubInstallScript($slug),
    };
}

/**
 * @param  array<string, mixed>  $app
 */
function generateUninstallScript(array $app): string
{
    $slug = (string) $app['slug'];
    $type = (string) $app['type'];

    return match ($type) {
        'docker' => dockerUninstallScript($slug),
        'apt' => aptUninstallScript($slug, (string) ($app['package'] ?? $slug)),
        'curl' => curlUninstallScript($slug),
        'script' => scriptUninstallScript($slug, (string) ($app['service'] ?? $slug)),
        default => stubUninstallScript($slug),
    };
}

function dockerInstallScript(string $slug, string $image, int $port): string
{
    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="\$(cd "\$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "\${SCRIPT_DIR}/../_lib/docker.sh"
obiora_docker_install "{$slug}" "{$image}" {$port}
BASH;
}

function dockerUninstallScript(string $slug): string
{
    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="\$(cd "\$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "\${SCRIPT_DIR}/../_lib/docker.sh"
obiora_docker_uninstall "{$slug}"
BASH;
}

function aptInstallScript(string $slug, string $package): string
{
    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

if [[ "\${EUID}" -ne 0 ]]; then
    exec sudo -n bash "\$0" "\$@"
fi

if dpkg -s {$package} &>/dev/null; then
    echo "OK:{$slug} (déjà installé)"
    exit 0
fi

if command -v apt-get &>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq {$package}
elif command -v dnf &>/dev/null; then
    dnf install -y {$package} 2>/dev/null || { echo "Paquet {$package} non disponible" >&2; exit 1; }
else
    echo "Gestionnaire de paquets non supporté" >&2
    exit 1
fi

systemctl enable {$package} 2>/dev/null || true
systemctl start {$package} 2>/dev/null || true

echo "OK:{$slug}"
BASH;
}

function aptUninstallScript(string $slug, string $package): string
{
    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

if [[ "\${EUID}" -ne 0 ]]; then
    exec sudo -n bash "\$0" "\$@"
fi

systemctl stop {$package} 2>/dev/null || true
systemctl disable {$package} 2>/dev/null || true

if command -v apt-get &>/dev/null; then
    apt-get remove -y -qq {$package} 2>/dev/null || true
elif command -v dnf &>/dev/null; then
    dnf remove -y {$package} 2>/dev/null || true
fi

echo "OK:{$slug} removed"
BASH;
}

function curlInstallScript(string $slug, string $url): string
{
    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

if [[ "\${EUID}" -ne 0 ]]; then
    exec sudo -n bash "\$0" "\$@"
fi

if command -v rclone &>/dev/null; then
    echo "OK:{$slug} (déjà installé)"
    exit 0
fi

curl -fsSL {$url} | bash

echo "OK:{$slug}"
BASH;
}

function curlUninstallScript(string $slug): string
{
    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

if [[ "\${EUID}" -ne 0 ]]; then
    exec sudo -n bash "\$0" "\$@"
fi

rm -f /usr/bin/rclone /usr/local/bin/rclone 2>/dev/null || true

echo "OK:{$slug} removed"
BASH;
}

function scriptInstallScript(string $slug, string $service): string
{
    $body = match ($service) {
        'webmin' => <<<'BODY'
if [[ -f /etc/webmin/miniserv.conf ]]; then
    echo "OK:webmin (déjà installé)"
    exit 0
fi
curl -fsSL https://raw.githubusercontent.com/webmin/webmin/master/setup.sh -o /tmp/webmin-setup.sh
bash /tmp/webmin-setup.sh --unattended
rm -f /tmp/webmin-setup.sh
BODY,
        'duckdns' => <<<'BODY'
install -d -m 0755 /opt/duckdns
cat > /opt/duckdns/duck.sh << 'EOF'
#!/bin/bash
echo url="https://www.duckdns.org/update?domains=${DUCKDNS_DOMAIN}&token=${DUCKDNS_TOKEN}&ip=" | curl -k -o /opt/duckdns/duck.log -K -
EOF
chmod +x /opt/duckdns/duck.sh
echo "Configurez DUCKDNS_DOMAIN et DUCKDNS_TOKEN dans /opt/duckdns/duck.sh"
BODY,
        'letsencrypt' => <<<'BODY'
echo "Utilisez le module Sites ObiOra pour les certificats SSL." >&2
exit 1
BODY,
        'csf' => <<<'BODY'
if [[ -d /etc/csf ]]; then
    echo "OK:csf (déjà installé)"
    exit 0
fi
cd /usr/src
curl -fsSL https://download.configserver.com/csf.tgz -o csf.tgz
tar -xzf csf.tgz
cd csf
bash install.sh
BODY,
        default => "echo \"Service {$service} — configuration manuelle requise\" >&2\nexit 1",
    };

    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

if [[ "\${EUID}" -ne 0 ]]; then
    exec sudo -n bash "\$0" "\$@"
fi

{$body}

echo "OK:{$slug}"
BASH;
}

function scriptUninstallScript(string $slug, string $service): string
{
    $body = match ($service) {
        'webmin' => 'rm -rf /etc/webmin /usr/share/webmin /var/webmin 2>/dev/null || true',
        'duckdns' => 'rm -rf /opt/duckdns 2>/dev/null || true',
        'letsencrypt' => 'echo "Certificats gérés par ObiOra" >&2',
        'csf' => 'cd /etc/csf 2>/dev/null && bash uninstall.sh || true',
        default => 'true',
    };

    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

if [[ "\${EUID}" -ne 0 ]]; then
    exec sudo -n bash "\$0" "\$@"
fi

{$body}

echo "OK:{$slug} removed"
BASH;
}

function stubInstallScript(string $slug): string
{
    return <<<BASH
#!/usr/bin/env bash
echo "Installation non disponible pour {$slug}" >&2
exit 1
BASH;
}

function stubUninstallScript(string $slug): string
{
    return <<<BASH
#!/usr/bin/env bash
echo "OK:{$slug} removed"
BASH;
}

/**
 * @param  array<string, mixed>  $app
 * @return array<string, mixed>|null
 */
function buildGeneratedRuntime(array $app): ?array
{
    $slug = (string) $app['slug'];
    $type = (string) ($app['type'] ?? 'docker');

    return match ($type) {
        'docker' => [
            'type' => 'docker',
            'container' => 'obiora-'.$slug,
            'port' => (int) $app['port'],
            'url' => 'http://{host}:'.(int) $app['port'],
            'usage' => (string) $app['description'],
        ],
        'apt' => [
            'type' => 'systemd',
            'service' => (string) ($app['package'] ?? $slug),
            'usage' => (string) $app['description'],
        ],
        'script' => [
            'type' => 'systemd',
            'service' => (string) ($app['service'] ?? $slug),
            'usage' => (string) $app['description'],
        ],
        default => null,
    };
}
