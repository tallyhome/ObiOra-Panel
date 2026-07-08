"""Secure local web dashboard for Obiora Doctor.

Security model for production Virtualizor dedicated servers:
- Binds to 127.0.0.1 ONLY (never 0.0.0.0 by default)
- Token authentication required for all endpoints
- Read-only by default; scan requires explicit POST + token
- Rate limiting on scan requests
- No shell execution from web parameters
- Security headers (CSP, X-Frame-Options, etc.)
- Access via SSH tunnel recommended

Example remote access:
  ssh -L 8766:127.0.0.1:8766 root@serveur
  Then open http://127.0.0.1:8766 in local browser
"""

from __future__ import annotations

import hashlib
import hmac
import json
import secrets
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

from core.engine import DiagnosticEngine
from core.history import list_reports
from core.reports import write_report_bundle

TOKEN_FILE = Path(__file__).resolve().parents[1] / "config" / "web.token"
RATE_LIMIT_SECONDS = 60


class SecureWebHandler(BaseHTTPRequestHandler):
    """Secure web handler with token auth and rate limiting."""

    reports_dir: Path = Path("reports")
    engine: DiagnosticEngine | None = None
    auth_token: str = ""
    last_scan_at: float = 0.0
    scan_lock: threading.Lock = threading.Lock()

    def do_GET(self) -> None:  # noqa: N802
        if not self._check_auth():
            return
        path = urlparse(self.path).path
        if path in {"/", "/dashboard"}:
            self._html_response(_dashboard_html())
            return
        if path == "/api/health":
            self._json_response(200, {"status": "ok", "secure": True})
            return
        if path == "/api/reports":
            self._json_response(200, {"reports": list_reports(self.reports_dir)})
            return
        if path == "/api/reports/latest":
            reports = list_reports(self.reports_dir)
            if not reports:
                self._json_response(404, {"error": "no reports"})
                return
            latest = Path(reports[0]["path"]) / "report.json"
            self._send_json_file(latest)
            return
        self._json_response(404, {"error": "not found"})

    def do_POST(self) -> None:  # noqa: N802
        if not self._check_auth():
            return
        path = urlparse(self.path).path
        if path == "/api/scan":
            self._handle_scan()
            return
        self._json_response(404, {"error": "not found"})

    def log_message(self, format: str, *args: Any) -> None:
        """Log to stderr only."""

    def _check_auth(self) -> bool:
        """Verify bearer token from Authorization header or query param."""

        auth = self.headers.get("Authorization", "")
        token = ""
        if auth.startswith("Bearer "):
            token = auth[7:].strip()
        if not token:
            query = parse_qs(urlparse(self.path).query)
            token = query.get("token", [""])[0]
        if not token or not hmac.compare_digest(token, self.auth_token):
            self._json_response(401, {"error": "unauthorized"})
            return False
        return True

    def _handle_scan(self) -> None:
        """Trigger a read-only diagnostic scan with rate limiting."""

        now = time.monotonic()
        with self.scan_lock:
            if now - self.last_scan_at < RATE_LIMIT_SECONDS:
                self._json_response(
                    429,
                    {"error": f"rate limit: wait {RATE_LIMIT_SECONDS}s between scans"},
                )
                return
            self.last_scan_at = now

        if self.engine is None:
            self._json_response(500, {"error": "engine not configured"})
            return

        report = self.engine.run()
        output_dir = write_report_bundle(report, self.reports_dir)
        self._json_response(
            200,
            {
                "status": "ok",
                "score": report.score,
                "report_path": str(output_dir),
            },
        )

    def _send_json_file(self, path: Path) -> None:
        with path.open(encoding="utf-8") as handle:
            data = json.load(handle)
        self._json_response(200, data)

    def _json_response(self, status: int, payload: dict[str, Any]) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self._security_headers()
        self.end_headers()
        self.wfile.write(body)

    def _html_response(self, html: str) -> None:
        body = html.encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self._security_headers()
        self.end_headers()
        self.wfile.write(body)

    def _security_headers(self) -> None:
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("X-Frame-Options", "DENY")
        self.send_header("X-XSS-Protection", "1; mode=block")
        self.send_header("Referrer-Policy", "no-referrer")
        self.send_header(
            "Content-Security-Policy",
            "default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'",
        )
        self.send_header("Cache-Control", "no-store")


def get_or_create_token() -> str:
    """Load or generate the web authentication token."""

    if TOKEN_FILE.exists():
        return TOKEN_FILE.read_text(encoding="utf-8").strip()
    token = secrets.token_urlsafe(32)
    TOKEN_FILE.parent.mkdir(parents=True, exist_ok=True)
    TOKEN_FILE.write_text(token + "\n", encoding="utf-8")
    TOKEN_FILE.chmod(0o600)
    return token


def serve_web(
    host: str,
    port: int,
    reports_dir: Path,
    engine: DiagnosticEngine,
) -> None:
    """Start the secure web dashboard.

    Parameters:
        host: Bind address. Must be 127.0.0.1 for production safety.
        port: TCP port.
        reports_dir: Reports directory.
        engine: Diagnostic engine for scan requests.
    """

    if host not in {"127.0.0.1", "::1", "localhost"}:
        print("SECURITE: refus de binder sur une interface publique.")
        print("Utilisez 127.0.0.1 et un tunnel SSH.")
        host = "127.0.0.1"

    token = get_or_create_token()
    handler = type(
        "Handler",
        (SecureWebHandler,),
        {
            "reports_dir": reports_dir,
            "engine": engine,
            "auth_token": token,
            "scan_lock": threading.Lock(),
        },
    )
    server = ThreadingHTTPServer((host, port), handler)
    print(f"Interface web securisee: http://{host}:{port}")
    print(f"Token (gardez-le secret): {token}")
    print(f"Fichier token: {TOKEN_FILE}")
    print("Acces distant: ssh -L {port}:127.0.0.1:{port} root@serveur".format(port=port))
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nInterface web arretee.")


def _dashboard_html() -> str:
    """Return self-contained dashboard HTML."""

    return """<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Obiora Doctor</title>
  <style>
    body { background:#0f172a; color:#e5e7eb; font-family:system-ui,sans-serif;
           margin:0; padding:24px; max-width:900px; }
    h1 { color:#38bdf8; }
    .card { background:#111827; border:1px solid #1f2937; border-radius:12px;
            padding:20px; margin:16px 0; }
    input, button { padding:10px; border-radius:8px; border:1px solid #374151;
                    background:#1f2937; color:#e5e7eb; margin:4px 0; width:100%;
                    box-sizing:border-box; }
    button { background:#2563eb; cursor:pointer; font-weight:600; }
    button:hover { background:#1d4ed8; }
    .warn { color:#f59e0b; font-size:0.9em; }
    #score { font-size:3em; color:#22c55e; font-weight:700; }
    pre { background:#0b1220; padding:12px; border-radius:8px; overflow:auto;
          font-size:0.85em; }
  </style>
</head>
<body>
  <h1>Obiora Doctor</h1>
  <p class="warn">Interface securisee - localhost uniquement. Token requis.</p>

  <div class="card">
    <label>Token d'authentification</label>
    <input type="password" id="token" placeholder="Token depuis config/web.token">
  </div>

  <div class="card">
    <button onclick="loadLatest()">Charger dernier rapport</button>
    <button onclick="runScan()">Lancer un scan</button>
    <div id="score"></div>
    <pre id="output">En attente...</pre>
  </div>

  <script>
    function headers() {
      const token = document.getElementById('token').value;
      return { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' };
    }
    async function loadLatest() {
      const r = await fetch('/api/reports/latest', { headers: headers() });
      const data = await r.json();
      document.getElementById('score').textContent = data.score + ' %';
      document.getElementById('output').textContent = JSON.stringify(data, null, 2);
    }
    async function runScan() {
      document.getElementById('output').textContent = 'Scan en cours...';
      const r = await fetch('/api/scan', { method: 'POST', headers: headers() });
      const data = await r.json();
      document.getElementById('output').textContent = JSON.stringify(data, null, 2);
      if (data.score !== undefined) document.getElementById('score').textContent = data.score + ' %';
    }
  </script>
</body>
</html>"""
