"""Virtualizor and KVM diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class VirtualizorModule(DiagnosticModule):
    """Collect and diagnose Virtualizor and libvirt state."""

    name = "virtualizor"
    title = "Virtualizor"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect Virtualizor, libvirt and VM information."""

        service = self.runner.run(
            ["systemctl", "is-active", "virtualizor"], timeout_seconds=5
        )
        status = self.runner.run(
            ["systemctl", "status", "virtualizor", "--no-pager"], timeout_seconds=5
        )
        virsh = self.runner.run(["virsh", "list", "--all"], timeout_seconds=8)
        nodeinfo = self.runner.run(["virsh", "nodeinfo"], timeout_seconds=8)
        return {
            "service": service.to_dict(),
            "status": status.to_dict(),
            "virsh": virsh.to_dict(),
            "nodeinfo": nodeinfo.to_dict(),
            "metrics": {
                "systemctl_available": not service.missing,
                "virtualizor_active": service.stdout.strip() == "active",
                "virsh_available": not virsh.missing,
                "libvirt_reachable": virsh.ok,
                "vm_count": self._vm_count(virsh.stdout),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build Virtualizor findings from collected raw data."""

        metrics = raw_data["metrics"]
        findings: list[Finding] = []

        if not metrics["systemctl_available"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "systemctl indisponible",
                    "Impossible de verifier le service Virtualizor via systemd.",
                    "Verifier manuellement le gestionnaire de services.",
                    ["systemctl status virtualizor"],
                )
            )
        elif metrics["virtualizor_active"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Virtualizor actif",
                    "Le service Virtualizor est actif selon systemd.",
                    "Aucune action requise.",
                    ["systemctl status virtualizor --no-pager"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Virtualizor inactif ou non installe",
                    raw_data["service"]["stdout"] or raw_data["service"]["stderr"],
                    "Verifier si le serveur est cense executer Virtualizor.",
                    ["systemctl status virtualizor --no-pager"],
                )
            )

        if not metrics["virsh_available"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "virsh non detecte",
                    "La commande virsh n'est pas disponible.",
                    "Aucune action requise si KVM/libvirt n'est pas utilise.",
                    ["which virsh"],
                )
            )
        elif not metrics["libvirt_reachable"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "libvirt inaccessible",
                    raw_data["virsh"]["stderr"] or "virsh list n'a pas abouti.",
                    "Verifier libvirtd/virtqemud et les permissions.",
                    ["virsh list --all", "systemctl status libvirtd"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Inventaire VM collecte",
                    f"{metrics['vm_count']} VM(s) detectee(s) via virsh.",
                    "Aucune action requise.",
                    ["virsh list --all"],
                )
            )

        return findings

    @staticmethod
    def _vm_count(output: str) -> int:
        """Count VM rows in `virsh list --all` output."""

        rows = [
            line
            for line in output.splitlines()
            if line.strip() and not line.startswith(" Id") and not line.startswith("---")
        ]
        return len(rows)
