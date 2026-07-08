"""Dedicated benchmark runner for Obiora Bench."""

from __future__ import annotations

import os
import socket
import subprocess
import time
from typing import Any


def run_full_benchmark(cache_dir: str = "cache") -> dict[str, Any]:
    """Run CPU, RAM, disk, network and optional fio IOPS benchmarks."""

    os.makedirs(cache_dir, exist_ok=True)
    results = {
        "cpu_ops_per_sec": _bench_cpu(),
        "ram_mb_per_sec": _bench_ram(),
        "disk_mb_per_sec": _bench_disk(cache_dir),
        "network_latency_ms": _bench_network(),
        "fio": _bench_fio(cache_dir),
    }
    return results


def render_bench_text(results: dict[str, Any]) -> str:
    """Render benchmark results as plain text."""

    fio = results.get("fio") or {}
    fio_line = "N/A"
    if fio.get("available"):
        fio_line = f"{fio.get('iops', 0):.0f} IOPS ({fio.get('mode', 'fio')})"
    return "\n".join(
        [
            "OBIORA BENCH",
            f"CPU:     {results['cpu_ops_per_sec']:,} ops/s",
            f"RAM:     {results['ram_mb_per_sec']:.1f} MB/s",
            f"Disque:  {results['disk_mb_per_sec']:.1f} MB/s",
            f"Reseau:  {results['network_latency_ms']:.1f} ms (localhost)",
            f"IOPS:    {fio_line}",
            "",
        ]
    )


def _bench_cpu() -> int:
    started = time.monotonic()
    total = 0
    while time.monotonic() - started < 0.5:
        total += 1
    return int(total / max(time.monotonic() - started, 0.001))


def _bench_ram() -> float:
    started = time.monotonic()
    size = 0
    while time.monotonic() - started < 0.3:
        block = bytearray(1024 * 1024)
        block[0] = 1
        size += 1
    return size / max(time.monotonic() - started, 0.001)


def _bench_disk(cache_dir: str) -> float:
    path = os.path.join(cache_dir, ".bench-disk.tmp")
    payload = b"x" * (1024 * 1024)
    started = time.monotonic()
    written = 0
    with open(path, "wb") as handle:
        while time.monotonic() - started < 0.3:
            handle.write(payload)
            written += 1
    try:
        os.remove(path)
    except OSError:
        pass
    return written / max(time.monotonic() - started, 0.001)


def _bench_network() -> float:
    started = time.monotonic()
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(1)
    try:
        sock.connect(("127.0.0.1", 1))
    except OSError:
        pass
    finally:
        sock.close()
    return (time.monotonic() - started) * 1000


def _bench_fio(cache_dir: str) -> dict[str, Any]:
    """Run fio benchmark if available, else return unavailable."""

    fio_path = "fio"
    try:
        subprocess.run([fio_path, "--version"], capture_output=True, check=True, timeout=5)
    except (FileNotFoundError, subprocess.SubprocessError):
        return {"available": False}

    target = os.path.join(cache_dir, ".fio-test")
    try:
        completed = subprocess.run(
            [
                fio_path,
                "--name=obiora",
                f"--filename={target}",
                "--size=64M",
                "--bs=4k",
                "--iodepth=1",
                "--rw=randread",
                "--direct=1",
                "--runtime=3",
                "--time_based",
                "--group_reporting",
                "--output-format=json",
            ],
            capture_output=True,
            text=True,
            timeout=20,
            check=False,
        )
    except subprocess.TimeoutExpired:
        return {"available": True, "error": "timeout"}
    finally:
        try:
            os.remove(target)
        except OSError:
            pass

    if completed.returncode != 0:
        return {"available": True, "error": completed.stderr[:200]}

    import json

    try:
        data = json.loads(completed.stdout)
        iops = data["jobs"][0]["read"]["iops"]
        return {"available": True, "iops": iops, "mode": "fio-randread-4k"}
    except (KeyError, IndexError, json.JSONDecodeError):
        return {"available": True, "error": "parse failed"}
