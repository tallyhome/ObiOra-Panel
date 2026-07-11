"""ftrace recorder — function_graph, irqsoff, preemptoff, wakeup."""

from __future__ import annotations

import logging
import shutil
from pathlib import Path
from typing import Any

from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.ftrace")

TRACING = Path("/sys/kernel/tracing")
TRACING_LEGACY = Path("/sys/kernel/debug/tracing")


class FtraceRecorder:
    """Enable ftrace tracers during incident mode."""

    TRACERS = ("function_graph", "irqsoff", "preemptoff", "wakeup")

    def __init__(self, output_dir: Path, duration_seconds: float = 10.0, enabled: bool = True) -> None:
        self.output_dir = output_dir
        self.duration_seconds = duration_seconds
        self.enabled = enabled
        self.runner = SubprocessRunner(default_timeout=duration_seconds + 5)

    @property
    def tracing_root(self) -> Path | None:
        if TRACING.exists():
            return TRACING
        if TRACING_LEGACY.exists():
            return TRACING_LEGACY
        return None

    def is_available(self) -> bool:
        root = self.tracing_root
        return root is not None and (root / "current_tracer").exists()

    def record(self, tracer: str = "function_graph") -> dict[str, Any]:
        if not self.enabled:
            return {"recorded": False, "reason": "disabled"}
        root = self.tracing_root
        if not root:
            return {"recorded": False, "reason": "ftrace_not_available"}
        if tracer not in self.TRACERS:
            tracer = "function_graph"

        self.output_dir.mkdir(parents=True, exist_ok=True)
        trace_file = self.output_dir / f"ftrace_{tracer}.txt"

        try:
            (root / "current_tracer").write_text(tracer, encoding="ascii")
            (root / "tracing_on").write_text("1", encoding="ascii")
        except OSError as exc:
            return {"recorded": False, "reason": str(exc)}

        self.runner.run(["sleep", str(int(self.duration_seconds))], timeout=self.duration_seconds + 2)

        try:
            trace_content = (root / "trace").read_text(encoding="utf-8", errors="replace")
            trace_file.write_text(trace_content[:500000], encoding="utf-8")
            (root / "tracing_on").write_text("0", encoding="ascii")
            (root / "current_tracer").write_text("nop", encoding="ascii")
        except OSError as exc:
            return {"recorded": False, "reason": str(exc)}

        return {
            "recorded": True,
            "tracer": tracer,
            "trace_file": str(trace_file),
            "lines": len(trace_content.splitlines()),
            "duration_seconds": self.duration_seconds,
        }

    def record_all(self) -> dict[str, Any]:
        """Run each tracer sequentially (short duration each)."""
        results: dict[str, Any] = {}
        per_tracer = max(3, int(self.duration_seconds / len(self.TRACERS)))
        for tracer in self.TRACERS:
            old_dur = self.duration_seconds
            self.duration_seconds = per_tracer
            results[tracer] = self.record(tracer)
            self.duration_seconds = old_dur
        return {"tracers": results}
