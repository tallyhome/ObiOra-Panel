"""Redis diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class RedisModule(DiagnosticModule):
    """Collect and diagnose Redis state."""

    name = "redis"
    title = "Redis"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        active, _ = systemd_active(self.runner, "redis")
        active_server, _ = systemd_active(self.runner, "redis-server")
        ping = self.runner.run(["redis-cli", "ping"])
        info = self.runner.run(["redis-cli", "info", "server"])
        return {
            "metrics": {
                "service_active": active or active_server,
                "reachable": ping.stdout.strip().upper() == "PONG",
                "version_line": info.stdout.splitlines()[0] if info.ok else "",
            }
        }

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        if not m["service_active"] and not m["reachable"]:
            return [Finding(Severity.INFO, "Redis non detecte", "Redis absent.", "Aucune action si non utilise.")]
        findings = [Finding(Severity.INFO, "Redis operationnel", m.get("version_line", "Redis actif."), "Verifier maxmemory et persistence.")]
        if m["service_active"] and not m["reachable"]:
            findings.append(Finding(Severity.WARNING, "Redis injoignable", "Service actif mais redis-cli ping echoue.", "Verifier bind et mot de passe."))
        return findings
