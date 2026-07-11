"""Witness HTTP receiver — runs on the VPS ObiOra."""

from __future__ import annotations

import json
import logging
import threading
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any
from urllib.parse import urlparse

from crashhunter.config.settings import Settings
from crashhunter.witness.monitor import WitnessMonitor
from crashhunter.witness.store import WitnessStore

logger = logging.getLogger("crashhunter.witness.receiver")


class WitnessReceiver:
    """HTTP server accepting heartbeats from dedicated servers."""

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.store = WitnessStore(settings.witness_data_dir)
        self.monitor = WitnessMonitor(settings, self.store)
        self._server: ThreadingHTTPServer | None = None

    def run(self) -> int:
        host = self.settings.witness.listen_host
        port = self.settings.witness.listen_port
        handler = _make_handler(self)
        self._server = ThreadingHTTPServer((host, port), handler)
        self.monitor.start()
        logger.info("Witness receiver listening on %s:%s", host, port)
        try:
            self._server.serve_forever()
        except KeyboardInterrupt:
            pass
        finally:
            self.monitor.stop()
            if self._server:
                self._server.server_close()
        return 0

    def handle_heartbeat(self, payload: dict[str, Any]) -> dict[str, Any]:
        payload["received_at"] = datetime.now(timezone.utc).isoformat()
        self.store.record_heartbeat(payload)
        return {"status": "ok", "host": payload.get("host")}

    def handle_status(self) -> dict[str, Any]:
        return {
            "hosts": self.monitor.check_all(),
            "events": self.store.get_events(limit=20),
        }


def _make_handler(receiver: WitnessReceiver) -> type[BaseHTTPRequestHandler]:
    class Handler(BaseHTTPRequestHandler):
        def log_message(self, fmt: str, *args: object) -> None:
            logger.debug(fmt, *args)

        def _auth_ok(self) -> bool:
            token = receiver.settings.witness.token
            if not token:
                return True
            auth = self.headers.get("Authorization", "")
            return auth == f"Bearer {token}"

        def _read_json(self) -> dict[str, Any]:
            length = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(length) if length else b"{}"
            return json.loads(body.decode("utf-8"))

        def _json_response(self, code: int, data: dict[str, Any]) -> None:
            payload = json.dumps(data).encode("utf-8")
            self.send_response(code)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

        def do_GET(self) -> None:
            if not self._auth_ok():
                self._json_response(401, {"error": "unauthorized"})
                return
            path = urlparse(self.path).path
            if path in ("/api/v1/witness/status", "/api/v1/witness/hosts"):
                self._json_response(200, receiver.handle_status())
            elif path == "/health":
                self._json_response(200, {"status": "ok"})
            else:
                self._json_response(404, {"error": "not found"})

        def do_POST(self) -> None:
            if not self._auth_ok():
                self._json_response(401, {"error": "unauthorized"})
                return
            path = urlparse(self.path).path
            if path == "/api/v1/witness/heartbeat":
                try:
                    payload = self._read_json()
                    result = receiver.handle_heartbeat(payload)
                    self._json_response(200, result)
                except json.JSONDecodeError:
                    self._json_response(400, {"error": "invalid json"})
            else:
                self._json_response(404, {"error": "not found"})

    return Handler
