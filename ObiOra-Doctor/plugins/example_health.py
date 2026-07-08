"""Example Obiora Doctor plugin."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class ExampleHealthPlugin(DiagnosticModule):
    name = "example_health"
    title = "Example Health"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        hostname_file = Path("/etc/hostname")
        return {
            "metrics": {
                "hostname_file_exists": hostname_file.exists(),
            }
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        if raw_data["metrics"]["hostname_file_exists"]:
            return [
                Finding(
                    Severity.INFO,
                    "Hostname file",
                    "/etc/hostname present.",
                    "Aucune action requise.",
                )
            ]
        return [
            Finding(
                Severity.WARNING,
                "Hostname file",
                "/etc/hostname absent.",
                "Verifier la configuration systeme.",
            )
        ]
