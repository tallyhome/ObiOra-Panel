"""Memory, swap, slab, vmstat, HugePages."""

from __future__ import annotations

from typing import Any

from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.proc import ProcReader
from crashhunter.utils.subprocess_runner import SubprocessRunner


class MemorySampler(BaseSampler):
    name = "memory"

    def __init__(self, runner: SubprocessRunner) -> None:
        self.runner = runner

    def sample(self) -> dict[str, Any]:
        mem = ProcReader.meminfo()
        vmstat = ProcReader.vmstat()
        slab_text = self.runner.run_text(["bash", "-c", "cat /proc/slabinfo 2>/dev/null | head -20"])
        return {
            "meminfo": mem,
            "vmstat": vmstat,
            "swap_total_kb": mem.get("SwapTotal", 0),
            "swap_free_kb": mem.get("SwapFree", 0),
            "mem_available_kb": mem.get("MemAvailable", mem.get("MemFree", 0)),
            "mem_total_kb": mem.get("MemTotal", 0),
            "dirty_kb": mem.get("Dirty", 0),
            "writeback_kb": mem.get("Writeback", 0),
            "hugepages_total": mem.get("HugePages_Total", 0),
            "hugepages_free": mem.get("HugePages_Free", 0),
            "slab_top": slab_text.splitlines()[:10],
        }
