"""Enterprise memory plugins: swap, OOM, NUMA, HugePages."""

from __future__ import annotations

from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector
from crashhunter.utils.proc import ProcReader


class SwapCollector(TimedCollector):
    name = "swap"
    priority = 110

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        mem = ProcReader.meminfo()
        return {
            **self.collect_meta(),
            "swap_total_kb": mem.get("SwapTotal", 0),
            "swap_free_kb": mem.get("SwapFree", 0),
            "swap_cached_kb": mem.get("SwapCached", 0),
            "vmstat": {k: v for k, v in ProcReader.vmstat().items() if "swap" in k or "pg" in k},
            "free": self.timed_command("free", ["free", "-m"]),
        }


class OomCollector(TimedCollector):
    name = "oom"
    priority = 115

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "oom_score": self.read_proc("/proc/sys/vm/overcommit_memory"),
            "panic_on_oom": self.read_proc("/proc/sys/vm/panic_on_oom"),
            "oom_kill": self.timed_command("dmesg_oom", ["bash", "-c", "dmesg | grep -iE 'oom|out of memory' | tail -20"]),
            "journal_oom": self.timed_command("journal_oom", ["journalctl", "-k", "-g", "oom", "-n", "20", "--no-pager"]),
        }


class NumaCollector(TimedCollector):
    name = "numa"
    priority = 120

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        return {
            **self.collect_meta(),
            "numastat": self.timed_command("numastat", ["numastat"]),
            "meminfo_numa": {k: v for k, v in ProcReader.meminfo().items() if "Numa" in k or "Huge" in k},
        }


class HugePagesCollector(TimedCollector):
    name = "hugepages"
    priority = 125

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        mem = ProcReader.meminfo()
        return {
            **self.collect_meta(),
            "hugepages_total": mem.get("HugePages_Total", 0),
            "hugepages_free": mem.get("HugePages_Free", 0),
            "hugetlb_kb": mem.get("Hugetlb", 0),
            "thp_enabled": self.read_proc("/sys/kernel/mm/transparent_hugepage/enabled"),
            "thp_defrag": self.read_proc("/sys/kernel/mm/transparent_hugepage/defrag"),
            "buddyinfo": self.read_proc("/proc/buddyinfo"),
            "pagetypeinfo": self.read_proc("/proc/pagetypeinfo", max_lines=30),
        }
