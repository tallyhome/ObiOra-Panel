"""Kernel messages: dmesg diff, journal diff."""

from __future__ import annotations

import hashlib
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.subprocess_runner import SubprocessRunner


class KernelSampler(BaseSampler):
    name = "kernel"

    def __init__(self, settings: Settings, runner: SubprocessRunner) -> None:
        self.settings = settings
        self.runner = runner
        self._prev_dmesg_hash = ""
        self._prev_journal_hash = ""
        self._prev_dmesg_lines: list[str] = []
        self._prev_journal_lines: list[str] = []

    def sample(self) -> dict[str, Any]:
        dmesg = self.runner.run_text(["dmesg", "-T", "--level=err,warn,info,crit,alert,emerg"])
        if not dmesg:
            dmesg = self.runner.run_text(["dmesg"])
        journal = self.runner.run_text(
            ["journalctl", "-k", "-n", str(self.settings.journal_lines), "--no-pager", "-o", "short-iso"]
        )
        systemd = self.runner.run_text(
            ["journalctl", "-p", "warning", "-n", str(self.settings.journal_lines), "--no-pager", "-o", "short-iso"]
        )

        dmesg_lines = dmesg.splitlines()[-self.settings.dmesg_lines :]
        journal_lines = journal.splitlines()
        dmesg_hash = hashlib.sha256("\n".join(dmesg_lines).encode()).hexdigest()
        journal_hash = hashlib.sha256("\n".join(journal_lines).encode()).hexdigest()

        dmesg_diff = self._diff_lines(self._prev_dmesg_lines, dmesg_lines)
        journal_diff = self._diff_lines(self._prev_journal_lines, journal_lines)

        self._prev_dmesg_lines = dmesg_lines
        self._prev_journal_lines = journal_lines
        self._prev_dmesg_hash = dmesg_hash
        self._prev_journal_hash = journal_hash

        watchdog = self.runner.run_text(["cat", "/sys/class/watchdog/watchdog0/state"])
        sysctl = self.runner.run_text(["sysctl", "-a"], timeout=3.0)

        return {
            "dmesg_tail": dmesg_lines[-30:],
            "dmesg_diff": dmesg_diff,
            "journal_tail": journal_lines[-30:],
            "journal_diff": journal_diff,
            "systemd_warnings": systemd.splitlines()[-20:],
            "watchdog_state": watchdog,
            "sysctl_sample": sysctl.splitlines()[:50],
            "mce": self.runner.run_text(["bash", "-c", "cat /sys/devices/system/machinecheck/machinecheck*/trigger 2>/dev/null"]),
            "ras": self.runner.run_text(["bash", "-c", "dmesg | grep -iE 'ras|mce|edac' | tail -10"]),
        }

    @staticmethod
    def _diff_lines(previous: list[str], current: list[str]) -> list[str]:
        if not previous:
            return current[-20:]
        prev_set = set(previous)
        return [line for line in current if line not in prev_set][-20:]
