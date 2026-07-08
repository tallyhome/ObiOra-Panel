"""cPanel diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class CpanelModule(DiagnosticModule):
    """Collect and diagnose cPanel/WHM state."""

    name = "cpanel"
    title = "cPanel"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect cPanel indicators."""

        cpanel_active, _ = systemd_active(self.runner, "cpanel")
        whmapi = self.runner.run(["whmapi1", "version"])
        cpanel_dir = Path("/usr/local/cpanel").exists()
        return {
            "whmapi": whmapi.to_dict(),
            "metrics": {
                "installed": cpanel_dir,
                "service_active": cpanel_active,
                "whmapi_available": whmapi.ok,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build cPanel findings."""

        metrics = raw_data["metrics"]
        if not metrics["installed"]:
            return [
                Finding(
                    Severity.INFO,
                    "cPanel non detecte",
                    "/usr/local/cpanel absent.",
                    "Aucune action requise.",
                )
            ]
        findings = [
            Finding(
                Severity.INFO,
                "cPanel installe",
                "Installation cPanel detectee.",
                "Verifier les mises a jour et la licence.",
                ["/usr/local/cpanel/cpanel -V"],
            )
        ]
        if not metrics["service_active"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Service cPanel inactif",
                    "Le service cPanel ne semble pas actif.",
                    "Verifier systemctl status cpanel.",
                    ["systemctl status cpanel"],
                )
            )
        return findings
