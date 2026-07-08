"""KVM / libvirt diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class KvmModule(DiagnosticModule):
    """Collect and diagnose KVM and libvirt state."""

    name = "kvm"
    title = "KVM"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect libvirt and QEMU information."""

        libvirtd, _ = systemd_active(self.runner, "libvirtd")
        virtqemud, _ = systemd_active(self.runner, "virtqemud")
        virsh = self.runner.run(["virsh", "list", "--all"])
        nodeinfo = self.runner.run(["virsh", "nodeinfo"])
        return {
            "virsh": virsh.to_dict(),
            "nodeinfo": nodeinfo.to_dict(),
            "metrics": {
                "daemon_active": libvirtd or virtqemud,
                "libvirt_reachable": virsh.ok,
                "vm_count": self._vm_count(virsh.stdout),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build KVM findings."""

        metrics = raw_data["metrics"]
        if not metrics["daemon_active"] and raw_data["virsh"]["missing"]:
            return [
                Finding(
                    Severity.INFO,
                    "KVM non detecte",
                    "libvirt/KVM non disponible sur ce serveur.",
                    "Aucune action requise si la virtualisation n'est pas utilisee.",
                    ["which virsh"],
                )
            ]

        findings: list[Finding] = []
        if metrics["daemon_active"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Daemon libvirt actif",
                    "Le service de virtualisation KVM est actif.",
                    "Aucune action requise.",
                    ["systemctl status libvirtd"],
                )
            )
        if metrics["libvirt_reachable"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Inventaire VM KVM",
                    f"{metrics['vm_count']} VM(s) detectee(s).",
                    "Aucune action requise.",
                    ["virsh list --all"],
                )
            )
        elif not raw_data["virsh"]["missing"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "libvirt inaccessible",
                    raw_data["virsh"]["stderr"] or "virsh list a echoue.",
                    "Verifier libvirtd et les permissions.",
                    ["virsh list --all"],
                )
            )
        return findings

    @staticmethod
    def _vm_count(output: str) -> int:
        """Count VM rows in virsh output."""

        return len(
            [
                line
                for line in output.splitlines()
                if line.strip() and not line.startswith(" Id") and not line.startswith("---")
            ]
        )
