"""RAM and swap diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class RamModule(DiagnosticModule):
    """Collect and diagnose memory information."""

    name = "ram"
    title = "RAM"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect memory data from `/proc/meminfo` and `free`."""

        meminfo = self._read_meminfo()
        free = self.runner.run(["free", "-b"])
        return {
            "meminfo": meminfo,
            "free": free.to_dict(),
            "metrics": {
                "mem_total_kb": meminfo.get("MemTotal", 0),
                "mem_available_kb": meminfo.get("MemAvailable", 0),
                "swap_total_kb": meminfo.get("SwapTotal", 0),
                "swap_free_kb": meminfo.get("SwapFree", 0),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build RAM findings from collected raw data."""

        metrics = raw_data["metrics"]
        findings: list[Finding] = []

        total = int(metrics.get("mem_total_kb") or 0)
        available = int(metrics.get("mem_available_kb") or 0)
        swap_total = int(metrics.get("swap_total_kb") or 0)

        if not total:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Meminfo indisponible",
                    "Impossible de lire la memoire depuis /proc/meminfo.",
                    "Verifier que le scan tourne sur Linux.",
                    ["cat /proc/meminfo"],
                )
            )
            return findings

        available_percent = round((available / total) * 100, 2)
        findings.append(
            Finding(
                Severity.INFO,
                "Memoire detectee",
                f"{round(total / 1024 / 1024, 2)} Go RAM, {available_percent}% disponible.",
                "Aucune action requise si la pression memoire reste stable.",
                ["free -h"],
            )
        )

        if available_percent < 10:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Pression memoire critique",
                    f"Seulement {available_percent}% de RAM disponible.",
                    "Identifier les processus consommateurs et verifier les OOM.",
                    ["ps aux --sort=-%mem", "journalctl -k | grep -i oom"],
                )
            )
        elif available_percent < 20:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Pression memoire elevee",
                    f"Seulement {available_percent}% de RAM disponible.",
                    "Surveiller la consommation et verifier les pics applicatifs.",
                    ["free -h", "vmstat 1 5"],
                )
            )

        if swap_total == 0:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Swap inactive",
                    "Aucun swap n'est configure sur ce serveur.",
                    "Aucune action requise si c'est volontaire.",
                    ["swapon --show"],
                )
            )

        return findings

    @staticmethod
    def _read_meminfo() -> dict[str, int]:
        """Parse `/proc/meminfo` into a dictionary."""

        path = Path("/proc/meminfo")
        if not path.exists():
            return {}

        values: dict[str, int] = {}
        for line in path.read_text(encoding="utf-8").splitlines():
            key, _, value = line.partition(":")
            parts = value.strip().split()
            if parts and parts[0].isdigit():
                values[key] = int(parts[0])
        return values
