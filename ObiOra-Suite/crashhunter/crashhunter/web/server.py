"""CrashHunter Web Dashboard — lightweight HTTP UI."""

from __future__ import annotations

import json
import logging
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

from crashhunter.config.settings import Settings
from crashhunter.storage.incident_store import IncidentStore
from crashhunter.storage.ring_buffer import RingBuffer
from crashhunter.storage.state_store import StateStore

logger = logging.getLogger("crashhunter.web")

DASHBOARD_HTML = Path(__file__).parent / "dashboard.html"


class WebDashboard:
    """Serve dashboard + JSON API for incidents, timeline, metrics."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self._server: ThreadingHTTPServer | None = None

    def run(self) -> int:
        host = self.settings.web.listen_host
        port = self.settings.web.listen_port
        handler = _make_dashboard_handler(self)
        self._server = ThreadingHTTPServer((host, port), handler)
        logger.info("CrashHunter Web UI on http://%s:%s", host, port)
        try:
            self._server.serve_forever()
        except KeyboardInterrupt:
            pass
        finally:
            if self._server:
                self._server.server_close()
        return 0

    def api_status(self) -> dict[str, Any]:
        state = StateStore(
            self.settings.boot_id_file,
            self.settings.last_uptime_file,
            self.settings.last_clock_file,
        )
        ring = RingBuffer(self.settings.ring_capacity, self.settings.effective_ring_dir)
        ring.load_from_disk()
        incidents = IncidentStore(self.settings.incident_dir).list_incidents()
        reports = sorted(
            self.settings.reports_dir.glob("CrashReport_*"),
            key=lambda p: p.stat().st_mtime,
            reverse=True,
        )[:10]
        return {
            "hostname": self.settings.hostname,
            "uptime": state.current_uptime(),
            "boot_id": state.current_boot_id(),
            "ring_count": ring.count,
            "ring_capacity": self.settings.ring_capacity,
            "incidents": incidents,
            "reports": [p.name for p in reports],
        }

    def api_incidents(self) -> list[dict[str, Any]]:
        store = IncidentStore(self.settings.incident_dir)
        result = []
        for inc in store.list_incidents()[:20]:
            result.append({"id": inc, "snapshots": store.count(inc)})
        return result

    def api_timeline(self) -> list[dict[str, Any]]:
        path = self.settings.timeline_file
        if not path.exists():
            return []
        events = []
        for line in path.read_text(encoding="utf-8").splitlines()[-200:]:
            try:
                events.append(json.loads(line))
            except json.JSONDecodeError:
                continue
        return events

    def api_report_summary(self, report_id: str | None = None) -> dict[str, Any]:
        reports = sorted(
            self.settings.reports_dir.glob("CrashReport_*"),
            key=lambda p: p.stat().st_mtime,
            reverse=True,
        )
        if not reports:
            return {"error": "no reports"}
        target = reports[0]
        if report_id:
            for r in reports:
                if report_id in r.name:
                    target = r
                    break
        json_files = list(target.glob("CrashReport_*.json"))
        if not json_files:
            return {"error": "no json"}
        return json.loads(json_files[0].read_text(encoding="utf-8"))


def _make_dashboard_handler(dashboard: WebDashboard) -> type[BaseHTTPRequestHandler]:
    class Handler(BaseHTTPRequestHandler):
        def log_message(self, fmt: str, *args: object) -> None:
            logger.debug(fmt, *args)

        def _send_json(self, code: int, data: Any) -> None:
            payload = json.dumps(data, ensure_ascii=False, default=str).encode("utf-8")
            self.send_response(code)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

        def _send_html(self, content: str) -> None:
            payload = content.encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

        def do_GET(self) -> None:
            parsed = urlparse(self.path)
            path = parsed.path
            qs = parse_qs(parsed.query)

            if path in ("/", "/dashboard"):
                if DASHBOARD_HTML.exists():
                    self._send_html(DASHBOARD_HTML.read_text(encoding="utf-8"))
                else:
                    self._send_html("<h1>CrashHunter Dashboard</h1><p>dashboard.html missing</p>")
            elif path == "/api/status":
                self._send_json(200, dashboard.api_status())
            elif path == "/api/incidents":
                self._send_json(200, dashboard.api_incidents())
            elif path == "/api/timeline":
                self._send_json(200, dashboard.api_timeline())
            elif path == "/api/report":
                rid = qs.get("id", [None])[0]
                self._send_json(200, dashboard.api_report_summary(rid))
            else:
                self._send_json(404, {"error": "not found"})

    return Handler
