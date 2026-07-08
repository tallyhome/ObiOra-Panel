"""SMART disk diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class SmartModule(DiagnosticModule):
    """Collect and diagnose SMART disk health."""

    name = "smart"
    title = "SMART"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect SMART scan and health data."""

        scan = self.runner.run(["smartctl", "--scan"])
        devices = self._parse_devices(scan.stdout)
        health: list[dict[str, Any]] = []
        for device in devices[:5]:
            health_result = self.runner.run(
                ["smartctl", "-H", device], timeout_seconds=10
            )
            health.append(
                {
                    "device": device,
                    "ok": health_result.ok,
                    "output": health_result.stdout,
                    "failed": "FAILED" in health_result.stdout.upper(),
                }
            )
        return {
            "scan": scan.to_dict(),
            "health": health,
            "metrics": {
                "smartctl_available": not scan.missing,
                "device_count": len(devices),
                "failed_devices": [item["device"] for item in health if item["failed"]],
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build SMART findings."""

        metrics = raw_data["metrics"]
        if not metrics["smartctl_available"]:
            return [
                Finding(
                    Severity.INFO,
                    "smartctl non detecte",
                    "L'outil smartmontools n'est pas installe.",
                    "Installer smartmontools pour activer les controles SMART.",
                    ["which smartctl"],
                )
            ]

        findings = [
            Finding(
                Severity.INFO,
                "Inventaire SMART",
                f"{metrics['device_count']} disque(s) detecte(s).",
                "Aucune action requise.",
                ["smartctl --scan"],
            )
        ]
        if metrics["failed_devices"]:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Echec SMART detecte",
                    "Disques en echec: " + ", ".join(metrics["failed_devices"]),
                    "Planifier le remplacement des disques concernes.",
                    ["smartctl -a <device>"],
                )
            )
        return findings

    @staticmethod
    def _parse_devices(output: str) -> list[str]:
        """Parse device paths from smartctl --scan output."""

        devices: list[str] = []
        for line in output.splitlines():
            parts = line.split()
            if parts:
                devices.append(parts[0])
        return devices
