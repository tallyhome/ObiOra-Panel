"""Shared type definitions."""

from __future__ import annotations

from typing import Any, TypedDict


class ProcessInfo(TypedDict, total=False):
    pid: int
    ppid: int
    comm: str
    state: str
    cpu_percent: float
    mem_percent: float
    rss_kb: int
    vsz_kb: int
    threads: int
    io_read_kb: int
    io_write_kb: int
    open_files: int
    children: list[int]


class DiagnosisFinding(TypedDict):
    category: str
    title: str
    description: str
    confidence: float
    evidence: list[str]
    severity: str


class SuspiciousEvent(TypedDict):
    timestamp: str
    event: str
    source: str
    probability: float
    detail: str


Snapshot = dict[str, Any]
