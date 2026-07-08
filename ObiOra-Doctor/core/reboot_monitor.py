"""24-hour reboot monitoring and detailed reboot cause reports."""

from __future__ import annotations

import json
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from core.engine import DiagnosticEngine
from core.reports import write_report_bundle
from core.runner import CommandRunner


def analyze_last_24h(runner: CommandRunner | None = None) -> dict[str, Any]:
    """Analyze journal logs from the last 24 hours for reboot indicators.

    Parameters:
        runner: Optional command runner.

    Returns:
        Analysis dictionary with events and probable causes.
    """

    runner = runner or CommandRunner(timeout_seconds=20)
    since = runner.run(
        [
            "journalctl",
            "--since",
            "24 hours ago",
            "--no-pager",
            "-g",
            "reboot|shutdown|power|oom|panic|watchdog|Out of memory|kernel panic|"
            "systemd-shutdown|Rebooting|reboot required",
        ],
        timeout_seconds=20,
    )
    boots = runner.run(["journalctl", "--list-boots", "--no-pager"])
    reboot_events = _parse_journal_events(since.stdout)
    boot_events = _parse_boot_list(boots.stdout)

    return {
        "period": "24h",
        "analyzed_at": datetime.now(timezone.utc).replace(microsecond=0).isoformat(),
        "reboot_events": reboot_events,
        "boot_list": boot_events,
        "reboot_count_24h": len(boot_events),
        "probable_causes": _infer_causes(reboot_events),
        "next_reboot_risk": _estimate_next_reboot(runner),
    }


def run_reboot_watch(
    engine: DiagnosticEngine,
    *,
    hours: float = 24,
    interval_minutes: int = 5,
    cache_dir: str = "cache",
    reports_dir: str = "reports",
) -> int:
    """Monitor reboot indicators over a period and generate a detailed report.

    Parameters:
        engine: Diagnostic engine.
        hours: Monitoring duration in hours.
        interval_minutes: Snapshot interval.
        cache_dir: Cache directory for snapshots.
        reports_dir: Output reports directory.

    Returns:
        Exit code.
    """

    watch_dir = Path(cache_dir) / "reboot-watch"
    watch_dir.mkdir(parents=True, exist_ok=True)
    runner = engine.runner
    end_at = time.monotonic() + (hours * 3600)
    snapshots: list[dict[str, Any]] = []

    print(f"Reboot Monitor - {hours}h, snapshot toutes les {interval_minutes} min")
    print("Ctrl+C pour arreter et generer le rapport.")

    try:
        while time.monotonic() < end_at:
            analysis = analyze_last_24h(runner)
            report = engine.run(["reboot"])
            snapshot = {
                "timestamp": datetime.now(timezone.utc).replace(microsecond=0).isoformat(),
                "reboot_risk": analysis["next_reboot_risk"],
                "reboot_count_24h": analysis["reboot_count_24h"],
                "score": report.score,
                "causes": analysis["probable_causes"],
            }
            snapshots.append(snapshot)
            snap_file = watch_dir / f"{snapshot['timestamp'].replace(':', '-')}.json"
            snap_file.write_text(json.dumps(snapshot, indent=2), encoding="utf-8")
            print(
                f"[{snapshot['timestamp']}] risque={snapshot['reboot_risk']}% "
                f"reboots_24h={snapshot['reboot_count_24h']}"
            )
            time.sleep(interval_minutes * 60)
    except KeyboardInterrupt:
        print("\nArret demande, generation du rapport...")

    final = _build_final_report(snapshots, analyze_last_24h(runner))
    report_path = watch_dir / "reboot-monitor-final.json"
    report_path.write_text(json.dumps(final, indent=2, ensure_ascii=False), encoding="utf-8")

    from core.models import Finding, ModuleResult, Report, Severity

    results = [
        ModuleResult(
            "reboot-monitor",
            "ok",
            100 - final.get("max_risk_observed", 0),
            [
                Finding(
                    Severity.INFO if final.get("max_risk_observed", 0) < 50 else Severity.WARNING,
                    "Rapport reboot 24h",
                    json.dumps(final.get("probable_causes", []), ensure_ascii=False),
                    final.get("recommendation", "Surveiller les indicateurs."),
                )
            ],
            final,
        )
    ]
    full_report = Report(
        str(engine.build_context().get("version", "0.4.0")),
        str(final["analyzed_at"]),
        {"hostname": "reboot-monitor"},
        results[0].score,
        results,
    )
    output = write_report_bundle(full_report, Path(reports_dir))
    print(f"Rapport detaille: {report_path}")
    print(f"Rapport standard: {output}")
    return 0


def render_reboot_report_text(analysis: dict[str, Any]) -> str:
    """Render 24h reboot analysis as plain text."""

    lines = [
        "OBIORA REBOOT MONITOR - RAPPORT 24H",
        f"Analyse: {analysis.get('analyzed_at')}",
        f"Reboots detectes (24h): {analysis.get('reboot_count_24h', 0)}",
        f"Risque reboot imminent: {analysis.get('next_reboot_risk', {}).get('percent', 0)}%",
        "",
        "Causes probables:",
    ]
    for cause in analysis.get("probable_causes", []):
        lines.append(f"  - [{cause.get('severity')}] {cause.get('title')}: {cause.get('detail')}")
    lines.append("")
    lines.append("Evenements journal (extraits):")
    for event in analysis.get("reboot_events", [])[:10]:
        lines.append(f"  {event.get('time', '?')} {event.get('message', '')[:120]}")
    return "\n".join(lines) + "\n"


def _parse_journal_events(output: str) -> list[dict[str, str]]:
    events: list[dict[str, str]] = []
    for line in output.splitlines():
        if not line.strip():
            continue
        parts = line.split(maxsplit=2)
        if len(parts) >= 3:
            events.append({"time": f"{parts[0]} {parts[1]}", "message": parts[2]})
        else:
            events.append({"time": "", "message": line})
    return events


def _parse_boot_list(output: str) -> list[dict[str, str]]:
    boots: list[dict[str, str]] = []
    for line in output.splitlines():
        if line.strip().startswith("-"):
            boots.append({"raw": line.strip()})
    return boots


def _infer_causes(events: list[dict[str, str]]) -> list[dict[str, str]]:
    causes: list[dict[str, str]] = []
    text = "\n".join(event.get("message", "") for event in events).lower()
    checks = [
        ("oom", "CRITICAL", "Out Of Memory", "Le kernel a tue des processus par manque de RAM."),
        ("panic", "CRITICAL", "Kernel panic", "Crash kernel detecte dans le journal."),
        ("watchdog", "CRITICAL", "Watchdog", "Reboot force par watchdog (blocage systeme)."),
        ("reboot required", "WARNING", "Mise a jour", "Mise a jour kernel/systeme necessitant un reboot."),
        ("systemd-shutdown", "INFO", "Arret planifie", "Shutdown initie par systemd."),
        ("power", "WARNING", "Alimentation", "Evenement lie a l'alimentation."),
    ]
    for keyword, severity, title, detail in checks:
        if keyword in text:
            causes.append({"severity": severity, "title": title, "detail": detail})
    if not causes:
        causes.append(
            {
                "severity": "INFO",
                "title": "Aucune cause critique",
                "detail": "Aucun signal reboot critique dans les 24 dernieres heures.",
            }
        )
    return causes


def _estimate_next_reboot(runner: CommandRunner) -> dict[str, Any]:
    """Estimate imminent reboot risk."""

    risk = 0
    reasons: list[str] = []
    if Path("/var/run/reboot-required").exists():
        risk += 50
        reasons.append("Fichier /var/run/reboot-required present")
    oom = runner.run(
        ["journalctl", "--since", "1 hour ago", "-g", "oom|Out of memory", "--no-pager", "-n", "5"],
        timeout_seconds=10,
    )
    if oom.ok and oom.stdout.strip():
        risk += 30
        reasons.append("OOM dans la derniere heure")
    meminfo = Path("/proc/meminfo")
    if meminfo.exists():
        content = meminfo.read_text(encoding="utf-8")
        avail = 0
        total = 0
        for line in content.splitlines():
            if line.startswith("MemAvailable:"):
                avail = int(line.split()[1])
            if line.startswith("MemTotal:"):
                total = int(line.split()[1])
        if total and avail / total < 0.05:
            risk += 20
            reasons.append("RAM disponible < 5%")
    return {"percent": min(100, risk), "reasons": reasons}


def _build_final_report(
    snapshots: list[dict[str, Any]],
    analysis: dict[str, Any],
) -> dict[str, Any]:
    risks = [snap.get("reboot_risk", {}).get("percent", 0) for snap in snapshots]
    max_risk = max(risks) if risks else analysis.get("next_reboot_risk", {}).get("percent", 0)
    return {
        **analysis,
        "snapshots_count": len(snapshots),
        "max_risk_observed": max_risk,
        "snapshots": snapshots[-20:],
        "recommendation": (
            "Planifier un reboot en maintenance."
            if max_risk >= 50
            else "Surveillance continue recommandee."
            if max_risk >= 25
            else "Aucun reboot imminent detecte."
        ),
    }
