"""Plugin health manager — auto-disable plugins that consume too many resources."""

from __future__ import annotations

import logging
import time
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.plugins.base import BaseCollector

logger = logging.getLogger("crashhunter.plugin_health")


class PluginHealthManager:
    """
    Monitor per-plugin CPU time (collection duration), failure rate.
    Auto-disable plugins that exceed thresholds; re-enable after cooldown.
    """

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self._disabled_until: dict[str, float] = {}
        self._consecutive_slow: dict[str, int] = {}
        self._consecutive_failures: dict[str, int] = {}

    def before_collect(self, collector: BaseCollector) -> bool:
        """Return False if plugin should be skipped."""
        if not collector.enabled:
            return False
        until = self._disabled_until.get(collector.name, 0)
        if time.monotonic() < until:
            return False
        if until > 0 and time.monotonic() >= until:
            self._disabled_until.pop(collector.name, None)
            collector.enabled = True
            logger.info("Plugin %s re-enabled after cooldown", collector.name)
        return True

    def after_collect(
        self,
        collector: BaseCollector,
        duration_ms: float,
        failed: bool,
    ) -> None:
        cfg = self.settings.plugin_health
        if failed:
            self._consecutive_failures[collector.name] = self._consecutive_failures.get(collector.name, 0) + 1
            if self._consecutive_failures[collector.name] >= cfg.max_consecutive_failures:
                self._disable(collector, f"{cfg.max_consecutive_failures} consecutive failures")
        else:
            self._consecutive_failures[collector.name] = 0

        if duration_ms > cfg.max_plugin_duration_ms:
            self._consecutive_slow[collector.name] = self._consecutive_slow.get(collector.name, 0) + 1
            if self._consecutive_slow[collector.name] >= cfg.max_slow_cycles:
                self._disable(collector, f"slow ({duration_ms:.0f}ms > {cfg.max_plugin_duration_ms}ms)")
        else:
            self._consecutive_slow[collector.name] = 0

    def _disable(self, collector: BaseCollector, reason: str) -> None:
        collector.enabled = False
        cooldown = self.settings.plugin_health.cooldown_seconds
        self._disabled_until[collector.name] = time.monotonic() + cooldown
        logger.warning("Plugin %s auto-disabled: %s (cooldown %ds)", collector.name, reason, cooldown)

    def snapshot(self) -> dict[str, Any]:
        return {
            "disabled_plugins": {
                name: round(until - time.monotonic(), 1)
                for name, until in self._disabled_until.items()
                if time.monotonic() < until
            },
            "consecutive_failures": dict(self._consecutive_failures),
            "consecutive_slow": dict(self._consecutive_slow),
        }
