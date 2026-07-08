"""Firewall diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class FirewallModule(DiagnosticModule):
    """Collect and diagnose firewall state."""

    name = "firewall"
    title = "Firewall"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect firewalld, ufw and nftables data."""

        firewalld_active, _ = systemd_active(self.runner, "firewalld")
        ufw_status = self.runner.run(["ufw", "status"])
        nft = self.runner.run(["nft", "list", "ruleset"])
        return {
            "ufw": ufw_status.to_dict(),
            "nft": nft.to_dict(),
            "metrics": {
                "firewalld_active": firewalld_active,
                "ufw_available": not ufw_status.missing,
                "ufw_active": "active" in ufw_status.stdout.lower(),
                "nft_available": not nft.missing,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build firewall findings."""

        metrics = raw_data["metrics"]
        active_layers = []
        if metrics["firewalld_active"]:
            active_layers.append("firewalld")
        if metrics["ufw_active"]:
            active_layers.append("ufw")
        if metrics["nft_available"] and raw_data["nft"]["ok"]:
            active_layers.append("nftables")

        if not active_layers:
            return [
                Finding(
                    Severity.WARNING,
                    "Aucun pare-feu detecte",
                    "Ni firewalld, ni ufw, ni nftables actif detecte.",
                    "Verifier la politique de securite reseau du serveur.",
                    ["systemctl status firewalld", "ufw status", "nft list ruleset"],
                )
            ]

        return [
            Finding(
                Severity.INFO,
                "Pare-feu detecte",
                "Couches actives: " + ", ".join(active_layers),
                "Verifier les regles exposees publiquement.",
                ["firewall-cmd --list-all", "ufw status", "nft list ruleset"],
            )
        ]
