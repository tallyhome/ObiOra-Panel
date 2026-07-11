"""Silent freeze detection engine."""

from __future__ import annotations

import logging
import re
from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.report.event_timeline import EventTimeline
from crashhunter.utils.proc import ProcReader

logger = logging.getLogger("crashhunter.freeze.detector")


@dataclass
class FreezeSignal:
    trigger: str
    severity: str
    detail: str
    confidence: float = 0.8


@dataclass
class DetectorState:
    prev_ctxt: int | None = None
    prev_procs_running: int | None = None
    prev_clock: datetime | None = None
    stall_cycles: int = 0
    prev_qemu_cpu: dict[int, int] = field(default_factory=dict)
    command_timeouts: int = 0


class SilentFreezeDetector:
    """
    Continuously monitors system health signals and triggers incident mode
    when a silent freeze is suspected — even without kernel panic or reboot.
    """

    KERNEL_STALL_PATTERNS = [
        (r"soft lockup", "soft_lockup"),
        (r"hard lockup", "hard_lockup"),
        (r"hung task", "hung_task"),
        (r"rcu stall", "rcu_stall"),
        (r"watchdog", "watchdog_warning"),
    ]

    def __init__(self, settings: Settings, timeline: EventTimeline) -> None:
        self.settings = settings
        self.timeline = timeline
        self._state = DetectorState()

    def evaluate(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        """Evaluate snapshot and return list of freeze signals (empty = healthy)."""
        signals: list[FreezeSignal] = []
        inc = self.settings.incident

        signals.extend(self._check_responsiveness(snapshot))
        signals.extend(self._check_scheduler(snapshot))
        signals.extend(self._check_iowait(snapshot))
        signals.extend(self._check_dstate(snapshot))
        signals.extend(self._check_kernel_messages(snapshot))
        signals.extend(self._check_disk_latency(snapshot))
        signals.extend(self._check_clock_drift(snapshot))
        signals.extend(self._check_qemu_stall(snapshot))

        if self._state.command_timeouts >= inc.command_timeout_count_threshold:
            signals.append(FreezeSignal(
                trigger="multiple_command_timeouts",
                severity="critical",
                detail=f"{self._state.command_timeouts} command timeouts accumulated",
                confidence=0.92,
            ))

        for sig in signals:
            self.timeline.record(sig.trigger, sig.detail, severity=sig.severity)

        return signals

    def should_trigger_incident(self, signals: list[FreezeSignal]) -> bool:
        if not self.settings.incident.enabled:
            return False
        return len(signals) > 0

    def _check_responsiveness(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        signals: list[FreezeSignal] = []
        resp = snapshot.get("responsiveness", {})
        inc = self.settings.incident

        ssh = resp.get("ssh_localhost", {})
        if ssh.get("timed_out"):
            self._state.command_timeouts += 1
            signals.append(FreezeSignal("ssh_timeout", "critical", "SSH localhost timeout", 0.95))
        elif (
            ssh.get("responsive") is False
            and ssh.get("classification") not in ("local_config_error", "auth_refused")
        ):
            self._state.command_timeouts += 1
            signals.append(FreezeSignal(
                "ssh_unresponsive",
                "high",
                f"SSH localhost unresponsive ({ssh.get('classification', 'unknown')})",
                0.90,
            ))

        for key, label in (("ping_loopback", "loopback"), ("ping_external", "external")):
            ping = resp.get(key, {})
            if ping.get("timed_out"):
                self._state.command_timeouts += 1
                signals.append(FreezeSignal(
                    "ping_timeout", "critical", f"Ping {label} failed ({ping.get('target', '')})", 0.93,
                ))

        virsh = resp.get("virsh_list", {})
        if virsh.get("timed_out"):
            self._state.command_timeouts += 1
            signals.append(FreezeSignal(
                "virsh_timeout", "high",
                f"virsh list timed out after {virsh.get('latency_ms', 0)}ms",
                0.90,
            ))
        elif virsh.get("slow"):
            signals.append(FreezeSignal(
                "virsh_slow", "medium",
                f"virsh list slow {virsh.get('latency_ms', 0)}ms (threshold {inc.virsh_timeout_seconds}s)",
                0.75,
            ))

        virt = resp.get("virtualizor", {})
        if virt.get("timed_out"):
            self._state.command_timeouts += 1
            signals.append(FreezeSignal("virtualizor_timeout", "high", "Virtualizor API timeout", 0.85))

        libvirt = resp.get("libvirt", {})
        if libvirt.get("timed_out") or not libvirt.get("connect_ok", True):
            signals.append(FreezeSignal("libvirt_timeout", "high", "libvirt connection failed", 0.87))

        return signals

    def _check_scheduler(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        signals: list[FreezeSignal] = []
        stat = ProcReader.stat()
        ctxt = stat.get("ctxt", 0)
        running = stat.get("procs_running", 0)

        if self._state.prev_ctxt is not None:
            if ctxt == self._state.prev_ctxt:
                self._state.stall_cycles += 1
                if self._state.stall_cycles == 1:
                    self.timeline.record("scheduler_anomaly", "No context switches", severity="high")
                if self._state.stall_cycles >= self.settings.incident.scheduler_stall_cycles:
                    signals.append(FreezeSignal(
                        "scheduler_stall", "critical",
                        f"No context switch progress for {self._state.stall_cycles} cycles", 0.90,
                    ))
            else:
                self._state.stall_cycles = 0

        if (
            self._state.prev_procs_running is not None
            and running == self._state.prev_procs_running
            and snapshot.get("cpu", {}).get("total_percent", 0) > 50
        ):
            signals.append(FreezeSignal(
                "no_scheduler_progress", "high",
                "Scheduler not making progress under load", 0.82,
            ))

        self._state.prev_ctxt = ctxt
        self._state.prev_procs_running = running
        return signals

    def _check_iowait(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        signals: list[FreezeSignal] = []
        cpu_data = snapshot.get("cpu", {})
        iowait = cpu_data.get("iowait_percent", self._calc_iowait())
        if iowait > self.settings.incident.iowait_threshold_percent:
            signals.append(FreezeSignal(
                "iowait_high", "high",
                f"IOWait at {iowait:.1f}% (threshold {self.settings.incident.iowait_threshold_percent}%)",
                min(0.95, iowait / 100),
            ))
            self.timeline.record("iowait_increased", f"IOWait {iowait:.1f}%", severity="high")
        return signals

    def _calc_iowait(self) -> float:
        lines = ProcReader.read_lines("/proc/stat")
        if not lines:
            return 0.0
        parts = lines[0].split()
        if len(parts) < 6:
            return 0.0
        try:
            values = [int(p) for p in parts[1:]]
            total = sum(values)
            iowait = values[3] if len(values) > 3 else 0
            return round(iowait / max(total, 1) * 100, 2)
        except (ValueError, IndexError):
            return 0.0

    def _check_dstate(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        dstate = snapshot.get("dstate", {})
        count = dstate.get("count", 0)
        threshold = self.settings.incident.blocked_d_state_threshold
        if count >= threshold:
            procs = dstate.get("processes", [])
            names = ", ".join(p.get("comm", "?") for p in procs[:5])
            signals = [FreezeSignal(
                "d_state_processes", "critical",
                f"{count} D-state processes: {names}", 0.91,
            )]
            for proc in procs:
                wchan = proc.get("wchan", "")
                if "qemu" in proc.get("comm", "").lower() or "kvm" in wchan.lower():
                    self.timeline.record(
                        "qemu_storage_wait",
                        f"QEMU {proc.get('comm')} PID {proc.get('pid')} wchan={wchan}",
                        severity="critical",
                    )
            return signals
        return []

    def _check_kernel_messages(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        signals: list[FreezeSignal] = []
        kernel = snapshot.get("kernel", {})
        for line in kernel.get("dmesg_diff", []) + kernel.get("journal_diff", []):
            lower = line.lower()
            for pattern, trigger in self.KERNEL_STALL_PATTERNS:
                if re.search(pattern, lower):
                    signals.append(FreezeSignal(trigger, "critical", line[:300], 0.94))
                    self.timeline.record(trigger, line[:200], severity="critical")
        return signals

    def _check_disk_latency(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        signals: list[FreezeSignal] = []
        disk = snapshot.get("disk", {})
        threshold = self.settings.thresholds.disk_latency_ms
        for lat in disk.get("latency", []):
            read_ms = lat.get("read_latency_ms", 0)
            write_ms = lat.get("write_latency_ms", 0)
            if read_ms > threshold or write_ms > threshold:
                signals.append(FreezeSignal(
                    "disk_latency_spike", "high",
                    f"Device {lat.get('device')}: read={read_ms}ms write={write_ms}ms",
                    0.80,
                ))
        fs_freeze = any(
            fs.get("used_percent", 0) >= 99.9
            for fs in disk.get("filesystems", [])
        )
        if fs_freeze and disk.get("latency"):
            signals.append(FreezeSignal("filesystem_freeze", "high", "Filesystem near full with I/O latency", 0.75))
        return signals

    def _check_clock_drift(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        signals: list[FreezeSignal] = []
        now = datetime.now(timezone.utc)
        if self._state.prev_clock is not None:
            expected_gap = self.settings.interval_seconds
            actual_gap = (now - self._state.prev_clock).total_seconds()
            drift = abs(actual_gap - expected_gap)
            if drift > self.settings.incident.clock_drift_threshold_seconds:
                signals.append(FreezeSignal(
                    "clock_drift", "medium",
                    f"System clock drift {drift:.1f}s (expected ~{expected_gap}s gap)", 0.70,
                ))
        self._state.prev_clock = now
        return signals

    def _check_qemu_stall(self, snapshot: dict[str, Any]) -> list[FreezeSignal]:
        signals: list[FreezeSignal] = []
        for proc in snapshot.get("processes", {}).get("top", []):
            comm = proc.get("comm", "").lower()
            if "qemu" not in comm:
                continue
            pid = proc.get("pid", 0)
            runtime = proc.get("rss_kb", 0)
            prev = self._state.prev_qemu_cpu.get(pid)
            if prev is not None and runtime == prev:
                signals.append(FreezeSignal(
                    "qemu_not_progressing", "high",
                    f"QEMU PID {pid} ({proc.get('comm')}) not progressing", 0.83,
                ))
            self._state.prev_qemu_cpu[pid] = runtime
        return signals

    def reset_timeout_counter(self) -> None:
        self._state.command_timeouts = 0
