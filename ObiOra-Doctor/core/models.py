"""Shared data models for Obiora Doctor."""

from __future__ import annotations

from dataclasses import asdict, dataclass, field
from enum import Enum
from typing import Any


class Severity(str, Enum):
    """Finding severity used by diagnostic modules."""

    INFO = "INFO"
    WARNING = "WARNING"
    CRITICAL = "CRITICAL"


@dataclass(frozen=True)
class Finding:
    """A human-readable diagnostic finding.

    Parameters:
        level: Severity of the finding.
        title: Short finding title.
        details: Detailed explanation.
        recommendation: Action recommended to the operator.
        commands: Optional commands useful for manual verification.

    Returns:
        Immutable finding object.

    Example:
        Finding(Severity.INFO, "RAM detected", "64 GB available", "No action.")
    """

    level: Severity
    title: str
    details: str
    recommendation: str = "Aucune action requise."
    commands: list[str] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        """Return a JSON-serializable representation of the finding."""

        data = asdict(self)
        data["level"] = self.level.value
        return data


@dataclass(frozen=True)
class CommandResult:
    """Result of a system command execution.

    Parameters:
        command: Executed command as a list.
        stdout: Standard output.
        stderr: Standard error.
        returncode: Process return code, or None when not executed.
        duration_ms: Execution duration.
        timed_out: Whether the command exceeded the timeout.
        missing: Whether the executable was missing.

    Returns:
        Immutable command result.
    """

    command: list[str]
    stdout: str
    stderr: str
    returncode: int | None
    duration_ms: int
    timed_out: bool = False
    missing: bool = False

    @property
    def ok(self) -> bool:
        """Return True when the command completed successfully."""

        return self.returncode == 0 and not self.timed_out and not self.missing

    def to_dict(self) -> dict[str, Any]:
        """Return a JSON-serializable representation of the command result."""

        return asdict(self)


@dataclass(frozen=True)
class ModuleResult:
    """Normalized output returned by every diagnostic module.

    Parameters:
        module: Module identifier.
        status: Machine-readable status.
        score: Health score from 0 to 100.
        findings: Diagnostic findings.
        metrics: Structured metrics collected by the module.
        duration_ms: Module execution duration.

    Returns:
        Immutable module result.
    """

    module: str
    status: str
    score: int
    findings: list[Finding]
    metrics: dict[str, Any] = field(default_factory=dict)
    duration_ms: int = 0

    def to_dict(self) -> dict[str, Any]:
        """Return a JSON-serializable representation of the module result."""

        return {
            "module": self.module,
            "status": self.status,
            "score": self.score,
            "findings": [finding.to_dict() for finding in self.findings],
            "metrics": self.metrics,
            "duration_ms": self.duration_ms,
        }


@dataclass(frozen=True)
class Report:
    """Complete diagnostic report.

    Parameters:
        version: Obiora Doctor version.
        generated_at: ISO-8601 generation date.
        host: Host metadata.
        score: Global health score.
        results: Module results.

    Returns:
        Immutable report object.
    """

    version: str
    generated_at: str
    host: dict[str, Any]
    score: int
    results: list[ModuleResult]

    def to_dict(self) -> dict[str, Any]:
        """Return a JSON-serializable representation of the report."""

        return {
            "version": self.version,
            "generated_at": self.generated_at,
            "host": self.host,
            "score": self.score,
            "results": [result.to_dict() for result in self.results],
        }
