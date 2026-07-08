"""Network diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class NetworkModule(DiagnosticModule):
    """Collect and diagnose network state."""

    name = "network"
    title = "Network"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect network data from `ip`, `ss` and DNS tools."""

        addresses = self.runner.run(["ip", "addr"])
        routes = self.runner.run(["ip", "route"])
        stats = self.runner.run(["ip", "-s", "link"])
        sockets = self.runner.run(["ss", "-tulpen"])
        return {
            "addresses": addresses.to_dict(),
            "routes": routes.to_dict(),
            "stats": stats.to_dict(),
            "sockets": sockets.to_dict(),
            "metrics": {
                "ip_available": addresses.ok,
                "routes_available": routes.ok,
                "listening_sockets_available": sockets.ok,
            },
        }

    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Build network findings from collected raw data."""

        findings: list[Finding] = []
        addresses = raw_data["addresses"]
        routes = raw_data["routes"]
        sockets = raw_data["sockets"]

        if addresses["missing"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Commande ip absente",
                    "Impossible de collecter les interfaces reseau.",
                    "Installer iproute2.",
                    ["which ip"],
                )
            )
            return findings

        if addresses["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Interfaces reseau collectees",
                    "Les adresses reseau ont ete collectees via ip addr.",
                    "Verifier les erreurs RX/TX si un probleme reseau existe.",
                    ["ip addr", "ip -s link"],
                )
            )

        if routes["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Routes collectees",
                    "La table de routage a ete collectee.",
                    "Aucune action requise.",
                    ["ip route"],
                )
            )

        if sockets["missing"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Commande ss absente",
                    "Impossible de lister les ports en ecoute.",
                    "Installer iproute2 pour disposer de ss.",
                    ["which ss"],
                )
            )
        elif sockets["ok"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Ports en ecoute collectes",
                    "Les sockets TCP/UDP en ecoute ont ete collectees.",
                    "Verifier l'exposition publique des services sensibles.",
                    ["ss -tulpen"],
                )
            )

        return findings
