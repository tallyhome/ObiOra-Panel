"""Base sampler interface — extends collector plugin."""

from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Any

from crashhunter.plugins.base import BaseCollector


class BaseSampler(BaseCollector, ABC):
    """Legacy sampler interface; collectors use collect(), samplers use sample()."""

    name: str = "base"

    def collect(self) -> dict[str, Any]:
        return self.sample()

    @abstractmethod
    def sample(self) -> dict[str, Any]:
        """Collect metrics and return a JSON-serializable dict."""
