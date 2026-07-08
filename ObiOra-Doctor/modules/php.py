"""PHP diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class PhpModule(DiagnosticModule):
    """Collect and diagnose PHP installations."""

    name = "php"
    title = "PHP"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect PHP CLI and FPM service data."""

        version = self.runner.run(["php", "-v"])
        fpm_active, _ = systemd_active(self.runner, "php-fpm")
        fpm82_active, _ = systemd_active(self.runner, "php8.2-fpm")
        fpm83_active, _ = systemd_active(self.runner, "php8.3-fpm")
        return {
            "version": version.to_dict(),
            "metrics": {
                "php_available": not version.missing,
                "fpm_active": fpm_active or fpm82_active or fpm83_active,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build PHP findings."""

        if not raw_data["metrics"]["php_available"]:
            return [
                Finding(
                    Severity.INFO,
                    "PHP non detecte",
                    "Le binaire php n'est pas disponible.",
                    "Aucune action requise si PHP n'est pas utilise.",
                    ["which php"],
                )
            ]

        findings = [
            Finding(
                Severity.INFO,
                "PHP detecte",
                raw_data["version"]["stdout"].splitlines()[0],
                "Verifier les versions PHP exposees aux sites.",
                ["php -v"],
            )
        ]
        if raw_data["metrics"]["fpm_active"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "PHP-FPM actif",
                    "Un service PHP-FPM est actif.",
                    "Verifier les pools et limites pm.max_children.",
                    ["systemctl status php-fpm"],
                )
            )
        return findings
