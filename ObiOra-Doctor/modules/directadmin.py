"""DirectAdmin diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class DirectadminModule(DiagnosticModule):
    """Collect and diagnose DirectAdmin state."""

    name = "directadmin"
    title = "DirectAdmin"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect DirectAdmin indicators."""

        da_dir = Path("/usr/local/directadmin").exists()
        service = self.runner.run(
            ["/usr/local/directadmin/directadmin", "status"],
            timeout_seconds=5,
        )
        return {
            "service": service.to_dict(),
            "metrics": {
                "installed": da_dir,
                "running": "running" in service.stdout.lower() if service.ok else False,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build DirectAdmin findings."""

        if not raw_data["metrics"]["installed"]:
            return [
                Finding(
                    Severity.INFO,
                    "DirectAdmin non detecte",
                    "/usr/local/directadmin absent.",
                    "Aucune action requise.",
                )
            ]
        if raw_data["metrics"]["running"]:
            return [
                Finding(
                    Severity.INFO,
                    "DirectAdmin operationnel",
                    "DirectAdmin est installe et actif.",
                    "Verifier la licence et les mises a jour.",
                    ["directadmin status"],
                )
            ]
        return [
            Finding(
                Severity.WARNING,
                "DirectAdmin inactif",
                raw_data["service"]["stderr"] or "DirectAdmin ne repond pas.",
                "Verifier le service DirectAdmin.",
                ["systemctl status directadmin"],
            )
        ]
