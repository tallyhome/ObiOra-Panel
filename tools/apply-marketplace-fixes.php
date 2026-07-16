<?php

declare(strict_types=1);

/**
 * Applique les correctifs audit marketplace : ports uniques, wizards auth, runtime manifests, install.sh.
 *
 * Usage: php tools/apply-marketplace-fixes.php
 */

$root = dirname(__DIR__);
$apps = require $root.'/tools/swizzin-apps.php';

/** @var list<string> */
$protectedInstall = [
    'qbittorrent', 'filebrowser', 'nextcloud', 'webmin', 'vsftpd', 'pure-ftpd', 'wireguard', 'netdata',
    'plex', 'calibreweb',
];

/** @var array<string, int> host_port overrides (évite conflits) */
$portOverrides = [
    'rutorrent' => 8084,
    'organizr' => 8086,
    'rapidleech' => 8087,
    'librespeed' => 8088,
    'sabnzbd' => 8085,
    'calibre' => 8089,
    'xmrig' => 8090,
    'filebrowser' => 8082,
    'sickchill' => 8091,
    'sickgear' => 8092,
    'calibrecs' => 8093,
    'xmr-stak' => 8094,
    'jellyfin' => 8097,
    'headphones' => 8183,
    'subsonic' => 4042,
    'sonarrold' => 8990,
    'nextcloud' => 8443,
    'plexpy' => 8182,
];

/** @var array<string, int> internal container port when different from host */
$internalPorts = [
    'sabnzbd' => 8080,
    'calibre' => 8080,
    'filebrowser' => 80,
    'nextcloud' => 443,
    // Host remappé ≠ port d'écoute de l'image (sinon UI morte après « OK »)
    'jellyfin' => 8096,
    'organizr' => 80,
    'rutorrent' => 80,
    'librespeed' => 80,
    'headphones' => 8181,
    'sickchill' => 8081,
    'sickgear' => 8081,
    'subsonic' => 4040,
    'calibrecs' => 8080,
    'sonarrold' => 8989,
    'plexpy' => 8181,
    'rapidleech' => 80,
    'rtorrent' => 80,
];

/** @var list<string> */
$nonInstallable = [
    'letsencrypt', 'netronome', 'nginx', 'rtorrent', 'plexpy', 'csf',
    // Legacy / mineurs / images mortes ou trompeuses
    'xmrig', 'xmr-stak', 'xmr-stak-cpu', 'couchpotato', 'headphones',
];

/** @var array<string, array{user: string, min_pass: int, mode: string}> */
$authApps = [
    'deluge' => ['user' => 'admin', 'min_pass' => 1, 'mode' => 'deluge'],
    'transmission' => ['user' => 'admin', 'min_pass' => 1, 'mode' => 'transmission'],
    'sabnzbd' => ['user' => 'admin', 'min_pass' => 1, 'mode' => 'sabnzbd'],
    'nzbget' => ['user' => 'nzbget', 'min_pass' => 1, 'mode' => 'nzbget'],
    'pyload' => ['user' => 'admin', 'min_pass' => 1, 'mode' => 'generic'],
    'znc' => ['user' => 'admin', 'min_pass' => 8, 'mode' => 'generic'],
    'calibreweb' => ['user' => 'admin', 'min_pass' => 1, 'mode' => 'calibreweb'],
];

$updatedManifests = 0;
$updatedInstalls = 0;

foreach ($apps as $app) {
    $slug = (string) $app['slug'];
    $type = (string) ($app['type'] ?? 'docker');
    $dir = $root.'/packages/'.$slug;
    $manifestPath = $dir.'/manifest.json';

    if ($type === 'skip' || ! is_file($manifestPath)) {
        continue;
    }

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    $hostPort = $portOverrides[$slug] ?? (isset($app['port']) ? (int) $app['port'] : null);

    if ($hostPort !== null && in_array($type, ['docker', 'native'], true)) {
        $runtime = $manifest['runtime'] ?? [];
        $runtime['type'] = $runtime['type'] ?? 'docker';
        if ($runtime['type'] === 'docker') {
            $runtime['container'] = $runtime['container'] ?? 'obiora-'.$slug;
            $runtime['port'] = $hostPort;
            $runtime['url'] = buildUrl($slug, $hostPort);
            $runtime['usage'] = buildUsage($slug, $app, isset($authApps[$slug]));
            $manifest['runtime'] = $runtime;
        }
    }

    if (in_array($slug, $nonInstallable, true)) {
        $manifest['installable'] = false;
        $manifest['install_notice'] = installNotice($slug);
    }

    if (isset($authApps[$slug]) && ! isset($manifest['install']['options'])) {
        $manifest['install'] = ['options' => wizardOptions($slug, $authApps[$slug])];
    }

    if ($slug === 'duckdns') {
        $manifest['install'] = ['options' => duckdnsWizardOptions()];
        $manifest['runtime'] = [
            'type' => 'binary',
            'usage' => 'Mise à jour DNS DuckDNS via timer systemd (toutes les 5 min). Domaine et token définis à l\'installation.',
        ];
    }

    if ($slug === 'x2go') {
        $manifest['runtime'] = [
            'type' => 'systemd',
            'service' => 'x2goserver',
            'usage' => 'Bureau à distance Linux via client X2Go (connexion SSH, port 22).',
        ];
    }

    if ($slug === 'sonarr' && ($manifest['runtime']['type'] ?? '') === 'docker') {
        $manifest['runtime']['port'] = 8989;
        $manifest['runtime']['url'] = 'http://{host}:8989';
        $manifest['runtime']['usage'] = 'Gestion et téléchargement de séries TV sur le port 8989.';
    }

    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
    );
    $updatedManifests++;

    if ($type !== 'docker' || in_array($slug, $protectedInstall, true)) {
        continue;
    }

    $image = (string) ($app['image'] ?? '');
    if ($image === '' || $hostPort === null) {
        continue;
    }

    $internalPort = $internalPorts[$slug] ?? $hostPort;
    $auth = $authApps[$slug] ?? null;
    $installSh = $auth !== null
        ? generateAuthInstall($slug, $image, $hostPort, $internalPort, $auth['mode'])
        : generateDockerInstall($slug, $image, $hostPort, $internalPort);

    file_put_contents($dir.'/install.sh', $installSh);
    chmod($dir.'/install.sh', 0755);
    $updatedInstalls++;
}

echo "Manifests mis à jour : {$updatedManifests}\n";
echo "install.sh régénérés : {$updatedInstalls}\n";

function buildUrl(string $slug, int $port): string
{
    if ($slug === 'nextcloud') {
        return 'https://{host}:'.$port;
    }

    if (in_array($slug, ['vsftpd', 'pure-ftpd'], true)) {
        return 'ftp://{host}:'.$port;
    }

    return 'http://{host}:'.$port;
}

/**
 * @param  array<string, mixed>  $app
 */
function buildUsage(string $slug, array $app, bool $hasAuth): string
{
    $desc = (string) ($app['description'] ?? $app['name'] ?? $slug);
    $port = (int) ($app['port'] ?? 0);

    if ($hasAuth) {
        return $desc.' Identifiants définis à l\'installation via le wizard du panel.';
    }

    return $desc;
}

function installNotice(string $slug): string
{
    return match ($slug) {
        'letsencrypt' => 'Utilisez le module Sites ObiOra pour les certificats SSL.',
        'netronome' => 'Entrée legacy désactivée (installait Prometheus). Utilisez Netdata.',
        'nginx' => 'Nginx est géré par le module Sites ObiOra — ne pas installer en double.',
        'rtorrent' => 'Utilisez ruTorrent pour rTorrent avec interface web.',
        'plexpy' => 'Doublon de Tautulli — installez Tautulli à la place.',
        'csf' => 'CSF n\'est pas supporté (interactif / conflits firewall). Utilisez le module Firewall.',
        'xmrig', 'xmr-stak', 'xmr-stak-cpu' => 'Mineurs crypto désactivés (hors scope panel / risque).',
        'couchpotato' => 'Projet abandonné — utilisez Radarr.',
        'headphones' => 'Projet abandonné — utilisez Lidarr.',
        default => 'Non installable depuis le marketplace.',
    };
}

/**
 * @param  array{user: string, min_pass: int, mode: string}  $auth
 * @return list<array<string, mixed>>
 */
function wizardOptions(string $slug, array $auth): array
{
    $label = ucfirst(str_replace('-', ' ', $slug));

    return [
        [
            'name' => 'label',
            'label' => 'Nom affiché dans le panel',
            'type' => 'text',
            'default' => $label,
            'help' => 'Libellé visible dans le centre de contrôle.',
        ],
        [
            'name' => 'username',
            'label' => 'Identifiant',
            'type' => 'text',
            'default' => $auth['user'],
            'required' => true,
            'min' => 3,
        ],
        [
            'name' => 'pass',
            'label' => 'Mot de passe',
            'type' => 'password',
            'required' => true,
            'min' => $auth['min_pass'],
            'help' => 'Stocké dans les infos de l\'application après installation.',
        ],
    ];
}

/** @return list<array<string, mixed>> */
function duckdnsWizardOptions(): array
{
    return [
        [
            'name' => 'label',
            'label' => 'Nom affiché dans le panel',
            'type' => 'text',
            'default' => 'DuckDNS',
        ],
        [
            'name' => 'domain',
            'label' => 'Sous-domaine DuckDNS',
            'type' => 'text',
            'required' => true,
            'help' => 'Ex. monserveur (sans .duckdns.org)',
        ],
        [
            'name' => 'token',
            'label' => 'Token DuckDNS',
            'type' => 'password',
            'required' => true,
            'min' => 8,
        ],
    ];
}

function generateDockerInstall(string $slug, string $image, int $hostPort, int $internalPort): string
{
    $internalArg = $hostPort !== $internalPort ? " {$internalPort}" : '';

    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="\$(cd "\$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "\${SCRIPT_DIR}/../_lib/docker.sh"
obiora_docker_install "{$slug}" "{$image}" {$hostPort}{$internalArg}
BASH;
}

function generateAuthInstall(string $slug, string $image, int $hostPort, int $internalPort, string $mode): string
{
    $body = match ($mode) {
        'transmission' => <<<BASH
web_user="\$(obiora_auth_user)"
web_pass="\$(obiora_auth_pass)"
data_dir="/var/lib/obiora/{$slug}"
mkdir -p "\${data_dir}"
chown -R 1000:1000 "\${data_dir}" 2>/dev/null || chmod -R 0777 "\${data_dir}"
obiora_docker_install "{$slug}" "{$image}" {$hostPort} \
    -e "USER=\${web_user}" -e "PASS=\${web_pass}" -e "HOST_WHITELIST="
echo "OK:{$slug} (port {$hostPort}) credentials:\${web_user}"
BASH,
        'deluge' => <<<BASH
web_user="\$(obiora_auth_user)"
web_pass="\$(obiora_auth_pass)"
data_dir="/var/lib/obiora/{$slug}"
mkdir -p "\${data_dir}"
obiora_seed_deluge_auth "\${data_dir}" "\${web_user}" "\${web_pass}"
chown -R 1000:1000 "\${data_dir}" 2>/dev/null || chmod -R 0777 "\${data_dir}"
obiora_docker_install "{$slug}" "{$image}" {$hostPort} {$internalPort}
echo "OK:{$slug} (port {$hostPort}) credentials:\${web_user}"
BASH,
        'nzbget' => <<<BASH
web_user="\$(obiora_auth_user)"
web_pass="\$(obiora_auth_pass)"
data_dir="/var/lib/obiora/{$slug}"
mkdir -p "\${data_dir}"
obiora_seed_nzbget_auth "\${data_dir}" "\${web_user}" "\${web_pass}"
chown -R 1000:1000 "\${data_dir}" 2>/dev/null || chmod -R 0777 "\${data_dir}"
obiora_docker_install "{$slug}" "{$image}" {$hostPort} {$internalPort}
echo "OK:{$slug} (port {$hostPort}) credentials:\${web_user}"
BASH,
        'sabnzbd' => <<<BASH
web_user="\$(obiora_auth_user)"
web_pass="\$(obiora_auth_pass)"
data_dir="/var/lib/obiora/{$slug}"
mkdir -p "\${data_dir}"
obiora_seed_sabnzbd_auth "\${data_dir}" "\${web_user}" "\${web_pass}"
chown -R 1000:1000 "\${data_dir}" 2>/dev/null || chmod -R 0777 "\${data_dir}"
obiora_docker_install "{$slug}" "{$image}" {$hostPort} {$internalPort}
echo "OK:{$slug} (port {$hostPort}) credentials:\${web_user}"
BASH,
        'calibreweb' => <<<BASH
web_user="\$(obiora_auth_user)"
web_pass="\$(obiora_auth_pass)"
data_dir="/var/lib/obiora/{$slug}"
mkdir -p "\${data_dir}"
obiora_seed_calibreweb_auth "\${data_dir}" "\${web_user}" "\${web_pass}"
chown -R 1000:1000 "\${data_dir}" 2>/dev/null || chmod -R 0777 "\${data_dir}"
obiora_docker_install "{$slug}" "{$image}" {$hostPort} {$internalPort}
echo "OK:{$slug} (port {$hostPort}) credentials:\${web_user} (défaut Calibre-Web : admin/admin123 si première connexion — changez via l'interface)"
BASH,
        default => <<<BASH
web_user="\$(obiora_auth_user)"
web_pass="\$(obiora_auth_pass)"
data_dir="/var/lib/obiora/{$slug}"
mkdir -p "\${data_dir}"
chown -R 1000:1000 "\${data_dir}" 2>/dev/null || chmod -R 0777 "\${data_dir}"
obiora_docker_install "{$slug}" "{$image}" {$hostPort} {$internalPort}
echo "OK:{$slug} (port {$hostPort}) credentials:\${web_user}"
BASH,
    };

    return <<<BASH
#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="\$(cd "\$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../_lib/docker.sh
source "\${SCRIPT_DIR}/../_lib/docker.sh"

{$body}
BASH;
}
