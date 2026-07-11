"""HTML report export with embedded SVG charts."""

from __future__ import annotations

import html
import json
from pathlib import Path
from typing import Any


def export_html(report: dict[str, Any], path: Path) -> Path:
    series = report.get("metrics", {})
    diagnosis = report.get("diagnosis", {})
    blackbox = report.get("blackbox", {})

    content = f"""<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Crash Hunter — {html.escape(report.get('report_id', ''))}</title>
<style>
body {{ font-family: system-ui, sans-serif; margin: 2rem; background: #0f172a; color: #e2e8f0; }}
h1, h2 {{ color: #38bdf8; }}
.card {{ background: #1e293b; border-radius: 8px; padding: 1rem; margin: 1rem 0; }}
.verdict {{ font-size: 1.5rem; color: #fbbf24; }}
.confidence {{ color: #4ade80; }}
table {{ border-collapse: collapse; width: 100%; }}
th, td {{ border: 1px solid #334155; padding: 0.5rem; text-align: left; }}
th {{ background: #334155; }}
svg {{ background: #0f172a; border: 1px solid #334155; border-radius: 4px; }}
.evidence {{ font-family: monospace; font-size: 0.85rem; color: #94a3b8; }}
</style>
</head>
<body>
<h1>Crash Hunter — Black Box Flight Recorder</h1>
<div class="card">
  <p><strong>Report:</strong> {html.escape(report.get('report_id', ''))}</p>
  <p><strong>Host:</strong> {html.escape(report.get('hostname', ''))}</p>
  <p><strong>Generated:</strong> {html.escape(report.get('generated_at', ''))}</p>
  <p class="verdict">Verdict: {html.escape(diagnosis.get('verdict', 'N/A'))}</p>
  <p class="confidence">Confidence: {diagnosis.get('confidence', 0):.0%}</p>
  <p>{html.escape(diagnosis.get('summary', ''))}</p>
</div>

<h2>Causal Correlation</h2>
<div class="card evidence">
<pre>{html.escape(report.get("causal_correlation", {}).get("story_text", ""))}</pre>
</div>

<h2>Recommendations</h2>
{''.join(_recommendation_card(r) for r in report.get("recommendations", []))}

<h2>Reboot Classification</h2>
<div class="card">
<p><strong>Type:</strong> {html.escape(str(report.get("reboot_classification", {}).get("reboot_type", "unknown")))}</p>
<p><strong>Confidence:</strong> {report.get("reboot_classification", {}).get("confidence", 0):.0%}</p>
</div>

<h2>Chronological Timeline</h2>
<div class="card evidence">
{''.join(f'<div>{html.escape(line)}</div>' for line in report.get("chronological_report", {}).get("narrative", []))}
<p><strong>Root cause:</strong> {html.escape(str(report.get("chronological_report", {}).get("probable_root_cause", "Unknown")))}</p>
</div>

<h2>Similar Past Crashes</h2>
<div class="card">
<table>
<tr><th>Confidence</th><th>Report</th><th>Root Cause</th></tr>
{''.join(_similar_row(s) for s in report.get("similar_crashes", [])[:5])}
</table>
</div>

<h2>Version Signature</h2>
<div class="card">
{''.join(f'<p><strong>{html.escape(k)}:</strong> {html.escape(str(v)[:200])}</p>' for k, v in report.get("version_signature", {}).items())}
</div>

<h2>Timeline — Last 60 Minutes</h2>
<div class="card">
{_svg_chart('CPU %', series.get('cpu', []), '#38bdf8')}
{_svg_chart('Memory Available (kB)', series.get('memory', []), '#4ade80')}
{_svg_chart('Load Average (1m)', series.get('load', []), '#f472b6')}
{_svg_chart('Blocked Tasks', series.get('blocked', []), '#fb923c')}
{_svg_chart('TCP Established', series.get('network', []), '#a78bfa')}
{_svg_chart('VM Count', series.get('vms', []), '#fbbf24')}
</div>

<h2>Findings</h2>
{''.join(_finding_card(f) for f in diagnosis.get('findings', []))}

<h2>Top 20 Suspicious Events</h2>
<div class="card">
<table>
<tr><th>Probability</th><th>Time</th><th>Event</th><th>Detail</th></tr>
{''.join(_event_row(e) for e in diagnosis.get('top_suspicious_events', [])[:20])}
</table>
</div>

<h2>Kernel Messages</h2>
<div class="card evidence">
{''.join(f'<div>{html.escape(line[:300])}</div>' for line in blackbox.get('kernel_events', [])[-40:])}
</div>

<h2>Virtualizor / VM Activity</h2>
<div class="card evidence">
{''.join(f'<div>{html.escape(line[:300])}</div>' for line in blackbox.get('vm_events', [])[-20:])}
</div>

<h2>Systemd Activity</h2>
<div class="card evidence">
{''.join(f'<div>{html.escape(line[:300])}</div>' for line in blackbox.get('systemd_events', [])[-30:])}
</div>

<footer><p>Crash Hunter — ObiOra Doctor precursor — Black Box Flight Recorder</p></footer>
</body>
</html>"""

    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    return path


def _svg_chart(title: str, data: list[dict[str, Any]], color: str) -> str:
    if not data:
        return f"<p>{html.escape(title)}: no data</p>"
    values = [float(d.get("v", 0)) for d in data]
    max_v = max(values) or 1
    width, height = 600, 80
    points = []
    for i, v in enumerate(values):
        x = i * width / max(len(values) - 1, 1)
        y = height - (v / max_v * (height - 10))
        points.append(f"{x:.1f},{y:.1f}")
    polyline = " ".join(points)
    return f"""
<h3>{html.escape(title)}</h3>
<svg width="{width}" height="{height + 20}" viewBox="0 0 {width} {height + 20}">
  <polyline fill="none" stroke="{color}" stroke-width="2" points="{polyline}"/>
  <text x="4" y="12" fill="#94a3b8" font-size="10">max={max_v:.1f}</text>
</svg>"""


def _finding_card(finding: dict[str, Any]) -> str:
    evidence = "".join(
        f'<div class="evidence">{html.escape(e[:250])}</div>'
        for e in finding.get("evidence", [])[:5]
    )
    return f"""
<div class="card">
  <h3>{html.escape(finding.get('title', ''))} — {finding.get('confidence', 0):.0%}</h3>
  <p>{html.escape(finding.get('description', ''))}</p>
  {evidence}
</div>"""


def _recommendation_card(rec: dict[str, Any]) -> str:
    actions = "".join(f"<li>{html.escape(a)}</li>" for a in rec.get("actions", []))
    return f"""
<div class="card">
  <h3>{html.escape(rec.get('title', ''))} ({rec.get('confidence', 0):.0%})</h3>
  <ul>{actions}</ul>
</div>"""


def _similar_row(sim: dict[str, Any]) -> str:
    return (
        f"<tr><td>{sim.get('confidence', 0):.0%}</td>"
        f"<td>{html.escape(sim.get('report_id', ''))}</td>"
        f"<td>{html.escape(sim.get('probable_root_cause', ''))}</td></tr>"
    )


def _event_row(event: dict[str, Any]) -> str:
    return (
        f"<tr><td>{event.get('probability', 0):.0%}</td>"
        f"<td>{html.escape(str(event.get('timestamp', '')))}</td>"
        f"<td>{html.escape(event.get('event', ''))}</td>"
        f"<td>{html.escape(str(event.get('detail', ''))[:200])}</td></tr>"
    )
