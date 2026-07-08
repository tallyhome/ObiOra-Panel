"""CPU diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class CpuModule(DiagnosticModule):
    """Collect and diagnose CPU information."""

    name = "cpu"
    title = "CPU"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect CPU raw data from `lscpu` and `/proc/loadavg`."""

        lscpu = self.runner.run(["lscpu"])
        loadavg = ""
        if Path("/proc/loadavg").exists():
            loadavg = Path("/proc/loadavg").read_text(encoding="utf-8").strip()
        return {
            "lscpu": lscpu.to_dict(),
            "loadavg": loadavg,
            "metrics": {
                "lscpu_available": lscpu.ok,
                "loadavg": loadavg,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build CPU findings from collected raw data."""

        findings: list[Finding] = []
        lscpu = raw_data["lscpu"]

        if lscpu["missing"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Commande lscpu absente",
                    "Impossible de collecter les details CPU via lscpu.",
                    "Installer util-linux ou verifier le PATH.",
                    ["which lscpu"],
                )
            )
        elif lscpu["returncode"] == 0:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Inventaire CPU collecte",
                    "Les informations CPU ont ete collectees avec succes.",
                    "Aucune action requise.",
                    ["lscpu"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Inventaire CPU incomplet",
                    lscpu["stderr"] or "lscpu a retourne une erreur.",
                    "Verifier l'acces aux informations CPU.",
                    ["lscpu"],
                )
            )

        loadavg = raw_data.get("loadavg")
        if loadavg:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Charge systeme detectee",
                    f"Load average: {loadavg}",
                    "Comparer la charge au nombre de coeurs disponibles.",
                    ["cat /proc/loadavg"],
                )
            )

        return findings
