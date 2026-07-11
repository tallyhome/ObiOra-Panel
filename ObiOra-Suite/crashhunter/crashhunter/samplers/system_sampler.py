"""System-level metrics: uptime, load, kernel info."""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from crashhunter.samplers.base import BaseSampler
from crashhunter.utils.proc import ProcReader


class SystemSampler(BaseSampler):
    name = "system"

    def sample(self) -> dict[str, Any]:
        uptime, idle = ProcReader.uptime()
        return {
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "boot_id": ProcReader.boot_id(),
            "uptime_seconds": uptime,
            "idle_seconds": idle,
            "loadavg": ProcReader.loadavg(),
            "kernel_version": ProcReader.kernel_version(),
            "kernel_taint": ProcReader.kernel_taint(),
            "stat": {
                "ctxt": ProcReader.stat().get("ctxt", 0),
                "processes": ProcReader.stat().get("processes", 0),
                "procs_running": ProcReader.stat().get("procs_running", 0),
                "procs_blocked": ProcReader.stat().get("procs_blocked", 0),
            },
            "interrupts": ProcReader.interrupts(),
            "softirqs": ProcReader.softirqs(),
            "pressure": ProcReader.pressure(),
            "modules_count": len(ProcReader.modules()),
        }
