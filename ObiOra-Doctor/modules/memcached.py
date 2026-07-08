"""Memcached diagnostic module."""

from __future__ import annotations

from typing import Any

from core.models import Finding, Severity
from core.module import DiagnosticModule
from modules._helpers import systemd_active


class MemcachedModule(DiagnosticModule):
    """Collect and diagnose Memcached state."""

    name = "memcached"
    title = "Memcached"

    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        active, _ = systemd_active(self.runner, "memcached")
        stats = self.runner.run(["echo", "stats", "|", "nc", "127.0.0.1", "11211"])
        if stats.missing or stats.returncode != 0:
            stats = self.runner.run(["bash", "-c", "echo stats | nc -w1 127.0.0.1 11211"])
        return {"metrics": {"service_active": active, "stats_ok": bool(stats.stdout.strip())}}

    def diagnostic(self, raw_data: dict[str, Any], context: dict[str, Any]) -> list[Finding]:
        m = raw_data["metrics"]
        if not m["service_active"] and not m["stats_ok"]:
            return [Finding(Severity.INFO, "Memcached non detecte", "Memcached absent.", "Aucune action si non utilise.")]
        if m["stats_ok"]:
            return [Finding(Severity.INFO, "Memcached operationnel", "Stats memcached accessibles.", "Surveiller usage memoire cache.")]
        return [Finding(Severity.WARNING, "Memcached inactif", "Service ou port 11211 inaccessible.", "Verifier systemctl status memcached.")]
