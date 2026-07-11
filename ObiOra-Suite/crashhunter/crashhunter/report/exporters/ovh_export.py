"""OVH support ticket report generator."""

from __future__ import annotations

import json
import logging
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from crashhunter.report.exporters.bundle_export import export_bundle

logger = logging.getLogger("crashhunter.ovh_export")


def generate_ovh_report(
    report: dict[str, Any],
    report_dir: Path,
    base_dir: Path,
) -> dict[str, Any]:
    """
    Generate OVH-ready package:
    - summary text
    - chronological story
    - hypotheses
    - relevant logs
    - diagnostic bundle
    """
    diagnosis = report.get("diagnosis", {})
    chronological = report.get("chronological_report", {})
    reboot = report.get("reboot_classification", {})
    similar = report.get("similar_crashes", [])
    recommendations = report.get("recommendations", [])

    summary_lines = [
        "=== CrashHunter OVH Support Report ===",
        f"Generated: {datetime.now(timezone.utc).isoformat()}",
        f"Hostname: {report.get('hostname', 'unknown')}",
        f"Report ID: {report.get('report_id', 'unknown')}",
        f"CrashHunter: {report.get('crashhunter_version', 'unknown')}",
        "",
        "--- SUMMARY ---",
        f"Verdict: {diagnosis.get('verdict', 'unknown')}",
        f"Confidence: {diagnosis.get('confidence', 'unknown')}",
        f"Reboot type: {reboot.get('reboot_type', 'unknown')}",
        f"Reboot evidence: {', '.join(reboot.get('evidence', []))}",
        "",
    ]

    story = chronological.get("causal_story") or chronological.get("story_text", "")
    if story:
        summary_lines.extend(["--- CHRONOLOGY ---", story, ""])

    findings = diagnosis.get("findings", [])
    if findings:
        summary_lines.append("--- HYPOTHESES ---")
        for i, f in enumerate(findings[:10], 1):
            summary_lines.append(
                f"{i}. [{f.get('severity', '?')}] {f.get('category', '?')}: {f.get('description', '')}"
            )
        summary_lines.append("")

    if similar:
        summary_lines.append("--- SIMILAR INCIDENTS ---")
        for s in similar[:5]:
            summary_lines.append(
                f"- {s.get('report_id')}: {s.get('confidence', 0)*100:.0f}% match — {s.get('probable_root_cause', '?')}"
            )
        summary_lines.append("")

    if recommendations:
        summary_lines.append("--- RECOMMENDATIONS ---")
        for rec in recommendations[:8]:
            summary_lines.append(f"- {rec.get('title', '')}: {rec.get('description', '')}")
        summary_lines.append("")

    blackbox = report.get("blackbox", {})
    events = blackbox.get("top_suspicious_events", [])
    if events:
        summary_lines.append("--- RELEVANT EVENTS ---")
        for evt in events[:15]:
            summary_lines.append(f"- {evt.get('timestamp', '?')}: {evt.get('event', '?')} — {evt.get('detail', '')}")
        summary_lines.append("")

    kernel_excerpt = _extract_kernel_excerpt(report)
    if kernel_excerpt:
        summary_lines.extend(["--- KERNEL LOG EXCERPT ---", kernel_excerpt, ""])

    summary_text = "\n".join(summary_lines)

    ovh_dir = report_dir / "ovh_report"
    ovh_dir.mkdir(parents=True, exist_ok=True)
    summary_path = ovh_dir / "OVH_SUMMARY.txt"
    summary_path.write_text(summary_text, encoding="utf-8")

    json_path = ovh_dir / "ovh_report.json"
    ovh_json = {
        "report_id": report.get("report_id"),
        "hostname": report.get("hostname"),
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "summary": diagnosis.get("verdict"),
        "reboot_type": reboot.get("reboot_type"),
        "chronology": story,
        "hypotheses": findings,
        "similar_incidents": similar,
        "recommendations": recommendations,
    }
    json_path.write_text(json.dumps(ovh_json, indent=2, ensure_ascii=False), encoding="utf-8")

    bundle_path = export_bundle(report, report_dir, base_dir)

    return {
        "summary_path": str(summary_path),
        "json_path": str(json_path),
        "bundle_path": str(bundle_path),
        "summary_preview": summary_text[:2000],
    }


def _extract_kernel_excerpt(report: dict[str, Any]) -> str:
    lines: list[str] = []
    blackbox = report.get("blackbox", {})
    for evt in blackbox.get("top_suspicious_events", []):
        detail = evt.get("detail", "")
        if any(k in detail.lower() for k in ("panic", "watchdog", "oom", "error", "reset", "stall")):
            lines.append(detail[:300])
    incident = report.get("incident", {})
    if isinstance(incident, dict):
        for snap in incident.get("snapshots", [])[:3]:
            dmesg = snap.get("commands", {}).get("dmesg", {})
            if isinstance(dmesg, dict) and dmesg.get("stdout"):
                lines.extend(dmesg["stdout"].splitlines()[-20:])
    return "\n".join(lines[:50])
