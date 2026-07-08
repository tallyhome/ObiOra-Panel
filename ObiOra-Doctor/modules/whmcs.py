"""WHMCS diagnostic module.

WHMCS is billing/client management software used by many hosting providers.
This module checks if WHMCS is installed and if core services respond.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class WhmcsModule(DiagnosticModule):
    """Detect WHMCS installation and basic health."""

    name = "whmcs"
    title = "WHMCS"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        paths = [
            Path("/var/www/html/whmcs"),
            Path("/var/www/whmcs"),
            Path("/home/whmcs/public_html"),
        ]
        installed_path = next((p for p in paths if p.exists()), None)
        cron = self.runner.run(["systemctl", "is-active", "crond"])
        return {
            "metrics": {
                "installed": installed_path is not None,
                "path": str(installed_path) if installed_path else "",
                "cron_active": cron.stdout.strip() == "active",
            }
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        if not raw_data["metrics"]["installed"]:
            return [Finding(Severity.INFO, "WHMCS non detecte", "Aucune installation WHMCS trouvee.", "Normal si ce serveur n heberge pas WHMCS.")]
        findings = [Finding(Severity.INFO, "WHMCS detecte", f"Installation: {raw_data['metrics']['path']}", "Verifier cron WHMCS et mises a jour.")]
        if not raw_data["metrics"]["cron_active"]:
            findings.append(Finding(Severity.WARNING, "Cron inactif", "WHMCS depend souvent de cron pour facturation et emails.", "Verifier crond."))
        return findings
