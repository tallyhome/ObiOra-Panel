"""Responsiveness probes — SSH, ping, virsh, Virtualizor, libvirt."""

from __future__ import annotations

import time
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.plugins.base import BaseCollector
from crashhunter.utils.subprocess_runner import SubprocessRunner


class ResponsivenessProbes(BaseCollector):
    """Probe external service responsiveness for silent freeze detection."""

    name = "responsiveness"
    priority = 50

    def __init__(self, settings: Settings, runner: SubprocessRunner) -> None:
        self.settings = settings
        self.runner = runner

    def collect(self) -> dict[str, Any]:
        inc = self.settings.incident
        return {
            "ssh_localhost": self._probe_ssh(inc.ssh_timeout_seconds),
            "ping_loopback": self._probe_ping("127.0.0.1", inc.ping_timeout_seconds),
            "ping_external": self._probe_ping(inc.external_ping_target, inc.ping_timeout_seconds),
            "virsh_list": self._probe_virsh(inc.virsh_timeout_seconds),
            "libvirt": self._probe_libvirt(),
            "virtualizor": self._probe_virtualizor(inc.virtualizor_timeout_seconds),
            "qemu_progress": self._probe_qemu_progress(),
            "command_latency_ms": self._probe_shell_latency(),
        }

    def _probe_ssh(self, timeout: float) -> dict[str, Any]:
        start = time.monotonic()
        result = self.runner.run(
            ["ssh", "-o", "ConnectTimeout=2", "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=no",
             "localhost", "true"],
            timeout=timeout,
        )
        elapsed = round((time.monotonic() - start) * 1000, 2)
        return {
            "ok": result.ok,
            "timed_out": result.timed_out,
            "latency_ms": elapsed,
            "returncode": result.returncode,
        }

    def _probe_ping(self, target: str, timeout: float) -> dict[str, Any]:
        start = time.monotonic()
        wait = max(1, int(timeout))
        result = self.runner.run(
            ["ping", "-c", "1", "-W", str(wait), target],
            timeout=timeout + 1,
        )
        elapsed = round((time.monotonic() - start) * 1000, 2)
        return {
            "target": target,
            "ok": result.ok and not result.timed_out,
            "timed_out": result.timed_out,
            "latency_ms": elapsed,
        }

    def _probe_virsh(self, timeout: float) -> dict[str, Any]:
        start = time.monotonic()
        result = self.runner.run(["virsh", "list"], timeout=timeout)
        elapsed = round((time.monotonic() - start) * 1000, 2)
        slow = elapsed > timeout * 1000
        return {
            "ok": result.ok and not result.timed_out,
            "timed_out": result.timed_out or slow,
            "latency_ms": elapsed,
            "slow": slow,
            "returncode": result.returncode,
        }

    def _probe_libvirt(self) -> dict[str, Any]:
        start = time.monotonic()
        active = self.runner.run_text(["systemctl", "is-active", "libvirtd"])
        connect = self.runner.run(["virsh", "connect", "qemu:///system"], timeout=3.0)
        elapsed = round((time.monotonic() - start) * 1000, 2)
        return {
            "service_active": active.strip() == "active",
            "connect_ok": connect.ok,
            "timed_out": connect.timed_out,
            "latency_ms": elapsed,
        }

    def _probe_virtualizor(self, timeout: float) -> dict[str, Any]:
        start = time.monotonic()
        result = self.runner.run(
            ["bash", "-c",
             "/usr/local/emps/bin/php /usr/local/virtualizor/scripts/virt_check.php 2>/dev/null || virt_check 2>/dev/null"],
            timeout=timeout,
        )
        elapsed = round((time.monotonic() - start) * 1000, 2)
        return {
            "ok": result.ok and not result.timed_out,
            "timed_out": result.timed_out,
            "latency_ms": elapsed,
            "output_lines": len(result.stdout.splitlines()),
        }

    def _probe_qemu_progress(self) -> dict[str, Any]:
        """Detect QEMU processes whose CPU time is not advancing."""
        result = self.runner.run_text(
            ["bash", "-c", "ps -eo pid,comm,stat,etimes | grep -E '[q]emu' | head -20"]
        )
        return {
            "qemu_processes": result.splitlines(),
            "count": len([line for line in result.splitlines() if line.strip()]),
        }

    def _probe_shell_latency(self) -> float:
        start = time.monotonic()
        self.runner.run(["true"], timeout=2.0)
        return round((time.monotonic() - start) * 1000, 2)
