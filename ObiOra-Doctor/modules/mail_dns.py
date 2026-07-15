"""Mail DNS (SPF/DKIM) and outbound mail security."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class MailDnsModule(DiagnosticModule):
    """Check mail stack and DNS records when mail is sent from this host."""

    name = "mail_dns"
    title = "Mail DNS"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        postfix, _ = systemd_active(self.runner, "postfix")
        exim, _ = systemd_active(self.runner, "exim4")
        hostname = self.runner.run(["hostname", "-f"])
        host = hostname.stdout.strip() or "localhost"

        spf = self.runner.run(["dig", "+short", "TXT", host], timeout_seconds=10)
        dkim_selectors = ["default", "mail", "selector1", "google"]
        dkim_found = False
        for sel in dkim_selectors:
            dkim = self.runner.run(["dig", "+short", "TXT", f"{sel}._domainkey.{host}"], timeout_seconds=8)
            if dkim.ok and "v=DKIM1" in dkim.stdout:
                dkim_found = True
                break

        spf_record = ""
        for line in spf.stdout.splitlines():
            if "v=spf1" in line.lower():
                spf_record = line.strip()
                break

        return {
            "metrics": {
                "mail_active": postfix or exim,
                "mail_service": "postfix" if postfix else ("exim" if exim else None),
                "hostname": host,
                "spf_present": bool(spf_record),
                "spf_record": spf_record[:200],
                "dkim_present": dkim_found,
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        if not m["mail_active"]:
            return [
                Finding(
                    Severity.INFO,
                    "Pas de serveur mail local",
                    "Postfix/Exim inactif.",
                    "Aucune action si le serveur n'envoie pas de mail.",
                )
            ]

        findings: list[Finding] = []
        if not m["spf_present"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Enregistrement SPF absent",
                    f"Aucun SPF pour {m['hostname']}.",
                    "Ajouter un TXT SPF pour limiter l'usurpation.",
                    [f"dig TXT {m['hostname']}"],
                )
            )
        if not m["dkim_present"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "DKIM non detecte",
                    f"Aucun selector DKIM trouve pour {m['hostname']}.",
                    "Configurer DKIM si envoi de mail sortant.",
                )
            )
        if m["spf_present"] and m["dkim_present"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "SPF et DKIM detectes",
                    "Configuration mail DNS de base presente.",
                    "Verifier DMARC en complement.",
                )
            )
        return findings
