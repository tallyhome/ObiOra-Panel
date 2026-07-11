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
from crashhunter.freeze.diagnostic_budget import DiagnosticBudget
from crashhunter.plugins.registry import CollectorRegistry
from crashhunter.storage.sequence_store import SequenceStore
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner
from crashhunter.utils.timestamp import now_us
from crashhunter.utils.virsh_capabilities import VirshCapabilities

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

    SYS_FILES: list[tuple[str, str]] = [
        ("edac", "/sys/devices/system/edac"),
    ]

    def __init__(
        self,
        settings: Settings,
        registry: CollectorRegistry,
        sequence_store: SequenceStore | None = None,
        shutdown_event: threading.Event | None = None,
    ) -> None:
        self.settings = settings
        self.registry = registry
        self.sequence_store = sequence_store
        self.shutdown_event = shutdown_event or threading.Event()
        self.runner = SubprocessRunner(default_timeout=settings.subprocess_timeout)
        self._deep_results: dict[str, Any] = {}
        self._deep_lock = threading.Lock()
        budget_cfg = settings.diagnostic_budget
        self.budget = DiagnosticBudget(
            psi_io_threshold=budget_cfg.psi_io_threshold,
            command_slow_ms=budget_cfg.command_slow_ms,
            heavy_cooldown_seconds=budget_cfg.heavy_cooldown_seconds,
        )
        diag_dir = settings.diagnostics_dir
        self._perf = PerfRecorder(diag_dir / "perf", settings.perf.duration_seconds, settings.perf.enabled)
        self._ftrace = FtraceRecorder(
            diag_dir / "ftrace",
            settings.ftrace,
            settings.state_dir,
            shutdown_event=self.shutdown_event,
        )
        self._qemu_gdb = QemuGdbCollector(settings.qemu_gdb.timeout_seconds, settings.qemu_gdb.max_processes)
        self._emergency_commands = self._build_emergency_commands()

    def _build_emergency_commands(self) -> list[tuple[str, list[str]]]:
        commands = list(self.EMERGENCY_COMMANDS)
        domstats = VirshCapabilities.domstats_command(self.runner)
        commands.insert(10, ("virsh_domstats", domstats))
        return commands

    def collect_quick_snapshot(self) -> dict[str, Any]:
        """Lightweight capture between SysRq steps."""
        pressure = ProcReader.pressure()
        snap = {
            "loadavg": ProcReader.loadavg(),
            "procs_blocked": ProcReader.stat().get("procs_blocked", 0),
            "pressure": {"parsed": pressure},
            "timestamp": now_us(),
        }
        if self.settings.diagnostic_budget.enabled:
            self.budget.evaluate({"pressure": {"parsed": pressure}})
        return snap

    def run_deep_diagnostics(self) -> None:
        """Background: perf, ftrace, QEMU gdb — one HEAVY tool at a time."""
        if self.shutdown_event.is_set():
            return
        if not self.budget.allow_heavy_diagnostics():
            logger.warning("Deep diagnostics skipped — MINIMAL SURVIVAL mode active")
            return

        results: dict[str, Any] = {}
        heavy_sequence = (
            ("perf", self.settings.perf.enabled, self._run_perf),
            ("ftrace", self.settings.ftrace.enabled, self._run_ftrace),
            ("qemu_gdb", self.settings.qemu_gdb.enabled, self._run_qemu_gdb),
        )
        for tool, enabled, runner in heavy_sequence:
            if self.shutdown_event.is_set():
                break
            if not enabled:
                continue
            if not self.budget.acquire_heavy(tool):
                continue
            timed_out = False
            try:
                results[tool] = runner()
                if isinstance(results[tool], dict) and results[tool].get("timed_out"):
                    timed_out = True
            except Exception as exc:
                logger.exception("Deep diagnostic %s failed: %s", tool, exc)
                results[tool] = {"recorded": False, "reason": str(exc)}
            finally:
                self.budget.release_heavy(tool, timed_out=timed_out)

        with self._deep_lock:
            self._deep_results = results
        logger.info("Deep incident diagnostics complete: %s", list(results.keys()))

    def _run_perf(self) -> dict[str, Any]:
        return self._perf.record()

    def _run_ftrace(self) -> dict[str, Any]:
        return self._ftrace.record_all()

    def _run_qemu_gdb(self) -> dict[str, Any]:
        return self._qemu_gdb.collect()

    def get_deep_results(self) -> dict[str, Any]:
        with self._deep_lock:
            return dict(self._deep_results)

    def collect_emergency_snapshot(self, triggers: list[str]) -> dict[str, Any]:
        """Full emergency snapshot combining plugins + raw commands + /proc dumps."""
        start = time.monotonic()
        pressure = ProcReader.pressure()
        if self.settings.diagnostic_budget.enabled:
            self.budget.evaluate({"pressure": {"parsed": pressure}})

        minimal = not self.budget.allow_heavy_diagnostics()
        if minimal:
            snapshot = self._collect_minimal(triggers, pressure)
        else:
            snapshot = self.registry.collect_all(emergency=True)
            snapshot["commands"] = self._run_commands(self._emergency_commands)
            snapshot["deep_diagnostics"] = self.get_deep_results()

        snapshot["emergency_timestamp"] = now_us()
        snapshot["triggers"] = triggers
        snapshot["proc_dumps"] = snapshot.get("proc_dumps") or self._read_proc_dumps()
        snapshot["sys_dumps"] = self._read_sys_dumps()
        snapshot["psi_parsed"] = pressure
        snapshot["dstate_full"] = snapshot.get("dstate", {})
        snapshot["diagnostic_budget"] = self.budget.status()
        snapshot["emergency_duration_ms"] = round((time.monotonic() - start) * 1000, 2)

        if self.sequence_store is not None:
            seq = self.sequence_store.next_id(
                "emergency_snapshot",
                {"minimal": minimal, "triggers": triggers},
            )
            snapshot["sequence_id"] = seq

        return snapshot

    def _collect_minimal(self, triggers: list[str], pressure: dict[str, Any]) -> dict[str, Any]:
        """MINIMAL SURVIVAL CAPTURE — /proc, /sys, no heavy tools."""
        allowed = self.budget.filter_emergency_commands(self._emergency_commands)
        return {
            "schema_version": 3,
            "collector": "crashhunter-enterprise",
            "mode": "emergency_minimal",
            "timestamp_us": now_us(),
            "triggers": triggers,
            "commands": self._run_commands(allowed),
            "pressure": {"parsed": pressure},
        }

    def _run_commands(self, commands: list[tuple[str, list[str]]]) -> dict[str, Any]:
        results: dict[str, Any] = {}
        for name, cmd in commands:
            cmd_start = time.monotonic()
            result = self.runner.run(cmd, timeout=self.settings.subprocess_timeout)
            latency_ms = round((time.monotonic() - cmd_start) * 1000, 2)
            results[name] = {
                "stdout": result.stdout[:50000],
                "stderr": result.stderr[:5000],
                "returncode": result.returncode,
                "timed_out": result.timed_out,
                "latency_ms": latency_ms,
                "pid": result.pid,
                "termination_method": result.termination_method,
            }
            if self.settings.diagnostic_budget.enabled:
                self.budget.evaluate({"pressure": {}}, last_command_ms=latency_ms)
                if result.timed_out:
                    self.budget.evaluate({"pressure": {}}, last_command_ms=self.settings.command_slow_ms + 1)
        return results

    def _read_proc_dumps(self) -> dict[str, str]:
        dumps: dict[str, str] = {}
        for name, path in self.PROC_FILES:
            dumps[name] = ProcReader.read_text(path)[:50000]
        for resource in ("cpu", "memory", "io"):
            dumps[f"pressure_{resource}"] = ProcReader.read_text(f"/proc/pressure/{resource}")
        return dumps

    def _read_sys_dumps(self) -> dict[str, str]:
        out: dict[str, str] = {}
        for name, path in self.SYS_FILES:
            root = __import__("pathlib").Path(path)
            if root.is_dir():
                lines: list[str] = []
                for f in sorted(root.rglob("*"))[:80]:
                    if f.is_file():
                        try:
                            lines.append(f"{f}: {f.read_text(encoding='utf-8', errors='replace')[:200]}")
                        except OSError:
                            continue
                out[name] = "\n".join(lines)[:20000]
        return out
