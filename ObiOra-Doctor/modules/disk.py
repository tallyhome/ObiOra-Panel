"""Disk diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class DiskModule(DiagnosticModule):
    """Collect and diagnose disk usage information."""

    name = "disk"
    title = "Disk"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect disk data from `df` and `lsblk`."""

        df = self.runner.run(["df", "-P", "-h"])
        inodes = self.runner.run(["df", "-P", "-i"])
        lsblk = self.runner.run(["lsblk"])
        return {
            "df": df.to_dict(),
            "inodes": inodes.to_dict(),
            "lsblk": lsblk.to_dict(),
            "metrics": {
                "df_available": df.ok,
                "lsblk_available": lsblk.ok,
                "critical_mounts": self._critical_mounts(df.stdout),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build disk findings from collected raw data."""

        findings: list[Finding] = []
        df = raw_data["df"]
        critical_mounts = raw_data["metrics"]["critical_mounts"]

        if df["missing"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Commande df absente",
                    "Impossible d'analyser l'espace disque.",
                    "Installer coreutils ou verifier le PATH.",
                    ["which df"],
                )
            )
        elif critical_mounts:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Espace disque critique",
                    "Montages au-dessus de 90%: " + ", ".join(critical_mounts),
                    "Liberer de l'espace ou augmenter la capacite disque.",
                    ["df -h", "du -xhd1 /"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Espace disque verifie",
                    "Aucun montage critique detecte via df.",
                    "Aucune action requise.",
                    ["df -h"],
                )
            )

        if raw_data["lsblk"]["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Inventaire blocs collecte",
                    "La topologie disque a ete collectee via lsblk.",
                    "Aucune action requise.",
                    ["lsblk"],
                )
            )

        return findings

    @staticmethod
    def _critical_mounts(df_output: str) -> list[str]:
        """Return mounts whose usage is above or equal to 90%."""

        critical: list[str] = []
        for line in df_output.splitlines()[1:]:
            parts = line.split()
            if len(parts) < 6:
                continue
            usage = parts[4].rstrip("%")
            if usage.isdigit() and int(usage) >= 90:
                critical.append(parts[5])
        return critical
