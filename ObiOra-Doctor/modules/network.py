"""Network diagnostic module."""

from __future__ import annotations

import re
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class NetworkModule(DiagnosticModule):
    """Collect and diagnose network state."""

    name = "network"
    title = "Network"

    _RISKY_PORTS = {
        21: "FTP",
        23: "Telnet",
        3306: "MySQL",
        5432: "PostgreSQL",
        6379: "Redis",
        27017: "MongoDB",
        9100: "Obiora Agent",
        10000: "Webmin",
        8080: "HTTP-Alt",
        8443: "HTTPS-Alt",
        4081: "Virtualizor",
        2087: "WHM",
    }

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect network data from `ip`, `ss` and DNS tools."""

        addresses = self.runner.run(["ip", "addr"])
        routes = self.runner.run(["ip", "route"])
        stats = self.runner.run(["ip", "-s", "link"])
        sockets = self.runner.run(["ss", "-tulpen"])
        listening = self._parse_listening(sockets.stdout if sockets.ok else "")

        return {
            "addresses": addresses.to_dict(),
            "routes": routes.to_dict(),
            "stats": stats.to_dict(),
            "sockets": sockets.to_dict(),
            "metrics": {
                "ip_available": addresses.ok,
                "routes_available": routes.ok,
                "listening_sockets_available": sockets.ok,
                "listening_count": len(listening),
                "public_listeners": [l for l in listening if l.get("public")],
                "risky_public": [
                    l for l in listening if l.get("public") and l.get("port") in self._RISKY_PORTS
                ],
            },
            "listening": listening[:30],
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
        metrics = raw_data["metrics"]

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

        risky = metrics.get("risky_public") or []
        if risky:
            details = ", ".join(
                f"{self._RISKY_PORTS.get(r['port'], r['port'])}:{r['port']} ({r.get('process', '?')})"
                for r in risky[:6]
            )
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Services sensibles exposes publiquement",
                    details,
                    "Restreindre via pare-feu ou bind localhost.",
                    ["ss -tulpen"],
                )
            )

        public_count = len(metrics.get("public_listeners") or [])
        if public_count > 20:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Nombreux ports publics",
                    f"{public_count} socket(s) en ecoute publique.",
                    "Auditer chaque service expose.",
                    ["ss -tulpen"],
                )
            )

        if routes["ok"] and not risky:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Ports en ecoute audites",
                    f"{metrics.get('listening_count', 0)} listener(s), {public_count} public(s).",
                    "Verifier regulierement l'exposition des services.",
                    ["ss -tulpen"],
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

        return findings

    def _parse_listening(self, output: str) -> list[dict[str, Any]]:
        listeners: list[dict[str, Any]] = []
        for line in output.splitlines():
            if "LISTEN" not in line and "UNCONN" not in line:
                continue
            port_match = re.search(r":(\d+)\s", line)
            if not port_match:
                continue
            port = int(port_match.group(1))
            public = bool(re.search(r"0\.0\.0\.0:|\\[::\\]:|\\*", line))
            proc_match = re.search(r'users:\(\("([^"]+)"', line)
            listeners.append(
                {
                    "port": port,
                    "public": public,
                    "process": proc_match.group(1) if proc_match else "",
                    "line": line.strip()[:120],
                }
            )
        return listeners
