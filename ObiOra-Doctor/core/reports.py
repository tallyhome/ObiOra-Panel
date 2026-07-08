"""Report rendering for JSON, Markdown, HTML and text outputs."""

from __future__ import annotations

import html
import json
import zipfile
from pathlib import Path

from core.knowledge import enrich_report
from core.models import Finding, ModuleResult, Report, Severity
from core.redact import redact_report_dict, redact_text


def write_report_bundle(
    report: Report,
    reports_dir: Path,
    *,
    anonymize: bool = False,
) -> Path:
    """Write report files in a timestamped directory.

    Parameters:
        report: Complete report to render.
        reports_dir: Base reports directory.

    Returns:
        Path to the created report directory.

    Example:
        write_report_bundle(report, Path("reports"))
    """

    folder_name = report.generated_at.replace(":", "-").replace("+00:00", "Z")
    output_dir = reports_dir / folder_name
    output_dir.mkdir(parents=True, exist_ok=True)

    if anonymize:
        payload = redact_report_dict(report.to_dict())
        (output_dir / "report.json").write_text(
            json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )
        (output_dir / "report.md").write_text(
            redact_text(render_markdown(report)), encoding="utf-8"
        )
        (output_dir / "report.html").write_text(
            redact_text(render_html(report)), encoding="utf-8"
        )
        (output_dir / "report.txt").write_text(
            redact_text(render_text(report)), encoding="utf-8"
        )
    else:
        payload = enrich_report(report.to_dict())
        (output_dir / "report.json").write_text(
            json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )
        (output_dir / "report.md").write_text(render_markdown(report), encoding="utf-8")
        (output_dir / "report.html").write_text(render_html(report), encoding="utf-8")
        (output_dir / "report.txt").write_text(render_text(report), encoding="utf-8")

    return output_dir


def export_report_zip(report_dir: Path) -> Path:
    """Create a zip archive of a report directory.

    Parameters:
        report_dir: Path to report folder.

    Returns:
        Path to created zip file.
    """

    zip_path = report_dir.with_suffix(".zip")
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as archive:
        for file_path in report_dir.iterdir():
            if file_path.is_file():
                archive.write(file_path, arcname=file_path.name)
    return zip_path


def render_json(report: Report) -> str:
    """Render a report as pretty JSON."""

    return json.dumps(report.to_dict(), indent=2, ensure_ascii=False) + "\n"


def render_markdown(report: Report) -> str:
    """Render a report as Markdown."""

    lines = [
        "# Obiora Doctor Report",
        "",
        f"- Version: `{report.version}`",
        f"- Date: `{report.generated_at}`",
        f"- Host: `{report.host.get('hostname', 'unknown')}`",
        f"- Platform: `{report.host.get('platform', 'unknown')}`",
        f"- Health Score: **{report.score}%**",
        "",
        "## Modules",
        "",
    ]

    for result in report.results:
        lines.extend(_markdown_module(result))

    return "\n".join(lines).rstrip() + "\n"


def render_text(report: Report) -> str:
    """Render a report as plain text."""

    lines = [
        "OBIORA DOCTOR",
        f"Health Score: {report.score}%",
        f"Host: {report.host.get('hostname', 'unknown')}",
        f"Date: {report.generated_at}",
        "",
    ]

    for result in report.results:
        lines.append(f"[{result.module}] score={result.score}% status={result.status}")
        for finding in result.findings:
            lines.append(f"  {finding.level.value}: {finding.title}")
            lines.append(f"    {finding.details}")
            lines.append(f"    Recommendation: {finding.recommendation}")
        lines.append("")

    return "\n".join(lines).rstrip() + "\n"


def render_html(report: Report) -> str:
    """Render a report as standalone HTML."""

    cards = "\n".join(_html_module(result) for result in report.results)
    title = "Obiora Doctor Report"
    return f"""<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{title}</title>
  <style>
    body {{
      background: #0f172a;
      color: #e5e7eb;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 32px;
    }}
    .header, .card {{
      background: #111827;
      border: 1px solid #1f2937;
      border-radius: 14px;
      margin-bottom: 18px;
      padding: 20px;
    }}
    .score {{ color: #22c55e; font-size: 48px; font-weight: 700; }}
    .module-score {{ color: #38bdf8; font-weight: 700; }}
    .INFO {{ color: #22c55e; }}
    .WARNING {{ color: #f59e0b; }}
    .CRITICAL {{ color: #ef4444; }}
    code {{ color: #93c5fd; }}
  </style>
</head>
<body>
  <section class="header">
    <h1>Obiora Doctor</h1>
    <div class="score">{report.score}%</div>
    <p>Host: <code>{html.escape(str(report.host.get("hostname", "unknown")))}</code></p>
    <p>Date: <code>{html.escape(report.generated_at)}</code></p>
  </section>
  {cards}
</body>
</html>
"""


def _markdown_module(result: ModuleResult) -> list[str]:
    lines = [
        f"### {result.module}",
        "",
        f"- Status: `{result.status}`",
        f"- Score: **{result.score}%**",
        f"- Duration: `{result.duration_ms} ms`",
        "",
    ]
    for finding in result.findings:
        lines.extend(_markdown_finding(finding))
    return lines


def _markdown_finding(finding: Finding) -> list[str]:
    lines = [
        f"- `{finding.level.value}` **{finding.title}**",
        f"  - Details: {finding.details}",
        f"  - Recommendation: {finding.recommendation}",
    ]
    if finding.commands:
        lines.append(f"  - Verification: `{'; '.join(finding.commands)}`")
    return lines


def _html_module(result: ModuleResult) -> str:
    findings = "\n".join(_html_finding(finding) for finding in result.findings)
    return f"""<section class="card">
  <h2>{html.escape(result.module)} <span class="module-score">{result.score}%</span></h2>
  <p>Status: <code>{html.escape(result.status)}</code> - Duration: {result.duration_ms} ms</p>
  {findings}
</section>"""


def _html_finding(finding: Finding) -> str:
    level = finding.level.value
    commands = ""
    if finding.commands:
        command_text = html.escape("; ".join(finding.commands))
        commands = f"<p>Verification: <code>{command_text}</code></p>"
    return f"""<article>
  <h3 class="{level}">{level} - {html.escape(finding.title)}</h3>
  <p>{html.escape(finding.details)}</p>
  <p><strong>Recommendation:</strong> {html.escape(finding.recommendation)}</p>
  {commands}
</article>"""
