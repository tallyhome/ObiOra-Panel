"""Génération de rapports HTML, JSON et PDF post-crash."""

from __future__ import annotations

import base64
import json
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from crash_analyzer.storage import MetricsStorage, utc_now_iso


def _format_ts(ts: float) -> str:
    return datetime.fromtimestamp(ts, tz=timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")


class ReportGenerator:
    """Construit les rapports détaillés des 60 dernières minutes."""

    def __init__(self, reports_dir: str, history_minutes: int = 60) -> None:
        self.reports_dir = Path(reports_dir)
        self.history_minutes = history_minutes

    def generate(
        self,
        storage: MetricsStorage,
        hostname: str,
        trigger_event: dict[str, Any] | None = None,
        extras: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        """Génère HTML, JSON et PDF (si reportlab disponible)."""
        since = time.time() - (self.history_minutes * 60)
        metrics = storage.metrics_since(since)
        events = storage.events_since(since)

        report_id = datetime.now(timezone.utc).strftime("%Y-%m-%d_%H-%M-%S")
        out_dir = self.reports_dir / report_id
        out_dir.mkdir(parents=True, exist_ok=True)

        payload: dict[str, Any] = {
            "report_id": report_id,
            "generated_at": utc_now_iso(),
            "hostname": hostname,
            "history_minutes": self.history_minutes,
            "trigger_event": trigger_event,
            "metrics_count": len(metrics),
            "events_count": len(events),
            "events": events,
            "metrics_summary": self._summarize_metrics(metrics),
            "metrics": metrics[-500:],
            "boot_journal": (extras or {}).get("boot_journal"),
            "hardware": (extras or {}).get("hardware"),
        }

        json_path = out_dir / "report.json"
        json_path.write_text(json.dumps(payload, indent=2), encoding="utf-8")

        html = self._build_html(payload)
        html_path = out_dir / "report.html"
        html_path.write_text(html, encoding="utf-8")

        pdf_path = out_dir / "report.pdf"
        pdf_generated = self._try_pdf(html, pdf_path)

        return {
            "report_id": report_id,
            "directory": str(out_dir),
            "json_path": str(json_path),
            "html_path": str(html_path),
            "pdf_path": str(pdf_path) if pdf_generated else None,
            "payload": payload,
            "pdf_base64": base64.b64encode(pdf_path.read_bytes()).decode("ascii") if pdf_generated else None,
        }

    def _summarize_metrics(self, metrics: list[dict[str, Any]]) -> dict[str, Any]:
        summary: dict[str, Any] = {}
        by_collector: dict[str, list[dict[str, Any]]] = {}
        for m in metrics:
            by_collector.setdefault(m["collector"], []).append(m["payload"])

        cpu_vals = [p.get("usage_percent", 0) for p in by_collector.get("cpu", [])]
        mem_vals = [p.get("used_percent", 0) for p in by_collector.get("memory", [])]
        if cpu_vals:
            summary["cpu"] = {"avg": round(sum(cpu_vals) / len(cpu_vals), 2), "max": max(cpu_vals)}
        if mem_vals:
            summary["memory"] = {"avg": round(sum(mem_vals) / len(mem_vals), 2), "max": max(mem_vals)}
        summary["collectors"] = list(by_collector.keys())
        return summary

    def _build_html(self, payload: dict[str, Any]) -> str:
        events_rows = "".join(
            f"<tr><td>{_format_ts(e['detected_at'])}</td><td>{e['severity']}</td>"
            f"<td>{e['event_type']}</td><td>{e['title']}</td><td>{e['details'][:200]}</td></tr>"
            for e in payload.get("events", [])
        )
        trigger = payload.get("trigger_event") or {}
        boot = payload.get("boot_journal") or {}
        boot_section = ""
        if boot:
            boot_section = f"""<h2>Journal boot précédent (journalctl -b -1)</h2>
<p>Journal persistant: {'oui' if boot.get('persistent_journal') else 'non'} | Boots: {boot.get('boots_count', 0)}</p>
<pre>{(boot.get('previous_boot_errors') or boot.get('previous_boot_log_tail') or '')[:6000]}</pre>"""
        return f"""<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>ObiOra Crash Analyzer — {payload['hostname']}</title>
<style>
body {{ font-family: system-ui, sans-serif; margin: 2rem; color: #1a1a2e; }}
h1 {{ color: #c0392b; }}
table {{ border-collapse: collapse; width: 100%; margin-top: 1rem; }}
th, td {{ border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 0.9rem; }}
th {{ background: #2c3e50; color: #fff; }}
.critical {{ color: #c0392b; font-weight: bold; }}
.meta {{ background: #f8f9fa; padding: 1rem; border-radius: 8px; }}
</style>
</head>
<body>
<h1>Rapport Crash Analyzer</h1>
<div class="meta">
<p><strong>Hôte :</strong> {payload['hostname']}</p>
<p><strong>Généré :</strong> {payload['generated_at']}</p>
<p><strong>Fenêtre :</strong> {payload['history_minutes']} minutes</p>
<p><strong>Déclencheur :</strong> {trigger.get('title', 'N/A')}</p>
<p><strong>Métriques :</strong> {payload['metrics_count']} | <strong>Événements :</strong> {payload['events_count']}</p>
</div>
<h2>Événements critiques</h2>
<table>
<thead><tr><th>Date</th><th>Sévérité</th><th>Type</th><th>Titre</th><th>Détails</th></tr></thead>
<tbody>{events_rows or '<tr><td colspan="5">Aucun événement</td></tr>'}</tbody>
</table>
<h2>Résumé métriques</h2>
<pre>{json.dumps(payload.get('metrics_summary', {}), indent=2)}</pre>
{boot_section}
<footer><p>ObiOra Crash Analyzer — rapport automatique post-incident</p></footer>
</body>
</html>"""

    def _try_pdf(self, html: str, pdf_path: Path) -> bool:
        try:
            from reportlab.lib.pagesizes import A4
            from reportlab.pdfgen import canvas

            c = canvas.Canvas(str(pdf_path), pagesize=A4)
            width, height = A4
            y = height - 40
            c.setFont("Helvetica-Bold", 14)
            c.drawString(40, y, "ObiOra Crash Analyzer Report")
            y -= 30
            c.setFont("Helvetica", 9)
            for line in html.replace("<", " <").splitlines():
                text = line.strip()[:120]
                if not text or text.startswith("<!") or text.startswith("<style"):
                    continue
                if y < 60:
                    c.showPage()
                    y = height - 40
                    c.setFont("Helvetica", 9)
                c.drawString(40, y, text[:110])
                y -= 12
            c.save()
            return True
        except ImportError:
            return False
