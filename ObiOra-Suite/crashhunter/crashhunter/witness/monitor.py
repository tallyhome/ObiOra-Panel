"""Witness timeout monitor — detects silent death on the VPS."""

from __future__ import annotations

import logging
import threading
import time
from datetime import datetime, timezone
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.witness.store import WitnessStore

logger = logging.getLogger("crashhunter.witness.monitor")


class WitnessMonitor:
    """Background thread: mark hosts dead when heartbeats stop."""

    def __init__(self, settings: Settings, store: WitnessStore) -> None:
        self.settings = settings
        self.store = store
        self._host_state: dict[str, str] = {}
        self._thread: threading.Thread | None = None
        self._running = False

    def start(self) -> None:
        if not self.settings.witness.monitor_enabled:
            return
        self._running = True
        self._thread = threading.Thread(target=self._loop, daemon=True, name="witness-monitor")
        self._thread.start()
        logger.info("Witness monitor started (timeout=%ss)", self.settings.witness.timeout_seconds)

    def stop(self) -> None:
        self._running = False

    def check_all(self) -> list[dict[str, Any]]:
        """Single check pass — returns status for all hosts."""
        results: list[dict[str, Any]] = []
        now = datetime.now(timezone.utc)
        for host in self.store.list_hosts():
            latest = self.store.latest_heartbeat(host)
            if not latest:
                continue
            age = _heartbeat_age_seconds(latest, now)
            prev = self._host_state.get(host, "alive")
            if age <= self.settings.witness.timeout_seconds:
                status = "alive"
            elif age <= self.settings.witness.death_threshold_seconds:
                status = "timeout"
            else:
                status = "dead"
            if status != prev and status in ("timeout", "dead"):
                event = {
                    "host": host,
                    "event": "witness_timeout" if status == "timeout" else "witness_dead",
                    "age_seconds": round(age, 1),
                    "last_heartbeat": latest.get("timestamp"),
                    "message": f"No heartbeat for {age:.0f}s — machine {'timing out' if status == 'timeout' else 'presumed dead'}",
                }
                self.store.record_event(event)
                logger.critical("WITNESS %s: %s (%.0fs)", status.upper(), host, age)
            self._host_state[host] = status
            results.append({"host": host, "status": status, "age_seconds": round(age, 1), "latest": latest})
        return results

    def _loop(self) -> None:
        while self._running:
            try:
                self.check_all()
            except Exception as exc:
                logger.exception("Witness monitor error: %s", exc)
            time.sleep(self.settings.witness.check_interval_seconds)


def _heartbeat_age_seconds(latest: dict[str, Any], now: datetime) -> float:
    ts = latest.get("received_at") or latest.get("timestamp", "")
    if not ts:
        return 9999.0
    try:
        if ts.endswith("Z"):
            dt = datetime.fromisoformat(ts.replace("Z", "+00:00"))
        elif "+" in ts or ts.count("-") > 2:
            dt = datetime.fromisoformat(ts)
        else:
            return 9999.0
        return max(0.0, (now - dt).total_seconds())
    except ValueError:
        return 9999.0
