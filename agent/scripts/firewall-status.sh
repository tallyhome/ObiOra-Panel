#!/usr/bin/env bash
set -euo pipefail

php -r '
$backend = "none";
$enabled = false;
$rules = [];

if (trim(shell_exec("command -v ufw 2>/dev/null") ?? "") !== "") {
    $backend = "ufw";
    $status = shell_exec("ufw status 2>/dev/null") ?? "";
    $enabled = stripos($status, "Status: active") !== false;
    foreach (preg_split("/\R/", $status) as $i => $line) {
        if ($i < 3) continue;
        $line = trim($line);
        if ($line !== "" && preg_match("/^\d+/", $line)) {
            $rules[] = $line;
        }
    }
} elseif (trim(shell_exec("command -v firewall-cmd 2>/dev/null") ?? "") !== "") {
    $backend = "firewalld";
    $enabled = trim(shell_exec("systemctl is-active firewalld 2>/dev/null") ?? "") === "active";
    if ($enabled) {
        $rules[] = trim(shell_exec("firewall-cmd --list-ports 2>/dev/null") ?? "");
        $rules[] = trim(shell_exec("firewall-cmd --list-services 2>/dev/null") ?? "");
    }
}

echo "OK:" . json_encode(["backend" => $backend, "enabled" => $enabled, "rules" => $rules], JSON_UNESCAPED_UNICODE);
'
