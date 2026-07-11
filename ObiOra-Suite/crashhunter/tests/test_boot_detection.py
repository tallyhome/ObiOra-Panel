"""Tests for boot/reboot detection."""

from __future__ import annotations

import tempfile
from pathlib import Path
from unittest.mock import patch

from crashhunter.storage.state_store import StateStore


def test_reboot_detected_on_boot_id_change() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        base = Path(tmp)
        store = StateStore(
            base / "boot_id",
            base / "uptime",
            base / "clock",
        )
        store.boot_id_file.write_text("old-boot-id", encoding="utf-8")
        store.last_uptime_file.write_text("3600", encoding="utf-8")

        with patch.object(store, "current_boot_id", return_value="new-boot-id"):
            with patch.object(store, "current_uptime", return_value=120.0):
                result = store.detect_reboot()

        assert result["reboot_detected"] is True
        assert result["reason"] == "boot_id_changed"


def test_no_reboot_same_boot_id() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        base = Path(tmp)
        store = StateStore(base / "boot_id", base / "uptime", base / "clock")
        store.boot_id_file.write_text("same-id", encoding="utf-8")
        store.last_uptime_file.write_text("100", encoding="utf-8")

        with patch.object(store, "current_boot_id", return_value="same-id"):
            with patch.object(store, "current_uptime", return_value=200.0):
                result = store.detect_reboot()

        assert result["reboot_detected"] is False
