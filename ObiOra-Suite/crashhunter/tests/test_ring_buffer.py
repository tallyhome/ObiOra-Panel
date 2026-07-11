"""Tests for ring buffer."""

from __future__ import annotations

import json
import tempfile
from pathlib import Path

from crashhunter.storage.ring_buffer import RingBuffer


def test_ring_buffer_circular_overwrite() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        ring_dir = Path(tmp)
        rb = RingBuffer(capacity=3, ring_dir=ring_dir)
        rb.append({"id": 1})
        rb.append({"id": 2})
        rb.append({"id": 3})
        rb.append({"id": 4})
        ordered = rb.get_all_ordered()
        assert len(ordered) == 3
        assert ordered[0]["id"] == 2
        assert ordered[-1]["id"] == 4


def test_ring_buffer_persists_to_disk() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        ring_dir = Path(tmp)
        rb = RingBuffer(capacity=5, ring_dir=ring_dir)
        rb.append({"timestamp": "t1", "value": 42})
        slot_file = ring_dir / "snap_0000.json"
        assert slot_file.exists()
        data = json.loads(slot_file.read_text(encoding="utf-8"))
        assert data["value"] == 42


def test_ring_buffer_reload_from_disk() -> None:
    with tempfile.TemporaryDirectory() as tmp:
        ring_dir = Path(tmp)
        rb1 = RingBuffer(capacity=5, ring_dir=ring_dir)
        rb1.append({"id": 10})
        rb2 = RingBuffer(capacity=5, ring_dir=ring_dir)
        rb2.load_from_disk()
        ordered = rb2.get_all_ordered()
        assert any(s.get("id") == 10 for s in ordered)
