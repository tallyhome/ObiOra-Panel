"""Post-reboot auto benchmark suite."""

from __future__ import annotations

import json
import logging
import shutil
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from crashhunter.utils.subprocess_runner import SubprocessRunner

logger = logging.getLogger("crashhunter.benchmark")


class PostRebootBenchmark:
    """Run fio, stress-ng, smartctl after reboot and compare with history."""

    def __init__(self, settings: Any) -> None:
        self.settings = settings.benchmark
        self.state_file = settings.benchmark_state_file
        self.runner = SubprocessRunner(default_timeout=settings.benchmark.max_duration_seconds)

    def should_run(self, reboot_detected: bool) -> bool:
        return self.settings.enabled and reboot_detected

    def run(self) -> dict[str, Any]:
        if not self.settings.enabled:
            return {"ran": False, "reason": "disabled"}

        results: dict[str, Any] = {
            "ran": True,
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "tests": {},
        }

        if shutil.which("fio") and self.settings.run_fio:
            results["tests"]["fio"] = self._run_fio()
        if shutil.which("stress-ng") and self.settings.run_stress_ng:
            results["tests"]["stress_ng"] = self._run_stress_ng()
        if shutil.which("smartctl") and self.settings.run_smart:
            results["tests"]["smart"] = self._run_smart()
        if shutil.which("ping"):
            results["tests"]["network_latency"] = self._run_ping()

        results["comparison"] = self._compare_with_history(results)
        self._save_result(results)
        return results

    def _run_fio(self) -> dict[str, Any]:
        cmd = [
            "fio", "--name=crashhunter-bench", "--ioengine=libaio", "--direct=1",
            "--bs=4k", "--iodepth=32", "--rw=randread", "--size=256M", "--numjobs=1",
            "--runtime=10", "--time_based", "--group_reporting", "--output-format=json",
        ]
        result = self.runner.run(cmd, timeout=30.0)
        parsed: dict[str, Any] = {"raw_available": bool(result.stdout)}
        if result.stdout.strip().startswith("{"):
            try:
                data = json.loads(result.stdout)
                jobs = data.get("jobs", [{}])
                if jobs:
                    read_iops = jobs[0].get("read", {}).get("iops", 0)
                    read_lat = jobs[0].get("read", {}).get("lat_ns", {}).get("mean", 0)
                    parsed["read_iops"] = read_iops
                    parsed["read_latency_ns_mean"] = read_lat
            except json.JSONDecodeError:
                pass
        parsed["returncode"] = result.returncode
        return parsed

    def _run_stress_ng(self) -> dict[str, Any]:
        result = self.runner.run(
            ["stress-ng", "--cpu", "2", "--timeout", "10s", "--metrics-brief"],
            timeout=20.0,
        )
        return {"stdout": result.stdout[:5000], "returncode": result.returncode}

    def _run_smart(self) -> dict[str, Any]:
        result = self.runner.run(
            ["bash", "-c", "for d in /dev/nvme?n1 /dev/sd?; do [ -b \"$d\" ] && smartctl -H \"$d\" 2>/dev/null; done"],
            timeout=15.0,
        )
        return {"stdout": result.stdout[:10000], "returncode": result.returncode}

    def _run_ping(self) -> dict[str, Any]:
        target = self.settings.ping_target
        result = self.runner.run(["ping", "-c", "5", "-W", "2", target], timeout=15.0)
        avg_ms = None
        for line in result.stdout.splitlines():
            if "rtt min/avg/max" in line or "min/avg/max" in line:
                parts = line.split("=")[-1].split("/")
                if len(parts) >= 2:
                    try:
                        avg_ms = float(parts[1])
                    except ValueError:
                        pass
        return {"target": target, "avg_ms": avg_ms, "stdout": result.stdout[:2000]}

    def _compare_with_history(self, current: dict[str, Any]) -> dict[str, Any]:
        history = self._load_history()
        if not history:
            return {"status": "first_run", "regressions": []}
        prev = history[-1]
        regressions: list[str] = []
        cur_fio = current.get("tests", {}).get("fio", {})
        prev_fio = prev.get("tests", {}).get("fio", {})
        if cur_fio.get("read_iops") and prev_fio.get("read_iops"):
            ratio = cur_fio["read_iops"] / max(prev_fio["read_iops"], 1)
            if ratio < 0.7:
                regressions.append(f"Disk IOPS dropped {100 - ratio * 100:.0f}% vs previous reboot")
        cur_ping = current.get("tests", {}).get("network_latency", {})
        prev_ping = prev.get("tests", {}).get("network_latency", {})
        if cur_ping.get("avg_ms") and prev_ping.get("avg_ms"):
            if cur_ping["avg_ms"] > prev_ping["avg_ms"] * 2:
                regressions.append("Network latency doubled vs previous reboot")
        return {"status": "compared", "regressions": regressions, "previous_timestamp": prev.get("timestamp")}

    def _load_history(self) -> list[dict[str, Any]]:
        if not self.state_file.exists():
            return []
        try:
            return json.loads(self.state_file.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return []

    def _save_result(self, result: dict[str, Any]) -> None:
        history = self._load_history()
        history.append(result)
        history = history[-20:]
        try:
            self.state_file.parent.mkdir(parents=True, exist_ok=True)
            self.state_file.write_text(json.dumps(history, indent=2), encoding="utf-8")
        except OSError as exc:
            logger.warning("Benchmark history save failed: %s", exc)
