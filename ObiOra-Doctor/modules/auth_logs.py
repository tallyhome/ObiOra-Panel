"""SSH authentication logs and brute-force detection."""

from __future__ import annotations

import re
from collections import Counter
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class AuthLogsModule(DiagnosticModule):
    """Analyze failed SSH login attempts."""

    name = "auth_logs"
    title = "Auth Logs"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        journal = self.runner.run(
            [
                "journalctl",
                "-u",
                "ssh",
                "-u",
                "sshd",
                "--since",
                "24 hours ago",
                "--no-pager",
                "-q",
            ],
            timeout_seconds=20,
        )
        auth_log = self.runner.run(
            ["bash", "-c", "grep -h 'Failed password\\|Invalid user' /var/log/auth.log /var/log/secure 2>/dev/null | tail -500"],
            timeout_seconds=15,
        )

        text = journal.stdout + "\n" + auth_log.stdout
        failed = len(re.findall(r"Failed password|Invalid user|authentication failure", text, re.I))
        ips = re.findall(r"from\s+(\d+\.\d+\.\d+\.\d+)", text)
        ip_counts = Counter(ips)
        top_ips = ip_counts.most_common(5)

        return {
            "metrics": {
                "failed_attempts_24h": failed,
                "unique_ips": len(ip_counts),
                "top_attacker_ips": [{"ip": ip, "count": cnt} for ip, cnt in top_ips],
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        findings: list[Finding] = []

        if m["failed_attempts_24h"] > 100:
            findings.append(
                Finding(
                    Severity.CRITICAL,
                    "Brute-force SSH intense",
                    f"{m['failed_attempts_24h']} tentatives echouees (24h), {m['unique_ips']} IP(s).",
                    "Verifier fail2ban actif et restreindre SSH si possible.",
                    ["fail2ban-client status sshd", "journalctl -u ssh --since '1 hour ago'"],
                )
            )
        elif m["failed_attempts_24h"] > 20:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Tentatives SSH echouees",
                    f"{m['failed_attempts_24h']} tentatives (24h).",
                    "S'assurer que fail2ban protege SSH.",
                    ["grep 'Failed password' /var/log/auth.log | tail -20"],
                )
            )

        if m["top_attacker_ips"]:
            top = m["top_attacker_ips"][0]
            if top["count"] > 50:
                findings.append(
                    Finding(
                        Severity.WARNING,
                        "IP attaquante dominante",
                        f"{top['ip']}: {top['count']} tentatives.",
                        "Envisager blocage firewall de cette IP.",
                        [f"iptables -A INPUT -s {top['ip']} -j DROP"],
                    )
                )

        if not findings:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Logs auth SSH calmes",
                    f"{m['failed_attempts_24h']} echec(s) sur 24h.",
                    "Continuer la surveillance fail2ban.",
                )
            )
        return findings
