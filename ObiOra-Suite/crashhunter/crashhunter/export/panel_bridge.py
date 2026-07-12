"""Push CrashHunter metrics, snapshots and reports to ObiOra Panel."""

from __future__ import annotations

import json
import logging
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

from crashhunter.storage.upload_spool import UploadSpool

logger = logging.getLogger("crashhunter.panel_bridge")


def format_timeline_event(entry: dict[str, Any]) -> dict[str, Any]:
    """Convert internal timeline entry to panel ingest format."""
    detected = (
        entry.get("timestamp_utc")
        or entry.get("timestamp_us")
        or entry.get("timestamp")
    )
    return {
        "event_type": str(entry.get("event", "unknown")),
        "title": str(entry.get("event", "Événement")),
        "details": str(entry.get("detail", "")),
        "severity": str(entry.get("severity", "info")),
        "detected_at": detected,
        "timestamp_utc": entry.get("timestamp_utc"),
        "timestamp_original": entry.get("timestamp_original"),
        "timestamp_source": entry.get("timestamp_source"),
        "payload": entry,
    }


class PanelBridge:
    """Remote ingest client for ObiOra Panel CrashHunter API."""

    def __init__(
        self,
        panel_url: str,
        server_id: int,
        agent_token: str,
        enabled: bool = True,
        timeout_seconds: float = 10.0,
        spool_dir: Path | None = None,
    ) -> None:
        self.panel_url = panel_url.rstrip("/")
        self.server_id = int(server_id)
        self.agent_token = agent_token
        self.enabled = enabled and bool(panel_url and agent_token)
        self.timeout_seconds = timeout_seconds
        self._last_push = 0.0
        self.spool = UploadSpool(spool_dir) if spool_dir else None
        self.last_upload_error: str | None = None

    def _post(self, path: str, payload: dict[str, Any], *, spool_kind: str | None = None) -> bool:
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
                ok = resp.status == 200
                if ok:
                    self.last_upload_error = None
                return ok
        except urllib.error.URLError as exc:
            self.last_upload_error = str(exc)
            logger.debug("Panel push failed %s: %s", path, exc)
            if self.spool and spool_kind:
                key = payload.get("report_id") or payload.get("incident_id") or path
                self.spool.enqueue(spool_kind, {"path": path, "body": payload}, str(key))
            return False

    def flush_spool(self, max_items: int = 20) -> int:
        if not self.spool or not self.enabled:
            return 0
        flushed = 0
        for item_path in self.spool.pending()[:max_items]:
            record = self.spool.load(item_path)
            if record is None:
                self.spool.ack(item_path)
                continue
            body = record.get("payload", {})
            api_path = body.get("path", "")
            payload = body.get("body", {})
            self.spool.mark_attempt(item_path)
            if self._post(api_path, payload, spool_kind=None):
                self.spool.ack(item_path)
                flushed += 1
        return flushed

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
        return self._post("crash-hunter/snapshots", {"snapshots": snapshots}, spool_kind="snapshots")

    def push_snapshots_batched(self, snapshots: list[dict[str, Any]], batch_size: int = 50) -> int:
        pushed = 0
        for index in range(0, len(snapshots), batch_size):
            if self.push_snapshots(snapshots[index : index + batch_size]):
                pushed += min(batch_size, len(snapshots) - index)
        return pushed

    def push_report(self, report: dict[str, Any], bundle_path: str | None = None) -> bool:
        return self._post("crash-hunter/reports", {
            "report_json": report,
            "report_id": report.get("report_id"),
            "bundle_path": bundle_path,
            "trigger_type": (
                report.get("reboot_detection", {}).get("reason")
                if isinstance(report.get("reboot_detection"), dict)
                else None
            ),
        }, spool_kind="report")

    def push_incident(self, summary: dict[str, Any]) -> bool:
        return self._post("crash-hunter/incidents", summary, spool_kind="incident")

    def push_events(self, events: list[dict[str, Any]]) -> bool:
        if not events:
            return False
        return self._post("crash-hunter/events", {"events": events}, spool_kind="events")

    def should_push(self, now: float, interval: float) -> bool:
        return (now - self._last_push) >= interval

    def mark_pushed(self, now: float) -> None:
        self._last_push = now

    @property
    def pending_upload_count(self) -> int:
        return self.spool.pending_count() if self.spool else 0
