"""Obiora Rescue - read-only remediation recommendations."""

from __future__ import annotations

from core.knowledge import enrich_finding
from core.models import Report, Severity


def generate_rescue_plan(report: Report) -> str:
    """Generate a read-only rescue plan from report findings.

    Parameters:
        report: Complete diagnostic report.

    Returns:
        Plain text rescue plan. Never modifies the system.

    Example:
        plan = generate_rescue_plan(report)
        print(plan)
    """

    lines = [
        "OBIORA RESCUE - PLAN DE DEPANNAGE (LECTURE SEULE)",
        f"Health Score: {report.score}%",
        "",
        "Aucune action automatique ne sera executee.",
        "",
    ]

    critical_count = 0
    warning_count = 0

    for result in report.results:
        for finding in result.findings:
            if finding.level == Severity.CRITICAL:
                critical_count += 1
                lines.extend(_format_rescue_item(result.module, finding, "CRITIQUE"))
            elif finding.level == Severity.WARNING:
                warning_count += 1
                lines.extend(_format_rescue_item(result.module, finding, "ATTENTION"))

    if critical_count == 0 and warning_count == 0:
        lines.append("Aucun probleme critique ou warning detecte.")
    else:
        lines.extend(
            [
                "",
                f"Resume: {critical_count} critique(s), {warning_count} warning(s).",
                "Toujours sauvegarder avant toute action corrective.",
            ]
        )
    return "\n".join(lines) + "\n"


def _format_rescue_item(module: str, finding, level: str) -> list[str]:
    enriched = enrich_finding(finding.title, finding.details, finding.recommendation)
    lines = [
        f"[{level}] Module: {module}",
        f"  Probleme: {finding.title}",
        f"  Cause probable: {enriched['probable_cause'] or 'A determiner'}",
        f"  Action: {enriched['suggested_action']}",
    ]
    if finding.commands:
        lines.append(f"  Verification: {'; '.join(finding.commands)}")
    lines.append("")
    return lines
