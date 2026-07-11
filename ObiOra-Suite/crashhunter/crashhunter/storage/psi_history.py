"""PSI history — track /proc/pressure over time."""

from __future__ import annotations

import json
import logging
from collections import deque
from pathlib import Path
from typing import Any

from crashhunter.utils.proc import ProcReader

logger = logging.getLogger("crashhunter.psi_history")


class PsiHistoryStore:
    """Rolling history of PSI metrics with disk persistence."""

    def __init__(self, state_file: Path, max_entries: int = 720) -> None:
        self.state_file = state_file
        self.max_entries = max_entries
        self._buffer: deque[dict[str, Any]] = deque(maxlen=max_entries)
        self._load()

    def record(self, timestamp: str, pressure_data: dict[str, Any] | None = None) -> dict[str, Any]:
        if pressure_data is None:
            pressure_data = ProcReader.pressure()

        entry = {"timestamp": timestamp, "pressure": pressure_data}
        self._buffer.append(entry)
        return entry

    def get_history(self, limit: int | None = None) -> list[dict[str, Any]]:
        items = list(self._buffer)
        if limit:
            return items[-limit:]
        return items

    def get_trends(self) -> dict[str, Any]:
        """Summarize PSI trends over stored history."""
        history = list(self._buffer)
        if len(history) < 2:
            return {"samples": len(history), "trends": {}}

        trends: dict[str, Any] = {}
        for resource in ("cpu", "memory", "io"):
            values = []
            for entry in history:
                p = entry.get("pressure", {}).get(resource, {})
                if isinstance(p, dict) and p.get("avg10") is not None:
                    values.append(float(p["avg10"]))
            if values:
                trends[resource] = {
                    "min_avg10": min(values),
                    "max_avg10": max(values),
                    "latest_avg10": values[-1],
                    "rising": values[-1] > values[0] * 1.5 if values[0] > 0 else values[-1] > 5,
                }
        return {"samples": len(history), "trends": trends}

    def flush(self) -> None:
        try:
            self.state_file.parent.mkdir(parents=True, exist_ok=True)
            self.state_file.write_text(
                json.dumps(list(self._buffer), ensure_ascii=False),
                encoding="utf-8",
            )
        except OSError as exc:
            logger.warning("PSI history flush failed: %s", exc)

    def _load(self) -> None:
        if not self.state_file.exists():
            return
        try:
            data = json.loads(self.state_file.read_text(encoding="utf-8"))
            for entry in data[-self.max_entries :]:
                self._buffer.append(entry)
        except (OSError, json.JSONDecodeError):
            pass
