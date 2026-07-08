"""Diagnostic orchestration engine."""

from __future__ import annotations

import datetime as dt
import socket
import sys
from typing import Any, Iterable

from core.config import load_config
from core.knowledge import enrich_report
from core.models import Report
from core.module import DiagnosticModule
from core.os_detect import detect_os
from core.runner import CommandRunner
from core.schema import REPORT_SCHEMA_VERSION, validate_report


VERSION = "0.5.0"


class DiagnosticEngine:
    """Run modules and build a complete diagnostic report."""

    def __init__(
        self,
        modules: Iterable[type[DiagnosticModule]],
        runner: CommandRunner | None = None,
        config: dict[str, Any] | None = None,
    ) -> None:
        """Create a diagnostic engine."""

        self.config = config or load_config()
        timeout = int(self.config.get("timeout_seconds", 8))
        self.runner = runner or CommandRunner(timeout_seconds=timeout)
        self.module_classes = list(modules)

    def build_context(self) -> dict[str, object]:
        """Build shared execution context for all modules."""

        is_linux = sys.platform.startswith("linux")
        os_info = detect_os() if is_linux else {}
        return {
            "version": VERSION,
            "schema_version": REPORT_SCHEMA_VERSION,
            "hostname": socket.gethostname(),
            "platform": sys.platform,
            "system": "Linux" if is_linux else sys.platform,
            "os": os_info,
            "config": self.config,
            "cache_dir": self.config.get("cache_dir", "cache"),
            "generated_at": dt.datetime.now(dt.timezone.utc)
            .replace(microsecond=0)
            .isoformat(),
        }

    def run(
        self,
        only_modules: list[str] | None = None,
        exclude_modules: list[str] | None = None,
    ) -> Report:
        """Run selected modules and return a complete report."""

        context = self.build_context()
        selected = set(only_modules or [])
        excluded = set(exclude_modules or [])
        results = []

        for module_class in self.module_classes:
            module = module_class(self.runner)
            if selected and module.name not in selected:
                continue
            if module.name in excluded:
                continue
            results.append(module.run(context))

        score = self.global_score([result.score for result in results])
        host: dict[str, object] = {
            "hostname": context["hostname"],
            "platform": context["platform"],
            "system": context["system"],
            "schema_version": REPORT_SCHEMA_VERSION,
        }
        if context.get("os"):
            host["os"] = context["os"]

        return Report(
            version=VERSION,
            generated_at=str(context["generated_at"]),
            host=host,
            score=score,
            results=results,
        )

    def run_validated(
        self,
        only_modules: list[str] | None = None,
        exclude_modules: list[str] | None = None,
    ) -> tuple[Report, list[str]]:
        """Run modules and validate the resulting report schema."""

        report = self.run(only_modules, exclude_modules)
        errors = validate_report(enrich_report(report.to_dict()))
        return report, errors

    @staticmethod
    def global_score(scores: list[int]) -> int:
        """Compute the global health score from module scores."""

        if not scores:
            return 0
        return round(sum(scores) / len(scores))
