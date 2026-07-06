<?php

declare(strict_types=1);

$treeFile = $argv[1] ?? '';
if ($treeFile === '' || ! is_file($treeFile)) {
    fwrite(STDERR, "Usage: php tools/analyze-swizzin.php <tree.json>\n");
    exit(1);
}

/** @var array{tree: list<array{path: string, type: string}>} $data */
$data = json_decode((string) file_get_contents($treeFile), true, 512, JSON_THROW_ON_ERROR);

$slugs = [];
$shellScripts = 0;

foreach ($data['tree'] as $file) {
    if (($file['type'] ?? '') !== 'blob') {
        continue;
    }
    $path = $file['path'];
    if (preg_match('#^scripts/(install|remove|update|nginx|upgrade)/([^/]+)\.sh$#', $path, $m)) {
        $slugs[$m[2]] = true;
    }
    if (str_starts_with($path, 'scripts/') && str_ends_with($path, '.sh')) {
        $shellScripts++;
    }
}

ksort($slugs);
$ourApps = array_column(require dirname(__DIR__).'/tools/swizzin-apps.php', 'slug');
$missing = array_diff(array_keys($slugs), $ourApps);
$extra = array_diff($ourApps, array_keys($slugs));

echo 'Shell scripts in scripts/: '.$shellScripts.PHP_EOL;
echo 'Unique app slugs (install/remove/update/nginx): '.count($slugs).PHP_EOL;
echo 'Our catalog entries: '.count($ourApps).PHP_EOL;
echo 'Missing from our catalog: '.implode(', ', $missing).PHP_EOL;
echo 'In our catalog but not in swizzin scripts: '.implode(', ', $extra).PHP_EOL;
