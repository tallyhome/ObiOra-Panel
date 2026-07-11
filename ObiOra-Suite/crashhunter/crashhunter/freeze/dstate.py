"""D-State process investigation collector."""

from __future__ import annotations

import os
import re
from typing import Any

from crashhunter.plugins.base import BaseCollector
from crashhunter.utils.proc import ProcReader


class DStateInvestigator(BaseCollector):
    """Investigate every process in uninterruptible sleep (D state)."""

    name = "dstate"
    priority = 200

    def collect(self) -> dict[str, Any]:
        d_processes: list[dict[str, Any]] = []
        for pid in ProcReader.list_pids():
            stat = ProcReader.pid_stat(pid)
            if stat.get("state") != "D":
                continue
            d_processes.append(self._investigate(pid, stat))
        return {
            "count": len(d_processes),
            "processes": d_processes,
        }

    def _investigate(self, pid: int, stat: dict[str, Any]) -> dict[str, Any]:
        status = ProcReader.pid_status(pid)
        io_data = ProcReader.pid_io(pid)
        return {
            "pid": pid,
            "ppid": stat.get("ppid", 0),
            "comm": stat.get("comm", ""),
            "state": "D",
            "runtime_ticks": stat.get("utime", 0) + stat.get("stime", 0),
            "cpu_percent": 0.0,
            "rss_kb": stat.get("rss_kb", 0),
            "vsz_kb": _parse_kb(status.get("VmSize", "0 kB")),
            "threads": stat.get("threads", 0),
            "open_files": ProcReader.open_files_count(pid),
            "syscall": ProcReader.read_text(f"/proc/{pid}/syscall", ""),
            "wchan": ProcReader.read_text(f"/proc/{pid}/wchan", ""),
            "stack": ProcReader.read_lines(f"/proc/{pid}/stack")[:30],
            "status": status,
            "io": io_data,
            "sched": ProcReader.read_key_values(f"/proc/{pid}/sched"),
            "cgroup": ProcReader.read_text(f"/proc/{pid}/cgroup", ""),
            "mounts": ProcReader.read_text(f"/proc/{pid}/mountinfo", "")[:5000],
            "ns_net": _read_ns(pid, "net"),
            "ns_mnt": _read_ns(pid, "mnt"),
            "ns_pid": _read_ns(pid, "pid"),
        }


def _read_ns(pid: int, ns: str) -> str:
    path = f"/proc/{pid}/ns/{ns}"
    try:
        return os.readlink(path)
    except OSError:
        return ""


def _parse_kb(value: str) -> int:
    match = re.match(r"(\d+)", value)
    return int(match.group(1)) if match else 0
