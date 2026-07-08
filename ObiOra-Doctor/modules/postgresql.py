"""PostgreSQL diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import service_finding, systemd_active


class PostgresqlModule(DiagnosticModule):
    """Collect and diagnose PostgreSQL state."""

    name = "postgresql"
    title = "PostgreSQL"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect PostgreSQL service and version data."""

        active, _ = systemd_active(self.runner, "postgresql")
        version = self.runner.run(["psql", "--version"])
        return {
            "active": active,
            "version": version.to_dict(),
            "metrics": {
                "service_active": active,
                "client_available": not version.missing,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build PostgreSQL findings."""

        metrics = raw_data["metrics"]
        if not metrics["service_active"] and not metrics["client_available"]:
            return [
                Finding(
                    Severity.INFO,
                    "PostgreSQL non detecte",
                    "Aucun service ou client PostgreSQL detecte.",
                    "Aucune action requise si PostgreSQL n'est pas utilise.",
                    ["systemctl status postgresql"],
                )
            ]

        findings: list[Finding] = []
        if metrics["service_active"]:
            findings.append(service_finding("postgresql", True, "Service PostgreSQL actif."))
        if raw_data["version"]["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Version PostgreSQL",
                    raw_data["version"]["stdout"],
                    "Verifier les mises a jour de securite.",
                    ["psql --version"],
                )
            )
        return findings
