"""Tests unitaires — stockage SQLite."""

from __future__ import annotations

import tempfile
import time
import unittest
from pathlib import Path

from crash_analyzer.storage import SqliteMetricsStorage


class TestSqliteStorage(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.mkdtemp()
        self.db = str(Path(self.tmp) / "test.db")
        self.storage = SqliteMetricsStorage(self.db)
        self.storage.initialize()

    def test_insert_and_retrieve_metric(self) -> None:
        ts = time.time()
        self.storage.insert_metric("cpu", {"usage_percent": 42.5}, ts)
        rows = self.storage.metrics_since(ts - 1)
        self.assertEqual(len(rows), 1)
        self.assertEqual(rows[0]["collector"], "cpu")
        self.assertEqual(rows[0]["payload"]["usage_percent"], 42.5)

    def test_insert_event(self) -> None:
        self.storage.insert_event("oom_killer", "critical", "OOM", "details", {"pid": 1})
        events = self.storage.recent_events(10)
        self.assertEqual(len(events), 1)
        self.assertEqual(events[0]["event_type"], "oom_killer")

    def test_prune_old(self) -> None:
        old_ts = time.time() - 7200
        self.storage.insert_metric("memory", {"used_percent": 80}, old_ts)
        self.storage.insert_metric("memory", {"used_percent": 90}, time.time())
        deleted = self.storage.prune_old(3600)
        self.assertGreaterEqual(deleted, 1)
        remaining = self.storage.metrics_since(time.time() - 7200)
        self.assertEqual(len(remaining), 1)


if __name__ == "__main__":
    unittest.main()
