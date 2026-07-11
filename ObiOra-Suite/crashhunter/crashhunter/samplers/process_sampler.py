"""Top processes with RSS, VSZ, I/O, process tree."""

from __future__ import annotations

import os
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.proc import ProcReader


class ProcessSampler(BaseSampler):
    name = "processes"

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self._prev_cpu: dict[int, tuple[int, int]] = {}
        self._boot_ticks = self._read_boot_ticks()

    def _read_boot_ticks(self) -> int:
        with open("/proc/stat", encoding="utf-8") as fh:
            for line in fh:
                if line.startswith("btime"):
                    return int(line.split()[1]) * os.sysconf("SC_CLK_TCK")
        return 0

    def sample(self) -> dict[str, Any]:
        processes: list[dict[str, Any]] = []
        clk_tck = os.sysconf("SC_CLK_TCK")
        uptime = ProcReader.uptime()[0]

        for pid in ProcReader.list_pids():
            stat = ProcReader.pid_stat(pid)
            if not stat:
                continue
            status = ProcReader.pid_status(pid)
            io_data = ProcReader.pid_io(pid)
            utime = stat.get("utime", 0)
            stime = stat.get("stime", 0)
            prev = self._prev_cpu.get(pid, (utime, stime))
            cpu_delta = (utime + stime) - (prev[0] + prev[1])
            cpu_percent = round(cpu_delta / clk_tck / max(uptime, 1) * 100, 2) if uptime else 0.0
            self._prev_cpu[pid] = (utime, stime)

            rss_kb = stat.get("rss_kb", 0)
            vsz_kb = int(status.get("VmSize", "0 kB").split()[0]) if status.get("VmSize") else 0
            mem_total = ProcReader.meminfo().get("MemTotal", 1)
            mem_percent = round(rss_kb / mem_total * 100, 2) if mem_total else 0.0

            processes.append(
                {
                    "pid": pid,
                    "ppid": stat.get("ppid", 0),
                    "comm": stat.get("comm", ""),
                    "state": stat.get("state", ""),
                    "cpu_percent": cpu_percent,
                    "mem_percent": mem_percent,
                    "rss_kb": rss_kb,
                    "vsz_kb": vsz_kb,
                    "threads": stat.get("threads", 0),
                    "io_read_kb": io_data.get("read_bytes", 0) // 1024,
                    "io_write_kb": io_data.get("write_bytes", 0) // 1024,
                    "open_files": ProcReader.open_files_count(pid),
                }
            )

        processes.sort(key=lambda p: p["cpu_percent"], reverse=True)
        top = processes[: self.settings.top_process_count]
        tree = self._build_tree(top)
        return {
            "count": len(processes),
            "top": top,
            "process_tree": tree,
        }

    def _build_tree(self, processes: list[dict[str, Any]]) -> dict[str, list[int]]:
        tree: dict[str, list[int]] = {}
        for proc in processes:
            ppid = str(proc.get("ppid", 0))
            tree.setdefault(ppid, []).append(proc["pid"])
        return tree
