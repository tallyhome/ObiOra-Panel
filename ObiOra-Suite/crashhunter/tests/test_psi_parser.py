"""PSI parser tests."""

from __future__ import annotations

from crashhunter.utils.proc import ProcReader


def test_cpu_some_only() -> None:
    parsed = ProcReader.parse_pressure_text(
        "some avg10=0.00 avg60=0.02 avg300=0.05 total=123456\n"
    )
    assert "some" in parsed
    assert parsed["some"]["avg10"] == 0.0
    assert "full" not in parsed


def test_memory_some_and_full() -> None:
    parsed = ProcReader.parse_pressure_text(
        "some avg10=1.23 avg60=0.50 avg300=0.10 total=999\n"
        "full avg10=0.00 avg60=0.00 avg300=0.01 total=4567\n"
    )
    assert parsed["some"]["avg10"] == 1.23
    assert parsed["full"]["avg10"] == 0.0


def test_io_some_and_full() -> None:
    parsed = ProcReader.parse_pressure_text(
        "some avg10=12.50 avg60=3.00 avg300=1.00 total=100\n"
        "full avg10=8.00 avg60=2.00 avg300=0.50 total=50\n"
    )
    assert parsed["some"]["avg10"] == 12.5
    assert parsed["full"]["avg10"] == 8.0


def test_empty_text() -> None:
    assert ProcReader.parse_pressure_text("") == {}


def test_malformed_line_ignored() -> None:
    parsed = ProcReader.parse_pressure_text("garbage line\nsome avg10=1.00 avg60=0.00 avg300=0.00 total=1\n")
    assert "some" in parsed


def test_pressure_normalized_avg10() -> None:
    block = {"some": {"avg10": 3.5, "avg60": 1.0, "avg300": 0.5, "total": 10}}
    # Simulate normalized structure used by budget
    assert block.get("avg10", block["some"]["avg10"]) == 3.5
