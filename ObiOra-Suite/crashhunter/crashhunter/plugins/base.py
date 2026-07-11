"""Base collector plugin interface."""

from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Any


class BaseCollector(ABC):
    """
    Every collector is a plugin.
    Collector failures must NEVER stop CrashHunter — the registry catches exceptions.
    """

    name: str = "base"
    enabled: bool = True
    priority: int = 100

    @abstractmethod
    def collect(self) -> dict[str, Any]:
        """Collect metrics and return a JSON-serializable dict fragment."""
