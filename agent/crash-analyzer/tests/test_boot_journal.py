"""Tests boot journal parsing."""

from __future__ import annotations

import unittest

from crash_analyzer.boot_journal import parse_list_boots


class TestBootJournal(unittest.TestCase):
    def test_parse_list_boots(self) -> None:
        raw = """-3 8f3a2b1c Mon 2026-07-01 10:00:00 UTC
-2 1a2b3c4d Mon 2026-07-08 08:00:00 UTC
-1 9e8d7c6b Wed 2026-07-09 14:30:00 UTC
 0 abcdef01 Wed 2026-07-09 15:00:00 UTC"""
        boots = parse_list_boots(raw)
        self.assertEqual(len(boots), 4)
        self.assertEqual(boots[0]["index"], -3)
        self.assertEqual(boots[-1]["index"], 0)


if __name__ == "__main__":
    unittest.main()
