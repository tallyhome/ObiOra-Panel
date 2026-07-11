"""Markdown report export."""

from __future__ import annotations

from pathlib import Path
from typing import Any


def export_markdown(report: dict[str, Any], path: Path) -> Path:
    lines: list[str] = [
        f"# Crash Hunter Report — {report.get('report_id', 'unknown')}",
        "",
        f"**Hostname:** {report.get('hostname', 'unknown')}",
        f"**Generated:** {report.get('generated_at', '')}",
        f"**Verdict:** {report.get('diagnosis', {}).get('verdict', 'N/A')}",
        f"**Confidence:** {report.get('diagnosis', {}).get('confidence', 0):.0%}",
        "",
        "## Summary",
        "",
        report.get("diagnosis", {}).get("summary", ""),
        "",
        "## Black Box Flight Recorder",
        "",
        f"- Snapshots: {report.get('blackbox', {}).get('snapshot_count', 0)}",
        f"- Duration: {report.get('blackbox', {}).get('duration_minutes', 0)} min",
        "",
        "## Findings",
        "",
    ]

    for finding in report.get("diagnosis", {}).get("findings", []):
        lines.append(f"### {finding['title']} ({finding['confidence']:.0%})")
        lines.append("")
        lines.append(finding["description"])
        lines.append("")
        for ev in finding.get("evidence", [])[:5]:
            lines.append(f"- `{ev[:200]}`")
        lines.append("")

    lines.extend(["## Top Suspicious Events", ""])
    for event in report.get("diagnosis", {}).get("top_suspicious_events", [])[:20]:
        lines.append(
            f"- **{event.get('probability', 0):.0%}** [{event.get('timestamp')}] "
            f"{event.get('event')}: {event.get('detail', '')[:150]}"
        )

    lines.extend(["", "## Recommendations", ""])
    for rec in report.get("recommendations", []):
        lines.append(f"### {rec.get('title', '')} ({rec.get('confidence', 0):.0%})")
        for action in rec.get("actions", []):
            lines.append(f"- {action}")
        lines.append("")

    causal = report.get("causal_correlation", {})
    if causal.get("story_text"):
        lines.extend(["## Causal Correlation", "", "```", causal["story_text"], "```", ""])

    reboot_cls = report.get("reboot_classification", {})
    if reboot_cls:
        lines.extend([
            "## Reboot Classification", "",
            f"- **Type:** {reboot_cls.get('reboot_type', 'unknown')}",
            f"- **Confidence:** {reboot_cls.get('confidence', 0):.0%}",
            "",
        ])

    lines.extend(["", "## Chronological Timeline", ""])
    chrono = report.get("chronological_report", {})
    for line in chrono.get("narrative", []):
        lines.append(f"- `{line}`")
    lines.append("")
    lines.append(f"**Probable root cause:** {chrono.get('probable_root_cause', 'Unknown')}")
    lines.append("")

    similar = report.get("similar_crashes", [])
    if similar:
        lines.extend(["## Similar Past Crashes", ""])
        for s in similar[:5]:
            lines.append(
                f"- **{s.get('confidence', 0):.0%}** similar to {s.get('report_id')} "
                f"— {s.get('probable_root_cause', '')}"
            )
        lines.append("")

    sig = report.get("version_signature", {})
    if sig:
        lines.extend(["## Version Signature", ""])
        for key, val in sig.items():
            lines.append(f"- **{key}:** {val}")
        lines.append("")

    lines.extend(["", "## Kernel Events (sample)", ""])
    for line in report.get("blackbox", {}).get("kernel_events", [])[-30:]:
        lines.append(f"- `{line[:250]}`")

    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("\n".join(lines), encoding="utf-8")
    return path
