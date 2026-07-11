"""Optional eBPF collector — graceful fallback if tools unavailable."""

from __future__ import annotations

import shutil
from typing import Any

from crashhunter.plugins.collectors.timed_base import TimedCollector


class EbpfCollector(TimedCollector):
    """
    Optional eBPF-based kernel introspection.
    Uses bpftrace if available; otherwise reports unavailable without failing.
    """

    name = "ebpf"
    priority = 200

    def collect(self) -> dict[str, Any]:
        self.reset_alerts()
        if not self.settings.ebpf.enabled:
            return {**self.collect_meta(), "available": False, "reason": "disabled_in_config"}

        bpftrace = shutil.which("bpftrace")
        if not bpftrace:
            return {**self.collect_meta(), "available": False, "reason": "bpftrace_not_installed"}

        # Lightweight one-liners — low overhead, 1s timeout each
        return {
            **self.collect_meta(),
            "available": True,
            "tool": bpftrace,
            "runqlen": self.timed_command(
                "bpf_runqlen",
                ["bpftrace", "-e", "interval:s:1 { printf(\"%d\\n\", count()); exit(); }"],
                timeout=self.settings.ebpf.timeout_seconds,
                max_output=1000,
            ),
            "biolatency_hint": self.timed_command(
                "bpf_io",
                ["bash", "-c", "bpftrace -e 'tracepoint:block:block_rq_issue { @bytes = hist(args->bytes); } interval:s:1 { exit(); }' 2>/dev/null || echo 'skipped'"],
                timeout=self.settings.ebpf.timeout_seconds,
            ),
        }
