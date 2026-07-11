"""perf record during incident mode."""

from __future__ import annotations

import logging
import shutil
import threading
from pathlib import Path
from typing import Any

from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.perf")


class PerfRecorder:
    """Run perf record during incident and produce report/script output."""

    def __init__(
        self,
        output_dir: Path,
        duration_seconds: float = 30.0,
        enabled: bool = True,
    ) -> None:
        self.output_dir = output_dir
        self.duration_seconds = duration_seconds
        self.enabled = enabled
        self.runner = SubprocessRunner(default_timeout=duration_seconds + 10)
        self._thread: threading.Thread | None = None
        self._result: dict[str, Any] | None = None

    def is_available(self) -> bool:
        return shutil.which("perf") is not None

    def start_async(self) -> bool:
        if not self.enabled or not self.is_available():
            return False
        if self._thread and self._thread.is_alive():
            return False
        self._thread = threading.Thread(target=self._run, daemon=True, name="perf-record")
        self._thread.start()
        return True

    def _run(self) -> None:
        self._result = self.record()

    def get_result(self, wait: bool = False, timeout: float = 35.0) -> dict[str, Any] | None:
        if wait and self._thread:
            self._thread.join(timeout=timeout)
        return self._result

    def record(self) -> dict[str, Any]:
        if not self.enabled:
            return {"recorded": False, "reason": "disabled"}
        if not self.is_available():
            return {"recorded": False, "reason": "perf_not_installed"}

        self.output_dir.mkdir(parents=True, exist_ok=True)
        data_file = self.output_dir / "incident.data"
        report_file = self.output_dir / "incident.perf.txt"
        script_file = self.output_dir / "incident.perf.script"

        record = self.runner.run(
            ["perf", "record", "-o", str(data_file), "-a", "-g", "--", "sleep", str(int(self.duration_seconds))],
            timeout=self.duration_seconds + 15,
        )
        if record.returncode != 0 and not data_file.exists():
            return {"recorded": False, "reason": record.stderr[:500], "timed_out": record.timed_out}

        report = self.runner.run(["perf", "report", "-i", str(data_file), "--stdio"], timeout=30.0)
        if report.stdout:
            report_file.write_text(report.stdout[:200000], encoding="utf-8")

        script = self.runner.run(["perf", "script", "-i", str(data_file)], timeout=30.0)
        if script.stdout:
            script_file.write_text(script.stdout[:200000], encoding="utf-8")

        return {
            "recorded": True,
            "data_file": str(data_file),
            "report_file": str(report_file),
            "script_file": str(script_file),
            "duration_seconds": self.duration_seconds,
        }
