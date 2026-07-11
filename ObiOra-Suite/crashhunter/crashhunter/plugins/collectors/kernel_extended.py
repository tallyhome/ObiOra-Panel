"""Enterprise kernel plugins: scheduler, interrupts, pressure, watchdog, journal, dmesg."""

from __future__ import annotations

from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector
from crashhunter.utils.proc import ProcReader


class SchedulerCollector(TimedCollector):
    name = "scheduler"
    priority = 60

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        stat = ProcReader.stat()
        load = ProcReader.loadavg()
        return {
            **self.collect_meta(),
            "run_queue": load.get("running", 0),
            "procs_running": stat.get("procs_running", 0),
            "procs_blocked": stat.get("procs_blocked", 0),
            "ctxt": stat.get("ctxt", 0),
            "schedstat": self.read_proc("/proc/schedstat", max_lines=20),
            "loadavg": load,
        }


class InterruptCollector(TimedCollector):
    name = "interrupt"
    priority = 65

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "interrupts": self.read_proc("/proc/interrupts", max_lines=30),
            "softirqs": self.read_proc("/proc/softirqs", max_lines=20),
        }


class SoftirqCollector(TimedCollector):
    name = "softirq"
    priority = 66

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "softirqs": ProcReader.softirqs(),
            "softirqs_raw": self.read_proc("/proc/softirqs"),
        }


class PressureCollector(TimedCollector):
    name = "pressure"
    priority = 70

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        psi: dict[str, str] = {}
        for resource in ("cpu", "memory", "io"):
            psi[resource] = self.read_proc(f"/proc/pressure/{resource}")
        return {**self.collect_meta(), "psi": psi, "parsed": ProcReader.pressure()}


class WatchdogCollector(TimedCollector):
    name = "watchdog"
    priority = 75

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "state": self.read_proc("/sys/class/watchdog/watchdog0/state"),
            "timeout": self.read_proc("/sys/class/watchdog/watchdog0/timeout"),
            "nowayout": self.read_proc("/sys/class/watchdog/watchdog0/nowayout"),
            "kernel_taint": ProcReader.kernel_taint(),
            "dmesg_watchdog": self.timed_command(
                "dmesg_watchdog",
                ["bash", "-c", "dmesg | grep -iE 'watchdog|lockup|stall' | tail -20"],
            ),
        }


class JournalCollector(TimedCollector):
    name = "journal"
    priority = 90

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        n = str(self.settings.journal_lines)
        return {
            **self.collect_meta(),
            "kernel": self.timed_command("journalctl_k", ["journalctl", "-k", "-n", n, "--no-pager", "-o", "short-iso"]),
            "warnings": self.timed_command("journalctl_warn", ["journalctl", "-p", "warning", "-n", n, "--no-pager", "-o", "short-iso"]),
            "failed_units": self.timed_command("systemctl_failed", ["systemctl", "--failed", "--no-pager"]),
            "jobs": self.timed_command("systemctl_jobs", ["systemctl", "list-jobs", "--no-pager"]),
        }


class DmesgCollector(TimedCollector):
    name = "dmesg"
    priority = 91

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "recent": self.timed_command("dmesg", ["dmesg", "-T", "--level=err,warn,crit,alert,emerg"]),
            "full_tail": self.timed_command(
                "dmesg_tail",
                ["dmesg", "-T"],
                max_output=80000,
            ),
        }
