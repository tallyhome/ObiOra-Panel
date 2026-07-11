"""Enterprise probe plugins: SSH and ping (standalone)."""

from __future__ import annotations

from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector


class SshCollector(TimedCollector):
    name = "ssh"
    priority = 40

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        inc = self.settings.incident
        return {
            **self.collect_meta(),
            "localhost": self.timed_command(
                "ssh_localhost",
                ["ssh", "-o", "ConnectTimeout=2", "-o", "BatchMode=yes",
                 "-o", "StrictHostKeyChecking=no", "localhost", "true"],
                timeout=inc.ssh_timeout_seconds,
            ),
        }


class PingCollector(TimedCollector):
    name = "ping"
    priority = 45

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        inc = self.settings.incident
        wait = max(1, int(inc.ping_timeout_seconds))
        target = inc.external_ping_target
        return {
            **self.collect_meta(),
            "loopback": self.timed_command(
                "ping_loopback",
                ["ping", "-c", "1", "-W", str(wait), "127.0.0.1"],
                timeout=inc.ping_timeout_seconds + 1,
            ),
            "external": self.timed_command(
                "ping_external",
                ["ping", "-c", "1", "-W", str(wait), target],
                timeout=inc.ping_timeout_seconds + 1,
            ),
            "target": target,
        }
