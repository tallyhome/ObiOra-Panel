"""Disk I/O, latency, filesystem usage."""

from __future__ import annotations

import os
import shutil
from typing import Any

from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner


class DiskSampler(BaseSampler):
    name = "disk"

    def __init__(self, runner: SubprocessRunner) -> None:
        self.runner = runner
        self._prev_diskstats: list[dict[str, Any]] | None = None

    def sample(self) -> dict[str, Any]:
        diskstats = ProcReader.diskstats()
        latency = self._compute_latency(diskstats)
        iostat = self.runner.run_text(["iostat", "-xz", "1", "1"])
        lsblk = self.runner.run_text(["lsblk", "-J"])
        mounts = ProcReader.mounts()
        filesystems = self._filesystem_usage(mounts)
        smart = self.runner.run_text(
            ["bash", "-c", "for d in /dev/sd? /dev/nvme?n?; do [ -b \"$d\" ] && smartctl -H \"$d\" 2>/dev/null; done"]
        )
        return {
            "diskstats": diskstats,
            "latency": latency,
            "iostat": iostat,
            "lsblk": lsblk,
            "filesystems": filesystems,
            "mounts": mounts[:30],
            "smart": smart,
            "blkid": self.runner.run_text(["blkid"]),
            "dmsetup": self.runner.run_text(["dmsetup", "ls", "--tree"]),
        }

    def _compute_latency(self, current: list[dict[str, Any]]) -> list[dict[str, Any]]:
        result: list[dict[str, Any]] = []
        prev_map = {d["device"]: d for d in (self._prev_diskstats or [])}
        for dev in current:
            name = dev["device"]
            prev = prev_map.get(name, {})
            reads = dev["reads"] - prev.get("reads", 0)
            writes = dev["writes"] - prev.get("writes", 0)
            read_ms = dev["read_ms"] - prev.get("read_ms", 0)
            write_ms = dev["write_ms"] - prev.get("write_ms", 0)
            result.append(
                {
                    "device": name,
                    "in_flight": dev["in_flight"],
                    "queue_depth": dev["in_flight"],
                    "read_latency_ms": round(read_ms / reads, 2) if reads > 0 else 0.0,
                    "write_latency_ms": round(write_ms / writes, 2) if writes > 0 else 0.0,
                    "io_wait_ms": dev["io_ms"],
                    "weighted_io_ms": dev["weighted_io_ms"],
                }
            )
        self._prev_diskstats = current
        return result

    def _filesystem_usage(self, mounts: list[dict[str, str]]) -> list[dict[str, Any]]:
        usage_list: list[dict[str, Any]] = []
        seen: set[str] = set()
        for mount in mounts:
            path = mount["mount"]
            if path in seen or not os.path.isdir(path):
                continue
            seen.add(path)
            try:
                du = shutil.disk_usage(path)
                stat = os.statvfs(path)
                usage_list.append(
                    {
                        "mount": path,
                        "device": mount["device"],
                        "fstype": mount["fstype"],
                        "total_bytes": du.total,
                        "used_bytes": du.used,
                        "free_bytes": du.free,
                        "used_percent": round(du.used / du.total * 100, 2) if du.total else 0,
                        "inodes_total": stat.f_files,
                        "inodes_free": stat.f_ffree,
                    }
                )
            except OSError:
                continue
        return usage_list[:20]
