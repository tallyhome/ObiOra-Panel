"""LXC / LXD diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class LxcModule(DiagnosticModule):
    """Collect and diagnose LXC/LXD containers."""

    name = "lxc"
    title = "LXC"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect LXC and LXD container data."""

        lxc = self.runner.run(["lxc-ls", "--active"])
        lxd = self.runner.run(["lxc", "list"])
        return {
            "lxc": lxc.to_dict(),
            "lxd": lxd.to_dict(),
            "metrics": {
                "lxc_available": not lxc.missing,
                "lxd_available": not lxd.missing,
                "container_count": self._count(lxc.stdout) + self._count(lxd.stdout),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build LXC findings."""

        metrics = raw_data["metrics"]
        if not metrics["lxc_available"] and not metrics["lxd_available"]:
            return [
                Finding(
                    Severity.INFO,
                    "LXC non detecte",
                    "Ni lxc-ls ni lxc (LXD) ne sont disponibles.",
                    "Aucune action requise si LXC n'est pas utilise.",
                    ["which lxc-ls", "which lxc"],
                )
            ]

        return [
            Finding(
                Severity.INFO,
                "Conteneurs LXC detectes",
                f"{metrics['container_count']} conteneur(s) listes.",
                "Verifier les limites cgroups et AppArmor.",
                ["lxc-ls --active", "lxc list"],
            )
        ]

    @staticmethod
    def _count(output: str) -> int:
        """Count non-empty lines."""

        return len([line for line in output.splitlines() if line.strip()])
