"""blk-mq sysfs collector — /sys/block/*/mq and queue."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector


class BlkMqCollector(TimedCollector):
    name = "blkmq"
    priority = 125

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        blocks: dict[str, Any] = {}
        sys_block = Path("/sys/block")
        if not sys_block.exists():
            return {**self.collect_meta(), "blocks": {}, "available": False}

        for block_path in sorted(sys_block.iterdir()):
            if not block_path.is_dir():
                continue
            name = block_path.name
            if name.startswith("loop") or name.startswith("ram"):
                continue
            block_data: dict[str, Any] = {"queue": {}, "mq": {}}

            queue_dir = block_path / "queue"
            if queue_dir.exists():
                for attr in (
                    "scheduler", "nr_requests", "max_sectors_kb", "rotational",
                    "read_ahead_kb", "add_random", "rq_affinity", "nomerges",
                    "hw_sector_size", "logical_block_size", "physical_block_size",
                ):
                    val = self._read_sysfs(queue_dir / attr)
                    if val is not None:
                        block_data["queue"][attr] = val

            mq_dir = block_path / "mq"
            if mq_dir.exists():
                for cpu_dir in sorted(mq_dir.iterdir()):
                    if cpu_dir.is_dir():
                        block_data["mq"][cpu_dir.name] = {
                            "tags": self._read_sysfs(cpu_dir / "tags"),
                            "dispatch_busy": self._read_sysfs(cpu_dir / "dispatch_busy"),
                        }
                block_data["mq"]["count"] = len(block_data["mq"]) - (1 if "count" in block_data["mq"] else 0)

            inflight = self._read_sysfs(block_path / "inflight")
            if inflight is not None:
                block_data["inflight"] = inflight

            stat = self._read_sysfs(block_path / "stat")
            if stat is not None:
                block_data["stat"] = stat

            blocks[name] = block_data

        return {**self.collect_meta(), "blocks": blocks, "block_count": len(blocks), "available": True}

    @staticmethod
    def _read_sysfs(path: Path) -> str | None:
        try:
            if path.exists():
                return path.read_text(encoding="utf-8", errors="replace").strip()[:2000]
        except OSError:
            pass
        return None
