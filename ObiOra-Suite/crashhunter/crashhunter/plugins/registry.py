"""Plugin collector registry — Enterprise plugins + health management."""

from __future__ import annotations

import logging
import time
from importlib import metadata
from typing import Any

from crashhunter.config.settings import Settings
from crashhunter.monitoring.plugin_health import PluginHealthManager
from crashhunter.plugins.base import BaseCollector
from crashhunter.plugins.collectors import build_enterprise_collectors
from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.plugins")


class _SamplerAdapter(BaseCollector):
    """Wraps legacy samplers as collectors."""

    def __init__(self, sampler: Any, name: str) -> None:
        self._sampler = sampler
        self.name = name
        self.enabled = True
        self.priority = 100

    def collect(self) -> dict[str, Any]:
        return self._sampler.sample()


class CollectorRegistry:
    """Load, filter and run collector plugins. Failures never stop the daemon."""

    ENTRY_POINT_GROUP = "crashhunter.collectors"

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.runner = SubprocessRunner(default_timeout=settings.subprocess_timeout)
        self.health = PluginHealthManager(settings) if settings.plugin_health.enabled else None
        self._collectors: list[BaseCollector] = []
        self._failures: dict[str, int] = {}
        self._last_durations: dict[str, float] = {}
        self._command_alerts: list[dict[str, Any]] = []
        self._build_collectors()

    def _build_collectors(self) -> None:
        from crashhunter.freeze.dstate import DStateInvestigator
        from crashhunter.freeze.probes import ResponsivenessProbes
        from crashhunter.samplers.cpu_sampler import CpuSampler
        from crashhunter.samplers.disk_sampler import DiskSampler
        from crashhunter.samplers.hardware_sampler import HardwareSampler
        from crashhunter.samplers.kernel_sampler import KernelSampler
        from crashhunter.samplers.memory_sampler import MemorySampler
        from crashhunter.samplers.network_sampler import NetworkSampler
        from crashhunter.samplers.process_sampler import ProcessSampler
        from crashhunter.samplers.system_sampler import SystemSampler
        from crashhunter.samplers.virtualizor_sampler import VirtualizorSampler

        enabled = set(self.settings.enabled_collectors)
        builtins: list[BaseCollector] = [
            _SamplerAdapter(SystemSampler(), "system"),
            _SamplerAdapter(CpuSampler(), "cpu"),
            _SamplerAdapter(MemorySampler(self.runner), "memory"),
            _SamplerAdapter(DiskSampler(self.runner), "disk"),
            _SamplerAdapter(NetworkSampler(self.runner), "network"),
            _SamplerAdapter(ProcessSampler(self.settings), "processes"),
            _SamplerAdapter(KernelSampler(self.settings, self.runner), "kernel"),
            _SamplerAdapter(VirtualizorSampler(self.runner), "virtualizor"),
            _SamplerAdapter(HardwareSampler(self.runner), "hardware"),
            ResponsivenessProbes(self.settings, self.runner),
            DStateInvestigator(),
        ]
        builtins.extend(build_enterprise_collectors(self.settings, self.runner))

        seen_names: set[str] = set()
        for collector in builtins:
            if collector.name in seen_names:
                continue
            seen_names.add(collector.name)
            if collector.name in enabled:
                collector.enabled = True
                self._collectors.append(collector)

        self._load_entry_point_plugins(enabled)
        self._collectors.sort(key=lambda c: c.priority)

    def _load_entry_point_plugins(self, enabled: set[str]) -> None:
        try:
            eps = metadata.entry_points()
            group = eps.select(group=self.ENTRY_POINT_GROUP) if hasattr(eps, "select") else eps.get(self.ENTRY_POINT_GROUP, [])
        except Exception as exc:
            logger.debug("No entry point plugins: %s", exc)
            return
        for ep in group:
            try:
                cls = ep.load()
                instance = cls(self.settings, self.runner)
                if isinstance(instance, BaseCollector) and instance.name in enabled:
                    self._collectors.append(instance)
                    logger.info("Loaded plugin collector: %s", instance.name)
            except Exception as exc:
                logger.warning("Failed to load plugin %s: %s", ep.name, exc)

    def collect_all(self, emergency: bool = False) -> dict[str, Any]:
        """Run all enabled collectors; failures are isolated per plugin."""
        from crashhunter.utils.timestamp import now_iso_us

        snapshot: dict[str, Any] = {
            "schema_version": 3,
            "collector": "crashhunter-enterprise",
            "mode": "emergency" if emergency else "normal",
            "timestamp_us": now_iso_us(),
        }
        self._command_alerts.clear()
        start = time.monotonic()

        for collector in self._collectors:
            if self.health and not self.health.before_collect(collector):
                continue
            if not collector.enabled:
                continue

            c_start = time.monotonic()
            failed = False
            try:
                data = collector.collect()
                snapshot[collector.name] = data
                self._failures.pop(collector.name, None)
                if isinstance(data, dict) and data.get("alerts"):
                    self._command_alerts.extend(data["alerts"])
            except Exception as exc:
                failed = True
                logger.exception("Collector %s failed: %s", collector.name, exc)
                self._failures[collector.name] = self._failures.get(collector.name, 0) + 1
                snapshot[collector.name] = {"error": str(exc), "failed": True}
                if hasattr(collector, "failure_count"):
                    collector.failure_count += 1

            duration = round((time.monotonic() - c_start) * 1000, 2)
            self._last_durations[collector.name] = duration
            if self.health:
                self.health.after_collect(collector, duration, failed)

        snapshot["collection_duration_ms"] = round((time.monotonic() - start) * 1000, 2)
        snapshot["collector_durations_ms"] = dict(self._last_durations)
        snapshot["collector_failures"] = dict(self._failures)
        snapshot["command_alerts"] = list(self._command_alerts)
        if self.health:
            snapshot["plugin_health"] = self.health.snapshot()
        return snapshot

    @property
    def failure_counts(self) -> dict[str, int]:
        return dict(self._failures)

    @property
    def last_durations(self) -> dict[str, float]:
        return dict(self._last_durations)
