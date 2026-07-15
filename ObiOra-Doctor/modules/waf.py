"""WAF, ModSecurity and nginx rate limiting audit."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class WafModule(DiagnosticModule):
    """Detect ModSecurity and nginx rate limiting."""

    name = "waf"
    title = "WAF"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        nginx_active, _ = systemd_active(self.runner, "nginx")
        apache_active, _ = systemd_active(self.runner, "httpd")
        apache2_active, _ = systemd_active(self.runner, "apache2")

        modsec = self.runner.run(
            ["bash", "-c", "grep -r ModSecurity /etc/nginx /etc/apache2 /etc/httpd 2>/dev/null | head -5"],
            timeout_seconds=10,
        )
        rate_limit = self.runner.run(
            ["bash", "-c", "grep -r limit_req /etc/nginx 2>/dev/null | head -5"],
            timeout_seconds=10,
        )

        return {
            "metrics": {
                "nginx_active": nginx_active,
                "apache_active": apache_active or apache2_active,
                "modsecurity_detected": bool(modsec.stdout.strip()),
                "rate_limit_detected": bool(rate_limit.stdout.strip()),
            },
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        if not m["nginx_active"] and not m["apache_active"]:
            return [
                Finding(
                    Severity.INFO,
                    "Pas de serveur web actif",
                    "Nginx/Apache inactif.",
                    "Aucune action requise.",
                )
            ]

        findings: list[Finding] = []
        if not m["modsecurity_detected"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "ModSecurity non detecte",
                    "Aucune config ModSecurity trouvee.",
                    "Envisager ModSecurity ou WAF externe si sites exposes.",
                )
            )
        if m["nginx_active"] and not m["rate_limit_detected"]:
            findings.append(
                Finding(
                    Severity.WARNING,
                    "Rate limiting nginx absent",
                    "Aucune directive limit_req detectee.",
                    "Configurer limit_req_zone pour limiter le brute-force HTTP.",
                    ["grep limit_req /etc/nginx/"],
                )
            )
        if m["modsecurity_detected"] or m["rate_limit_detected"]:
            findings.append(
                Finding(
                    Severity.INFO,
                    "Protections web detectees",
                    f"ModSecurity={m['modsecurity_detected']}, rate_limit={m['rate_limit_detected']}.",
                    "Verifier les regles et logs regulierement.",
                )
            )
        return findings
