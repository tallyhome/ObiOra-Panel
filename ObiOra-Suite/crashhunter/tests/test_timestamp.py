"""Timestamp normalization tests."""

from __future__ import annotations

from datetime import datetime, timezone

from crashhunter.utils.timestamp import (
    normalize_timeline_entry,
    parse_to_utc,
    sort_timeline_utc,
)


def test_utc_and_cest_same_instant() -> None:
    utc = parse_to_utc("2026-07-12T02:48:24.278478Z")
    cest = parse_to_utc("2026-07-12T04:48:24.278478+02:00")
    assert utc is not None and cest is not None
    assert utc == cest


def test_mixed_offsets_sort_chronologically() -> None:
    events = [
        {"event": "b", "timestamp_utc": "2026-07-12T04:48:36+02:00"},
        {"event": "a", "timestamp_utc": "2026-07-12T02:48:24Z"},
        {"event": "c", "timestamp": "2026-07-12T06:52:34+02:00"},
    ]
    sorted_events, integrity = sort_timeline_utc([normalize_timeline_entry(e) for e in events])
    assert [e["event"] for e in sorted_events] == ["a", "b", "c"]
    assert integrity is not None
    assert integrity.get("warning") == "timeline_integrity_warning"


def test_legacy_time_only_migrated() -> None:
    entry = normalize_timeline_entry({"timestamp": "08:52:34.123456", "event": "x"})
    assert "timestamp_utc" in entry
    assert entry["timestamp_source"] == "legacy_migrated"


def test_disordered_timeline_corrected() -> None:
    events = [
        {"event": "late", "timestamp_utc": "2026-07-12T10:00:00Z"},
        {"event": "early", "timestamp_utc": "2026-07-12T02:00:00Z"},
    ]
    sorted_events, integrity = sort_timeline_utc(events)
    assert sorted_events[0]["event"] == "early"
    assert integrity is not None
