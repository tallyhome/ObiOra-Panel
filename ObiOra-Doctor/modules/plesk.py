"""Plesk diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class PleskModule(DiagnosticModule):
    """Collect and diagnose Plesk state."""

    name = "plesk"
    title = "Plesk"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect Plesk indicators."""

        sw_active, _ = systemd_active(self.runner, "sw-cp-server")
        plesk_bin = Path("/usr/sbin/plesk").exists()
        version = self.runner.run(["plesk", "version"])
        return {
            "version": version.to_dict(),
            "metrics": {
                "installed": plesk_bin,
                "service_active": sw_active,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build Plesk findings."""

        if not raw_data["metrics"]["installed"]:
            return [
                Finding(
                    Severity.INFO,
                    "Plesk non detecte",
                    "Binaire plesk absent.",
                    "Aucune action requise.",
                )
            ]
        findings = [
            Finding(
                Severity.INFO,
                "Plesk installe",
                raw_data["version"]["stdout"] or "Plesk detecte.",
                "Verifier la licence et les mises a jour.",
                ["plesk version"],
            )
        ]
        if not raw_data["metrics"]["service_active"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Service Plesk inactif",
                    "sw-cp-server ne semble pas actif.",
                    "Verifier les services Plesk.",
                    ["systemctl status sw-cp-server"],
                )
            )
        return findings
