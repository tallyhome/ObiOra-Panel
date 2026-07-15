"""Hosting panel exposure (Virtualizor, WHM/cPanel) and backup audit."""

from __future__ import annotations

import re
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class HostingSecurityModule(DiagnosticModule):
    """Audit hosting panels and backup encryption."""

    name = "hosting_security"
    title = "Hosting Security"

    _PANEL_PORTS = {
        4081: "Virtualizor",
        4082: "Virtualizor SSL",
        4083: "Virtualizor admin",
        2087: "WHM SSL",
        2083: "cPanel SSL",
        8443: "Plesk",
        10000: "Webmin",
    }

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        ss = self.runner.run(["ss", "-tlnH"])
        exposed_panels: list[str] = []
        if ss.ok:
            for port, name in self._PANEL_PORTS.items():
                if re.search(rf":{port}\s", ss.stdout) and (
                    "0.0.0.0" in ss.stdout or "[::]" in ss.stdout
                ):
                    exposed_panels.append(f"{name}:{port}")

        virt_active, _ = systemd_active(self.runner, "virt")
        backup_dirs = self.runner.run(
            ["bash", "-c", "find /backup /var/backup /home/backup -maxdepth 2 -type f \\( -name '*.gpg' -o -name '*.enc' \\) 2>/dev/null | head -5"],
            timeout_seconds=15,
        )
        plain_backups = self.runner.run(
            ["bash", "-c", "find /backup /var/backup -maxdepth 2 -type f \\( -name '*.tar' -o -name '*.sql' -o -name '*.zip' \\) 2>/dev/null | head -10"],
            timeout_seconds=15,
        )

        return {
            "metrics": {
                "exposed_panels": exposed_panels,
                "virtualizor_active": virt_active,
                "encrypted_backup_samples": len(backup_dirs.stdout.splitlines()),
                "plain_backup_count": len([l for l in plain_backups.stdout.splitlines() if l.strip()]),
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        findings: list[Finding] = []

        if m["exposed_panels"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Panels admin exposes publiquement",
                    ", ".join(m["exposed_panels"]),
                    "Restreindre l'acces par IP ou VPN.",
                    ["ss -tlnp"],
                )
            )

        if m["plain_backup_count"] > 0 and m["encrypted_backup_samples"] == 0:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Backups non chiffres detectes",
                    f"{m['plain_backup_count']} archive(s) en clair.",
                    "Chiffrer les backups clients (GPG, restic, etc.).",
                    ["ls -la /backup"],
                )
            )

        if not findings:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Audit hebergement OK",
                    "Aucun panel admin expose ou backup non chiffre evident.",
                    "Re-verifier apres installation marketplace.",
                )
            )
        return findings
