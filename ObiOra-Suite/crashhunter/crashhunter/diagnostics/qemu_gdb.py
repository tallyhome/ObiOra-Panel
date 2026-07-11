"""QEMU backtrace collection via gdb."""

from __future__ import annotations

import logging
import re
import shutil
from typing import Any

from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.qemu_gdb")


class QemuGdbCollector:
    """Attach gdb to QEMU processes and collect thread backtraces."""

    def __init__(self, timeout_seconds: float = 15.0, max_processes: int = 3) -> None:
        self.timeout_seconds = timeout_seconds
        self.max_processes = max_processes
        self.runner = SubprocessRunner(default_timeout=timeout_seconds)

    def is_available(self) -> bool:
        return shutil.which("gdb") is not None

    def collect(self) -> dict[str, Any]:
        if not self.is_available():
            return {"available": False, "reason": "gdb_not_installed"}

        pids = self._find_qemu_pids()
        results: list[dict[str, Any]] = []
        for pid in pids[: self.max_processes]:
            bt = self._backtrace_pid(pid)
            results.append({"pid": pid, **bt})

        return {"available": True, "processes": results, "count": len(results)}

    def _find_qemu_pids(self) -> list[int]:
        result = self.runner.run(
            ["bash", "-c", "pgrep -f 'qemu-kvm|qemu-system' | head -20"],
            timeout=3.0,
        )
        pids: list[int] = []
        for line in result.stdout.splitlines():
            try:
                pids.append(int(line.strip()))
            except ValueError:
                continue
        return pids

    def _backtrace_pid(self, pid: int) -> dict[str, Any]:
        script = (
            f"set pagination off\n"
            f"attach {pid}\n"
            f"thread apply all bt\n"
            f"detach\n"
            f"quit\n"
        )
        result = self.runner.run(
            ["gdb", "-batch", "-ex", f"attach {pid}", "-ex", "thread apply all bt", "-ex", "detach", "-ex", "quit"],
            timeout=self.timeout_seconds,
        )
        output = result.stdout + result.stderr
        threads = len(re.findall(r"Thread \d+", output))
        return {
            "backtrace": output[:100000],
            "thread_count": threads,
            "returncode": result.returncode,
            "timed_out": result.timed_out,
        }
