"""Tests for ring buffer tmpfs deferred sync."""

from __future__ import annotations

import tempfile
from pathlib import Path

from crashhunter.storage.ring_buffer import RingBuffer


def test_ring_buffer_deferred_sync() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        ram_dir = Path(tmp) / "ram"
        sync_dir = Path(tmp) / "persist"
        ram_dir.mkdir()
        sync_dir.mkdir()
        rb = RingBuffer(capacity=3, ring_dir=ram_dir, sync_dir=sync_dir, defer_disk_writes=True)
        rb.append({"id": 1})
        assert not (sync_dir / "snap_0000.json").exists()
        synced = rb.sync_to_disk()
        assert synced == 1
        assert (sync_dir / "snap_0000.json").exists()
