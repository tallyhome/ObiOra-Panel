"""Kernel diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class KernelModule(DiagnosticModule):
    """Collect and diagnose kernel state."""

    name = "kernel"
    title = "Kernel"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect kernel version and recent kernel warnings."""

        uname = self.runner.run(["uname", "-a"])
        dmesg = self.runner.run(["dmesg", "--level=err,warn"], timeout_seconds=5)
        failed = self.runner.run(["systemctl", "--failed", "--no-pager"], timeout_seconds=5)
        return {
            "uname": uname.to_dict(),
            "dmesg": dmesg.to_dict(),
            "failed_units": failed.to_dict(),
            "metrics": {
                "uname_available": uname.ok,
                "kernel_warning_lines": len(dmesg.stdout.splitlines()) if dmesg.ok else 0,
                "systemd_available": not failed["missing"],
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build kernel findings from collected raw data."""

        findings: list[Finding] = []
        uname = raw_data["uname"]
        dmesg = raw_data["dmesg"]
        failed = raw_data["failed_units"]

        if uname["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Kernel identifie",
                    uname["stdout"],
                    "Aucune action requise.",
                    ["uname -a"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Kernel non identifie",
                    uname["stderr"] or "La commande uname n'a pas abouti.",
                    "Verifier l'environnement d'execution.",
                    ["uname -a"],
                )
            )

        warning_lines = raw_data["metrics"]["kernel_warning_lines"]
        if warning_lines > 0:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Messages kernel a verifier",
                    f"{warning_lines} ligne(s) dmesg err/warn detectee(s).",
                    "Analyser dmesg pour identifier erreurs disque, reseau ou drivers.",
                    ["dmesg --level=err,warn"],
                )
            )

        if failed["ok"] and "0 loaded units listed" not in failed["stdout"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Services systemd echoues",
                    "systemctl --failed retourne des unites a verifier.",
                    "Corriger les services echoues ou confirmer qu'ils sont attendus.",
                    ["systemctl --failed --no-pager"],
                )
            )

        return findings
