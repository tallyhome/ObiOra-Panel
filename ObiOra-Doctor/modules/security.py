"""Security audit diagnostic module."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class SecurityModule(DiagnosticModule):
    """Collect basic Linux security indicators."""

    name = "security"
    title = "Security"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect SSH, SELinux and privileged user indicators."""

        ssh_config = ""
        ssh_path = Path("/etc/ssh/sshd_config")
        if ssh_path.exists():
            ssh_config = ssh_path.read_text(encoding="utf-8", errors="ignore")
        selinux = self.runner.run(["getenforce"])
        sudoers = self.runner.run(["getent", "group", "sudo"])
        wheel = self.runner.run(["getent", "group", "wheel"])
        return {
            "selinux": selinux.to_dict(),
            "metrics": {
                "permit_root_login": "PermitRootLogin yes" in ssh_config,
                "password_auth": "PasswordAuthentication yes" in ssh_config,
                "selinux_enforcing": selinux.stdout.strip().lower() == "enforcing",
                "sudo_group": bool(sudoers.stdout or wheel.stdout),
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build security findings."""

        metrics = raw_data["metrics"]
        findings: list[Finding] = []

        if metrics["permit_root_login"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "SSH root login autorise",
                    "PermitRootLogin yes detecte dans sshd_config.",
                    "Desactiver la connexion SSH root directe.",
                    ["grep PermitRootLogin /etc/ssh/sshd_config"],
                )
            )
        else:
            findings.append(
                Finding(
                    Severity.INFO,
                    "SSH root login restreint",
                    "Aucune autorisation root SSH evidente detectee.",
                    "Aucune action requise.",
                    ["grep PermitRootLogin /etc/ssh/sshd_config"],
                )
            )

        if metrics["password_auth"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Authentification SSH par mot de passe",
                    "PasswordAuthentication yes detecte.",
                    "Preferer les cles SSH et desactiver le mot de passe.",
                    ["grep PasswordAuthentication /etc/ssh/sshd_config"],
                )
            )

        if raw_data["selinux"]["ok"]:
            level = Severity.INFO if metrics["selinux_enforcing"] else Severity.WARNING
            findings.append(
                Finding(
                    level,
                    "SELinux",
                    f"Mode SELinux: {raw_data['selinux']['stdout']}",
                    "Verifier la politique SELinux en production.",
                    ["getenforce"],
                )
            )
        return findings
