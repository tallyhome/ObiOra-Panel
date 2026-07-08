"""Nginx diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import service_finding, systemd_active


class NginxModule(DiagnosticModule):
    """Collect and diagnose Nginx state."""

    name = "nginx"
    title = "Nginx"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect Nginx service and configuration test data."""

        active, _ = systemd_active(self.runner, "nginx")
        version = self.runner.run(["nginx", "-v"])
        config_test = self.runner.run(["nginx", "-t"])
        return {
            "version": version.to_dict(),
            "config_test": config_test.to_dict(),
            "metrics": {
                "active": active,
                "config_ok": config_test.ok,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build Nginx findings."""

        metrics = raw_data["metrics"]
        if not metrics["active"] and raw_data["version"]["missing"]:
            return [
                service_finding("nginx", False, "Nginx non detecte.", optional=True)
            ]

        findings: list[Finding] = []
        if metrics["active"]:
            findings.append(service_finding("nginx", True, "Service Nginx actif."))
        if raw_data["version"]["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Version Nginx",
                    raw_data["version"]["stderr"] or raw_data["version"]["stdout"],
                    "Verifier la configuration SSL et les workers.",
                    ["nginx -v"],
                )
            )
        if not metrics["config_ok"] and not raw_data["config_test"]["missing"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Test configuration Nginx echoue",
                    raw_data["config_test"]["stderr"] or "nginx -t a echoue.",
                    "Corriger la configuration avant reload.",
                    ["nginx -t"],
                )
            )
        return findings
