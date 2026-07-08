"""Base contract for diagnostic modules."""

from __future__ import annotations

import time
from abc import ABC, abstractmethod
from typing import Any

from core.models import Finding, ModuleResult, Severity
from core.runner import CommandRunner


class DiagnosticModule(ABC):
    """Base class implemented by every Obiora Doctor module."""

    name: str = "module"
    title: str = "Module"
    linux_only: bool = True

    def __init__(self, runner: CommandRunner) -> None:
        """Create a diagnostic module.

        Parameters:
            runner: Shared command runner.

        Returns:
            Diagnostic module instance.
        """

        self.runner = runner

    def init(self, context: dict[str, Any]) -> None:
        """Initialize module state before scan.

        Parameters:
            context: Shared execution context.

        Returns:
            None.
        """

    @abstractmethod
    def scan(self, context: dict[str, Any]) -> dict[str, Any]:
        """Collect raw data for this module."""

    @abstractmethod
    def diagnostic(
        self,
        raw_data: dict[str, Any],
        context: dict[str, Any],
    ) -> list[Finding]:
        """Convert raw data into findings."""

    def score(self, findings: list[Finding], context: dict[str, Any]) -> int:
        """Compute a health score from findings.

        Parameters:
            findings: Findings returned by the module.
            context: Shared execution context.

        Returns:
            Integer score from 0 to 100.
        """

        score = 100
        for finding in findings:
            if finding.level == Severity.CRITICAL:
                score -= 35
            elif finding.level == Severity.WARNING:
                score -= 12
        return max(0, min(100, score))

    def recommendations(self, findings: list[Finding]) -> list[str]:
        """Return unique recommendations from findings."""

        recommendations: list[str] = []
        for finding in findings:
            if finding.recommendation not in recommendations:
                recommendations.append(finding.recommendation)
        return recommendations

    def run(self, context: dict[str, Any]) -> ModuleResult:
        """Execute init, scan, diagnostic and scoring for this module."""

        started_at = time.monotonic()
        if self.linux_only and context.get("system") != "Linux":
            return ModuleResult(
                module=self.name,
                status="skipped",
                score=100,
                findings=[
                    Finding(
                        Severity.INFO,
                        f"{self.title} non execute",
                        "Ce module est reserve aux serveurs Linux.",
                        "Executer Obiora Doctor sur le serveur Linux a diagnostiquer.",
                    )
                ],
                duration_ms=int((time.monotonic() - started_at) * 1000),
            )

        self.init(context)
        try:
            raw_data = self.scan(context)
            findings = self.diagnostic(raw_data, context)
            status = "ok"
        except Exception as exc:  # pragma: no cover - defensive isolation
            raw_data = {"error": str(exc)}
            findings = [
                Finding(
                    Severity.CRITICAL,
                    f"{self.title} indisponible",
                    str(exc),
                    "Verifier le module et relancer le scan en mode debug.",
                )
            ]
            status = "error"

        duration_ms = int((time.monotonic() - started_at) * 1000)
        return ModuleResult(
            module=self.name,
            status=status,
            score=self.score(findings, context),
            findings=findings,
            metrics=raw_data.get("metrics", {}),
            duration_ms=duration_ms,
        )
