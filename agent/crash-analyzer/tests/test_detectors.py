"""Tests unitaires — détecteurs d'événements."""

from __future__ import annotations

import re
import tempfile
import unittest
from pathlib import Path

from crash_analyzer.detectors import EventDetector, matches_ecc_error, is_edac_controller_init


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

    def test_edac_init_not_ecc_error(self) -> None:
        line = (
            "EDAC MC1: Giving out device to module skx_edac controller "
            "Skylake Socket#0 IMC#1: DEV 0000:64:0c.0 (INTERRUPT)"
        )
        self.assertTrue(is_edac_controller_init(line))
        self.assertFalse(matches_ecc_error(line))

    def test_edac_ce_count_is_ecc_error(self) -> None:
        line = "EDAC MC0: 1 CE memory read error on CPU_SrcID#0_Ha#0_Chan#0_DIMM#0"
        self.assertFalse(is_edac_controller_init(line))
        self.assertTrue(matches_ecc_error(line))

    def test_edac_ue_is_ecc_error(self) -> None:
        line = "EDAC MC0: 1 UE memory scrubbing error on CPU_SrcID#0_Ha#0_Chan#0_DIMM#0"
        self.assertTrue(matches_ecc_error(line))

    def test_device_substring_not_ecc_error(self) -> None:
        """Régression : 'CE' dans 'device' ne doit pas déclencher ecc_error."""
        line = "EDAC MC1: Giving out device to module skx_edac"
        self.assertFalse(matches_ecc_error(line))

    def test_mce_hardware_error(self) -> None:
        self.assertTrue(matches_ecc_error("mce: [Hardware Error]: Machine check events logged"))

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
