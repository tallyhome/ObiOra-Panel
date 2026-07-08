"""LiteSpeed diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import service_finding, systemd_active


class LitespeedModule(DiagnosticModule):
    """Collect and diagnose LiteSpeed state."""

    name = "litespeed"
    title = "LiteSpeed"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect LiteSpeed service data."""

        lshttpd_active, _ = systemd_active(self.runner, "lshttpd")
        lsws_active, _ = systemd_active(self.runner, "lsws")
        return {
            "metrics": {
                "active": lshttpd_active or lsws_active,
                "service": "lshttpd" if lshttpd_active else "lsws" if lsws_active else None,
            }
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build LiteSpeed findings."""

        metrics = raw_data["metrics"]
        if not metrics["active"]:
            return [
                service_finding("litespeed", False, "LiteSpeed non detecte.", optional=True)
            ]
        return [
            service_finding(
                metrics["service"] or "litespeed",
                True,
                "LiteSpeed detecte et actif.",
            ),
            Finding(
                Severity.INFO,
                "LiteSpeed operationnel",
                "Le serveur web LiteSpeed est actif.",
                "Verifier les vhosts et la licence.",
                ["systemctl status lshttpd"],
            ),
        ]
