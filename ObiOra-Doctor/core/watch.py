"""Real-time watch mode for Obiora Doctor."""

from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Any

from core.engine import DiagnosticEngine
from core.terminal import print_summary


def run_watch(
    engine: DiagnosticEngine,
    *,
    interval: float = 1.0,
    modules: list[str] | None = None,
    cache_dir: str = "cache",
    history_limit: int = 3600,
    color: bool = True,
) -> int:
    """Run continuous diagnostics with periodic refresh.

    Parameters:
        engine: Diagnostic engine instance.
        interval: Refresh interval in seconds.
        modules: Optional module filter.
        cache_dir: Directory for watch history.
        history_limit: Maximum history entries to keep.
        color: Enable ANSI colors.

    Returns:
        Exit code when interrupted.
    """

    history_path = Path(cache_dir) / "watch"
    history_path.mkdir(parents=True, exist_ok=True)
    print("Obiora Watch - Ctrl+C pour arreter")

    try:
        while True:
            report = engine.run(modules)
            timestamp = report.generated_at.replace(":", "-")
            snapshot = history_path / f"{timestamp}.json"
            snapshot.write_text(
                json.dumps(report.to_dict(), ensure_ascii=False, indent=2),
                encoding="utf-8",
            )
            _trim_history(history_path, history_limit)
            os.system("cls" if os.name == "nt" else "clear")
            print_summary(report, str(history_path), color=color)
            time.sleep(interval)
    except KeyboardInterrupt:
        print("\nWatch arrete.")
        return 0


def _trim_history(history_path: Path, limit: int) -> None:
    """Keep only the most recent watch history files."""

    files = sorted(history_path.glob("*.json"), reverse=True)
    for stale in files[limit:]:
        stale.unlink(missing_ok=True)
