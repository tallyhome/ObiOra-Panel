"""Push CrashHunter metrics, snapshots and reports to ObiOra Panel."""

from __future__ import annotations

import json
import logging
import urllib.error
import urllib.request
from typing import Any

logger = logging.getLogger("crashhunter.panel_bridge")


class PanelBridge:
    """Remote ingest client for ObiOra Panel CrashHunter API."""

    def __init__(
        self,
        panel_url: str,
        server_id: int,
        agent_token: str,
        enabled: bool = True,
        timeout_seconds: float = 10.0,
    ) -> None:
        self.panel_url = panel_url.rstrip("/")
        self.server_id = int(server_id)
        self.agent_token = agent_token
        self.enabled = enabled and bool(panel_url and agent_token)
        self.timeout_seconds = timeout_seconds
        self._last_push = 0.0

    def _post(self, path: str, payload: dict[str, Any]) -> bool:
        if not self.enabled:
            return False
        url = f"{self.panel_url}/api/v1/servers/{self.server_id}/{path}"
        data = json.dumps(payload, default=str).encode("utf-8")
        req = urllib.request.Request(
            url,
            data=data,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {self.agent_token}",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=self.timeout_seconds) as resp:
                return resp.status == 200
        except urllib.error.URLError as exc:
            logger.debug("Panel push failed %s: %s", path, exc)
            return False

    def push_metrics(self, snapshot: dict[str, Any], version: str) -> bool:
        metrics: dict[str, Any] = {}
        for key in (
            "system", "cpu", "memory", "disk", "network", "processes",
            "kernel", "virtualizor", "pressure", "blkmq", "qemu", "hardware",
        ):
            if key in snapshot and isinstance(snapshot[key], dict):
                metrics[key] = snapshot[key]

        payload = {
            "hostname": snapshot.get("hostname", ""),
            "timestamp_us": snapshot.get("timestamp_us", ""),
            "sampled_at": snapshot.get("timestamp_us", ""),
            "crashhunter_version": version,
            "ring_count": snapshot.get("ring_count"),
            "incident_mode": snapshot.get("incident_mode", False),
            "metrics": metrics,
            "events": snapshot.get("events", []),
        }
        return self._post("crash-hunter/metrics", payload)

    def push_witness(self, heartbeat: dict[str, Any]) -> bool:
        return self._post("crash-hunter/witness", heartbeat)

    def push_snapshots(self, snapshots: list[dict[str, Any]]) -> bool:
        if not snapshots:
            return False
        return self._post("crash-hunter/snapshots", {"snapshots": snapshots})

    def push_report(self, report: dict[str, Any], bundle_path: str | None = None) -> bool:
        return self._post("crash-hunter/reports", {
            "report_json": report,
            "report_id": report.get("report_id"),
            "bundle_path": bundle_path,
        })

    def push_incident(self, summary: dict[str, Any]) -> bool:
        return self._post("crash-hunter/incidents", summary)

    def should_push(self, now: float, interval: float) -> bool:
        return (now - self._last_push) >= interval

    def mark_pushed(self, now: float) -> None:
        self._last_push = now
