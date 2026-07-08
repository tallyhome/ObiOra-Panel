"""RAID diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class RaidModule(DiagnosticModule):
    """Collect and diagnose software RAID state."""

    name = "raid"
    title = "RAID"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect RAID data from mdadm."""

        detail = self.runner.run(["cat", "/proc/mdstat"])
        mdadm = self.runner.run(["mdadm", "--detail", "--scan"])
        return {
            "mdstat": detail.to_dict(),
            "mdadm": mdadm.to_dict(),
            "metrics": {
                "mdstat_available": detail.ok,
                "arrays_detected": detail.stdout.count("active") if detail.ok else 0,
                "degraded": "degraded" in detail.stdout.lower() if detail.ok else False,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build RAID findings."""

        metrics = raw_data["metrics"]
        if not metrics["mdstat_available"]:
            return [
                Finding(
                    Severity.INFO,
                    "RAID non detecte",
                    "/proc/mdstat indisponible ou vide.",
                    "Aucune action requise si RAID logiciel non utilise.",
                    ["cat /proc/mdstat"],
                )
            ]

        if metrics["degraded"]:
            return [
                Finding(
                    Severity.CRITICAL,
                    "RAID degrade",
                    raw_data["mdstat"]["stdout"],
                    "Reparer le tableau RAID immediatement.",
                    ["cat /proc/mdstat", "mdadm --detail /dev/md0"],
                )
            ]

        return [
            Finding(
                Severity.INFO,
                "RAID operationnel",
                f"{metrics['arrays_detected']} tableau(x) actif(s).",
                "Aucune action requise.",
                ["cat /proc/mdstat"],
            )
        ]
