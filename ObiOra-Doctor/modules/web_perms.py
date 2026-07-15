"""Web root permissions and suspicious PHP/shell detection."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class WebPermsModule(DiagnosticModule):
    """Audit /var/www permissions and web shells."""

    name = "web_perms"
    title = "Web Permissions"

    _WEB_ROOTS = ("/var/www", "/home/*/public_html", "/usr/local/apache/htdocs")
    _SHELL_PATTERNS = ("eval(", "base64_decode", "shell_exec", "system(", "passthru(", "c99", "r57")

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        world_writable = self.runner.run(
            ["bash", "-c", "find /var/www -xdev -type d -perm -0002 2>/dev/null | head -20"],
            timeout_seconds=30,
        )
        env_exposed = self.runner.run(
            ["bash", "-c", "find /var/www -name '.env' -readable 2>/dev/null | head -10"],
            timeout_seconds=20,
        )
        suspicious_php = self.runner.run(
            [
                "bash",
                "-c",
                "grep -rl --include='*.php' -E 'eval\\(|base64_decode|shell_exec|passthru\\(' /var/www 2>/dev/null | head -15",
            ],
            timeout_seconds=45,
        )

        ww_dirs = [d.strip() for d in world_writable.stdout.splitlines() if d.strip()]
        env_files = [f.strip() for f in env_exposed.stdout.splitlines() if f.strip()]
        suspect_files = [f.strip() for f in suspicious_php.stdout.splitlines() if f.strip()]

        return {
            "metrics": {
                "world_writable_dirs": ww_dirs,
                "world_writable_count": len(ww_dirs),
                "exposed_env_files": env_files,
                "suspicious_php_count": len(suspect_files),
                "suspicious_php": suspect_files[:5],
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        findings: list[Finding] = []

        if m["world_writable_count"] > 0:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Repertoires web world-writable",
                    ", ".join(m["world_writable_dirs"][:3]),
                    "Corriger les permissions (chmod 755 dirs, 644 fichiers).",
                    ["find /var/www -type d -perm -0002"],
                )
            )

        if m["exposed_env_files"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Fichiers .env dans le webroot",
                    ", ".join(m["exposed_env_files"][:3]),
                    "Deplacer .env hors du document root ou bloquer l'acces nginx.",
                    ["grep -r '.env' /etc/nginx/"],
                )
            )

        if m["suspicious_php_count"] > 0:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "PHP suspect detecte",
                    ", ".join(m["suspicious_php"][:3]),
                    "Analyser ces fichiers — possible web shell ou code obfusque.",
                    ["grep -rl eval /var/www --include='*.php'"],
                )
            )

        if not findings:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Permissions web OK",
                    "Aucune anomalie evidente sous /var/www.",
                    "Scanner apres chaque deploiement.",
                )
            )
        return findings
