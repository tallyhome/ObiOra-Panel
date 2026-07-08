"""Minimal read-only REST API for Obiora Doctor reports."""

from __future__ import annotations

import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from core.history import list_reports


class ReportAPIHandler(BaseHTTPRequestHandler):
    """Serve report JSON over HTTP."""

    reports_dir: Path = Path("reports")

    def do_GET(self) -> None:  # noqa: N802
        """Handle GET requests."""

        path = urlparse(self.path).path
        if path in {"/", "/health"}:
            self._json_response(200, {"status": "ok", "service": "obiora-doctor"})
            return
        if path == "/reports":
            self._json_response(200, {"reports": list_reports(self.reports_dir)})
            return
        if path == "/reports/latest":
            reports = list_reports(self.reports_dir)
            if not reports:
                self._json_response(404, {"error": "no reports"})
                return
            latest = Path(reports[0]["path"]) / "report.json"
            self._file_json(latest)
            return
        if path.startswith("/reports/"):
            folder = path.removeprefix("/reports/").strip("/")
            report_file = self.reports_dir / folder / "report.json"
            if report_file.exists():
                self._file_json(report_file)
                return
        self._json_response(404, {"error": "not found"})

    def log_message(self, format: str, *args: Any) -> None:
        """Suppress default access logs."""

    def _file_json(self, path: Path) -> None:
        """Send a JSON file as response."""

        with path.open(encoding="utf-8") as handle:
            data = json.load(handle)
        self._json_response(200, data)

    def _json_response(self, status: int, payload: dict[str, Any]) -> None:
        """Send a JSON response."""

        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


def serve_api(host: str, port: int, reports_dir: Path) -> None:
    """Start the read-only report API server."""

    handler = type("Handler", (ReportAPIHandler,), {"reports_dir": reports_dir})
    server = ThreadingHTTPServer((host, port), handler)
    print(f"API Obiora Doctor: http://{host}:{port}")
    print("Endpoints: /health, /reports, /reports/latest, /reports/<folder>")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nAPI arretee.")
