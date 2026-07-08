"""Apache diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import service_finding, systemd_active


class ApacheModule(DiagnosticModule):
    """Collect and diagnose Apache httpd state."""

    name = "apache"
    title = "Apache"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect Apache service and version data."""

        httpd_active, _ = systemd_active(self.runner, "httpd")
        apache2_active, _ = systemd_active(self.runner, "apache2")
        version = self.runner.run(["httpd", "-v"])
        apache2_version = self.runner.run(["apache2", "-v"])
        return {
            "metrics": {
                "active": httpd_active or apache2_active,
                "service": "httpd" if httpd_active else "apache2" if apache2_active else None,
                "version_output": version.stdout or apache2_version.stdout,
            }
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build Apache findings."""

        metrics = raw_data["metrics"]
        if not metrics["active"] and not metrics["version_output"]:
            return [
                service_finding("apache", False, "Apache non detecte.", optional=True)
            ]

        findings = [
            service_finding(
                metrics["service"] or "apache",
                metrics["active"],
                "Apache detecte.",
            )
        ]
        if metrics["version_output"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Version Apache",
                    metrics["version_output"].splitlines()[0],
                    "Verifier les modules et la configuration SSL.",
                    ["httpd -v", "apache2 -v"],
                )
            )
        return findings
