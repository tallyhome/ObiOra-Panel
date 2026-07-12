"""Upload spool tests."""

from __future__ import annotations

from pathlib import Path

from crashhunter.export.panel_bridge import PanelBridge
from crashhunter.storage.upload_spool import UploadSpool


def test_spool_enqueue_and_pending(tmp_path: Path) -> None:
    spool = UploadSpool(tmp_path / "spool")
    spool.enqueue("incident", {"incident_id": "Incident_1"}, "Incident_1")
    assert spool.pending_count() == 1
    spool.ack(spool.pending()[0])
    assert spool.pending_count() == 0


def test_panel_bridge_spools_on_failure(tmp_path: Path) -> None:
    bridge = PanelBridge(
        panel_url="http://127.0.0.1:1",
        server_id=1,
        agent_token="token",
        enabled=True,
        timeout_seconds=0.1,
        spool_dir=tmp_path / "spool",
    )
    ok = bridge.push_incident({"incident_id": "Incident_x", "status": "ended"})
    assert ok is False
    assert bridge.pending_upload_count == 1
