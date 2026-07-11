"""Aggregates all collector plugins into a single snapshot."""

from __future__ import annotations

import logging
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.plugins.registry import CollectorRegistry

logger = logging.getLogger("crashhunter.sampler")


class SnapshotAggregator:
    """Collect a full system snapshot via the plugin collector registry."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.registry = CollectorRegistry(settings)

    def collect(self, emergency: bool = False) -> dict[str, Any]:
        return self.registry.collect_all(emergency=emergency)

    @property
    def failure_counts(self) -> dict[str, int]:
        return self.registry.failure_counts

    @property
    def last_durations(self) -> dict[str, float]:
        return self.registry.last_durations
