"""Shared helpers for diagnostic modules."""

from __future__ import annotations

from core.models import Finding, Severity
from core.runner import CommandRunner


def systemd_active(runner: CommandRunner, unit: str) -> tuple[bool, str]:
    """Check if a systemd unit is active.

    Returns:
        Tuple of (is_active, stdout_or_stderr).
    """

    result = runner.run(["systemctl", "is-active", unit], timeout_seconds=5)
    if result.missing:
        return False, "systemctl unavailable"
    return result.stdout.strip() == "active", result.stdout or result.stderr


def systemd_failed_units(runner: CommandRunner) -> list[str]:
    """Return failed systemd unit names."""

    result = runner.run(["systemctl", "--failed", "--no-legend", "--no-pager"])
    if not result.ok:
        return []
    units: list[str] = []
    for line in result.stdout.splitlines():
        parts = line.split()
        if parts:
            units.append(parts[0])
    return units


def service_finding(
    name: str,
    active: bool,
    detail: str,
    optional: bool = False,
) -> Finding:
    """Build a standard service status finding."""

    if active:
        return Finding(
            Severity.INFO,
            f"{name} actif",
            detail,
            "Aucune action requise.",
            [f"systemctl status {name}"],
        )
    if optional:
        return Finding(
            Severity.INFO,
            f"{name} non detecte",
            detail,
            "Aucune action requise si ce service n'est pas utilise.",
            [f"systemctl status {name}"],
        )
    return Finding(
        Severity.WARNING,
        f"{name} inactif",
        detail,
        f"Verifier le service {name}.",
        [f"systemctl status {name}"],
    )
