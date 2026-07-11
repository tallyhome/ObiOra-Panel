"""PDF report export via ReportLab."""

from __future__ import annotations

from pathlib import Path
from typing import Any

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


def export_pdf(report: dict[str, Any], path: Path) -> Path:
    path.parent.mkdir(parents=True, exist_ok=True)
    doc = SimpleDocTemplate(str(path), pagesize=A4)
    styles = getSampleStyleSheet()
    title_style = ParagraphStyle(
        "Title2",
        parent=styles["Heading1"],
        textColor=colors.HexColor("#1e40af"),
    )
    story: list[Any] = []

    diagnosis = report.get("diagnosis", {})
    story.append(Paragraph(f"Crash Hunter Report — {report.get('report_id', '')}", title_style))
    story.append(Spacer(1, 0.3 * cm))
    story.append(Paragraph(f"Host: {report.get('hostname', '')}", styles["Normal"]))
    story.append(Paragraph(f"Generated: {report.get('generated_at', '')}", styles["Normal"]))
    story.append(Spacer(1, 0.5 * cm))
    story.append(
        Paragraph(
            f"<b>Verdict:</b> {diagnosis.get('verdict', 'N/A')} "
            f"({diagnosis.get('confidence', 0):.0%})",
            styles["Heading2"],
        )
    )
    story.append(Paragraph(diagnosis.get("summary", ""), styles["Normal"]))
    story.append(Spacer(1, 0.5 * cm))

    blackbox = report.get("blackbox", {})
    story.append(Paragraph("Black Box Flight Recorder", styles["Heading2"]))
    story.append(
        Paragraph(
            f"Snapshots: {blackbox.get('snapshot_count', 0)} — "
            f"Duration: {blackbox.get('duration_minutes', 0)} min",
            styles["Normal"],
        )
    )
    story.append(Spacer(1, 0.3 * cm))

    findings = diagnosis.get("findings", [])
    if findings:
        story.append(Paragraph("Findings", styles["Heading2"]))
        for f in findings[:10]:
            story.append(
                Paragraph(
                    f"<b>{f.get('title', '')}</b> ({f.get('confidence', 0):.0%})",
                    styles["Heading3"],
                )
            )
            story.append(Paragraph(f.get("description", ""), styles["Normal"]))
            for ev in f.get("evidence", [])[:3]:
                story.append(Paragraph(f"• {ev[:200]}", styles["Code"]))
            story.append(Spacer(1, 0.2 * cm))

    suspicious = diagnosis.get("top_suspicious_events", [])[:20]
    if suspicious:
        story.append(Paragraph("Top Suspicious Events", styles["Heading2"]))
        table_data = [["Prob.", "Time", "Event", "Detail"]]
        for e in suspicious:
            table_data.append(
                [
                    f"{e.get('probability', 0):.0%}",
                    str(e.get("timestamp", ""))[:19],
                    str(e.get("event", ""))[:20],
                    str(e.get("detail", ""))[:60],
                ]
            )
        table = Table(table_data, colWidths=[1.5 * cm, 3.5 * cm, 3 * cm, 8 * cm])
        table.setStyle(
            TableStyle(
                [
                    ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#334155")),
                    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                    ("GRID", (0, 0), (-1, -1), 0.5, colors.grey),
                    ("FONTSIZE", (0, 0), (-1, -1), 8),
                ]
            )
        )
        story.append(table)

    doc.build(story)
    return path
