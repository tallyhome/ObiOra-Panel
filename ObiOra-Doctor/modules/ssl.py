"""SSL certificate expiry diagnostic module."""

from __future__ import annotations

import re
from datetime import datetime, timezone
from email.utils import parsedate_to_datetime
from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule


class SslModule(DiagnosticModule):
    """Check SSL certificate expiry via openssl when available."""

    name = "ssl"
    title = "SSL"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        nginx = self.runner.run(["nginx", "-T"], timeout_seconds=15)
        server_names = re.findall(r"server_name\s+([^;]+);", nginx.stdout)
        hosts = []
        for block in server_names[:5]:
            for host in block.split():
                host = host.strip()
                if host and host not in {"_", "localhost"} and "." in host:
                    hosts.append(host)
        certs: list[dict[str, Any]] = []
        for host in hosts[:3]:
            result = self.runner.run(
                ["openssl", "s_client", "-connect", f"{host}:443", "-servername", host],
                timeout_seconds=10,
            )
            not_after = ""
            for line in result.stdout.splitlines():
                if "notAfter" in line:
                    not_after = line.split("=", 1)[-1].strip()
            if not_after:
                days_left = self._days_until_expiry(not_after)
                tls_proto = self._check_tls_protocol(host)
                certs.append(
                    {
                        "host": host,
                        "not_after": not_after,
                        "days_left": days_left,
                        "tls_protocol": tls_proto,
                    }
                )
        return {"metrics": {"certificates": certs, "checked_hosts": hosts[:3]}}

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        certs = raw_data["metrics"]["certificates"]
        if not certs:
            return [
                Finding(
                    Severity.INFO,
                    "SSL non verifie",
                    "Aucun certificat verifie automatiquement.",
                    "Verifier manuellement les vhosts.",
                )
            ]
        findings = []
        for cert in certs:
            days_left = cert.get("days_left")
            host = cert["host"]
            not_after = cert["not_after"]
            tls_proto = cert.get("tls_protocol") or ""
            if isinstance(days_left, int) and days_left <= 0:
                level = Severity.CRITICAL
                title = f"Certificat expire — {host}"
                details = f"host={host}, expiration={not_after}, jours restants: {days_left}"
            elif isinstance(days_left, int) and days_left <= 30:
                level = Severity.WARNING
                title = f"Certificat bientot expire — {host}"
                details = f"host={host}, expiration={not_after}, jours restants: {days_left}"
            else:
                level = Severity.INFO
                title = f"Certificat {host}"
                details = f"host={host}, expiration={not_after}, jours restants: {days_left}"
            findings.append(
                Finding(
                    level,
                    title,
                    details,
                    "Renouveler avant expiration (certbot/Let's Encrypt).",
                    [f"openssl s_client -connect {host}:443"],
                )
            )
            if tls_proto and any(v in tls_proto for v in ("TLSv1 ", "TLSv1.0", "TLSv1.1", "SSLv3")):
                findings.append(
                    Finding(
                        Severity.WARNING,
                        f"TLS obsolete — {host}",
                        f"Protocole detecte: {tls_proto}",
                        "Desactiver TLS 1.0/1.1 dans nginx/apache.",
                        [f"openssl s_client -connect {host}:443 -tls1"],
                    )
                )
        return findings

    def _check_tls_protocol(self, host: str) -> str:
        result = self.runner.run(
            ["openssl", "s_client", "-connect", f"{host}:443", "-servername", host],
            timeout_seconds=10,
        )
        for line in (result.stdout + result.stderr).splitlines():
            if "Protocol" in line or "SSL-Session" in line:
                return line.strip()
        return ""

    def _days_until_expiry(self, not_after: str) -> int | None:
        try:
            expiry = parsedate_to_datetime(not_after)
            if expiry.tzinfo is None:
                expiry = expiry.replace(tzinfo=timezone.utc)
            now = datetime.now(timezone.utc)
            return (expiry - now).days
        except (TypeError, ValueError, OverflowError):
            return None
