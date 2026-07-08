"""Swap diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class SwapModule(DiagnosticModule):
    """Collect and diagnose swap configuration and usage."""

    name = "swap"
    title = "Swap"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect swap data from /proc/swaps and swapon."""

        swaps = self.runner.run(["swapon", "--show"])
        swappiness = ""
        path = Path("/proc/sys/vm/swappiness")
        if path.exists():
            swappiness = path.read_text(encoding="utf-8").strip()
        return {
            "swaps": swaps.to_dict(),
            "metrics": {
                "swap_configured": swaps.ok and bool(swaps.stdout.strip()),
                "swappiness": int(swappiness) if swappiness.isdigit() else None,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build swap findings."""

        metrics = raw_data["metrics"]
        if not metrics["swap_configured"]:
            return [
                Finding(
                    Severity.INFO,
                    "Swap inactive",
                    "Aucun swap configure sur ce serveur.",
                    "Aucune action requise si c'est volontaire.",
                    ["swapon --show"],
                )
            ]

        findings = [
            Finding(
                Severity.INFO,
                "Swap configure",
                raw_data["swaps"]["stdout"],
                "Surveiller l'utilisation swap en charge.",
                ["swapon --show", "free -h"],
            )
        ]
        swappiness = metrics.get("swappiness")
        if swappiness is not None and swappiness > 60:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Swappiness eleve",
                    f"vm.swappiness = {swappiness}",
                    "Reduire swappiness sur serveurs avec beaucoup de RAM.",
                    ["cat /proc/sys/vm/swappiness"],
                )
            )
        return findings
