"""Emergency mode high-frequency data collector."""

from __future__ import annotations

import logging
import threading
import time
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.diagnostics.ftrace import FtraceRecorder
from crashhunter.diagnostics.perf_recorder import PerfRecorder
from crashhunter.diagnostics.qemu_gdb import QemuGdbCollector
from crashhunter.plugins.registry import CollectorRegistry
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner
from crashhunter.utils.timestamp import now_us

logger = logging.getLogger("crashhunter.emergency")


class EmergencyCollector:
    """Collect everything possible during incident mode at 500ms intervals."""

    EMERGENCY_COMMANDS: list[tuple[str, list[str]]] = [
        ("ps_auxww", ["ps", "auxww"]),
        ("top_batch", ["top", "-b", "-n", "1"]),
        ("vmstat", ["vmstat", "1", "1"]),
        ("iostat", ["iostat", "-xz", "1", "1"]),
        ("pidstat", ["pidstat", "-dur", "1", "1"]),
        ("iotop_batch", ["iotop", "-b", "-n", "1", "-qq"]),
        ("lsof_summary", ["lsof", "-nP"]),
        ("ss_antup", ["ss", "-antup"]),
        ("netstat", ["netstat", "-antup"]),
        ("virsh_list", ["virsh", "list", "--all"]),
        ("virsh_domstats", ["virsh", "domstats", "--state", "--cpu", "--balloon", "--block", "--interface"]),
        ("virsh_dominfo", ["bash", "-c", "for vm in $(virsh list --all --name 2>/dev/null); do virsh dominfo \"$vm\"; done"]),
        ("virsh_cpu_stats", ["bash", "-c", "for vm in $(virsh list --name 2>/dev/null); do echo \"=== $vm ===\"; virsh cpu-stats \"$vm\" 2>/dev/null; done"]),
        ("virsh_dommemstat", ["bash", "-c", "for vm in $(virsh list --name 2>/dev/null); do echo \"=== $vm ===\"; virsh dommemstat \"$vm\" 2>/dev/null; done"]),
        ("journalctl", ["journalctl", "-n", "200", "--no-pager", "-o", "short-iso"]),
        ("dmesg", ["dmesg", "-T"]),
        ("systemctl_failed", ["systemctl", "--failed", "--no-pager"]),
        ("systemctl_jobs", ["systemctl", "list-jobs", "--no-pager"]),
        ("systemctl_status", ["systemctl", "status", "--no-pager"]),
    ]

    PROC_FILES: list[tuple[str, str]] = [
        ("interrupts", "/proc/interrupts"),
        ("stat", "/proc/stat"),
        ("loadavg", "/proc/loadavg"),
        ("meminfo", "/proc/meminfo"),
        ("vmstat", "/proc/vmstat"),
        ("slabinfo", "/proc/slabinfo"),
        ("softirqs", "/proc/softirqs"),
    ]

    def __init__(self, settings: Settings, registry: CollectorRegistry) -> None:
        self.settings = settings
        self.registry = registry
        self.runner = SubprocessRunner(default_timeout=settings.subprocess_timeout)
        self._deep_results: dict[str, Any] = {}
        self._deep_lock = threading.Lock()
        diag_dir = settings.diagnostics_dir
        self._perf = PerfRecorder(diag_dir / "perf", settings.perf.duration_seconds, settings.perf.enabled)
        self._ftrace = FtraceRecorder(diag_dir / "ftrace", settings.ftrace.duration_seconds, settings.ftrace.enabled)
        self._qemu_gdb = QemuGdbCollector(settings.qemu_gdb.timeout_seconds, settings.qemu_gdb.max_processes)

    def collect_quick_snapshot(self) -> dict[str, Any]:
        """Lightweight capture between SysRq steps."""
        return {
            "loadavg": ProcReader.loadavg(),
            "procs_blocked": ProcReader.stat().get("procs_blocked", 0),
            "pressure": ProcReader.pressure(),
            "timestamp": now_us(),
        }

    def run_deep_diagnostics(self) -> None:
        """Background: perf, ftrace, QEMU gdb backtraces."""
        results: dict[str, Any] = {}
        if self.settings.perf.enabled:
            results["perf"] = self._perf.record()
        if self.settings.ftrace.enabled:
            results["ftrace"] = self._ftrace.record_all()
        if self.settings.qemu_gdb.enabled:
            results["qemu_gdb"] = self._qemu_gdb.collect()
        with self._deep_lock:
            self._deep_results = results
        logger.info("Deep incident diagnostics complete: %s", list(results.keys()))

    def get_deep_results(self) -> dict[str, Any]:
        with self._deep_lock:
            return dict(self._deep_results)

    def collect_emergency_snapshot(self, triggers: list[str]) -> dict[str, Any]:
        """Full emergency snapshot combining plugins + raw commands + /proc dumps."""
        start = time.monotonic()
        snapshot = self.registry.collect_all(emergency=True)
        snapshot["emergency_timestamp"] = now_us()
        snapshot["triggers"] = triggers
        snapshot["commands"] = {}
        for name, cmd in self.EMERGENCY_COMMANDS:
            result = self.runner.run(cmd, timeout=self.settings.subprocess_timeout)
            snapshot["commands"][name] = {
                "stdout": result.stdout[:50000],
                "stderr": result.stderr[:5000],
                "returncode": result.returncode,
                "timed_out": result.timed_out,
            }
        snapshot["proc_dumps"] = {}
        for name, path in self.PROC_FILES:
            snapshot["proc_dumps"][name] = ProcReader.read_text(path)[:50000]
        for resource in ("cpu", "memory", "io"):
            snapshot["proc_dumps"][f"pressure_{resource}"] = ProcReader.read_text(
                f"/proc/pressure/{resource}"
            )
        snapshot["psi_parsed"] = ProcReader.pressure()
        snapshot["dstate_full"] = snapshot.get("dstate", {})
        snapshot["deep_diagnostics"] = self.get_deep_results()
        snapshot["emergency_duration_ms"] = round((time.monotonic() - start) * 1000, 2)
        return snapshot
