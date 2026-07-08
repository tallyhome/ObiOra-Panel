"""Tests unitaires — détecteurs d'événements."""

from __future__ import annotations

import re
import tempfile
import unittest
from pathlib import Path

from crash_analyzer.detectors import EventDetector


class TestEventDetectorPatterns(unittest.TestCase):
    """Vérifie les motifs kernel/dmesg réalistes."""

    def test_kernel_panic_pattern(self) -> None:
        self.assertTrue(self._matches("kernel_panic", "Kernel panic - not syncing: Fatal exception"))

    def test_oom_pattern(self) -> None:
        self.assertTrue(self._matches("oom_killer", "oom-kill:constraint=CONSTRAINT_NONE task=mysql"))

    def test_nvme_pattern(self) -> None:
        self.assertTrue(self._matches("nvme_error", "nvme0n1: I/O error, dev nvme0n1, sector 4096"))

    def test_rcu_stall_pattern(self) -> None:
        self.assertTrue(self._matches("rcu_stall", "rcu_sched detected stalls on CPUs/tasks"))

    def test_filesystem_ro_pattern(self) -> None:
        self.assertTrue(self._matches("filesystem_ro", "EXT4-fs error (device sda1): Remounting filesystem read-only"))

    def _matches(self, event_type: str, line: str) -> bool:
        for etype, _severity, pattern, _title in EventDetector.CRITICAL_PATTERNS:
            if etype == event_type:
                return bool(pattern.search(line))
        return False


class TestEventDetectorState(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.mkdtemp()
        self.detector = EventDetector(str(Path(self.tmp) / "state.json"))

    def test_unexpected_reboot_detected(self) -> None:
        self.detector._state["last_boot_id"] = "boot-old"
        self.detector._state["graceful_shutdown"] = False
        event = self.detector.check_unexpected_reboot("boot-new", 60.0)
        self.assertIsNotNone(event)
        assert event is not None
        self.assertEqual(event.event_type, "unexpected_reboot")

    def test_graceful_reboot_not_flagged(self) -> None:
        self.detector._state["last_boot_id"] = "boot-old"
        self.detector._state["graceful_shutdown"] = True
        self.assertIsNone(self.detector.check_unexpected_reboot("boot-new", 60.0))


if __name__ == "__main__":
    unittest.main()
