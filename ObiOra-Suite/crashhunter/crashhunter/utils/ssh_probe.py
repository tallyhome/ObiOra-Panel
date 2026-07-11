"""SSH localhost responsiveness probe — safe under ProtectHome=true."""

from __future__ import annotations

import re
import time
from typing import Any

from crashhunter.utils.subprocess_runner import CommandResult, SubprocessRunner

# Avoid writing to /root/.ssh (ProtectHome); measure daemon responsiveness, not auth success.
SSH_LOCALHOST_BASE = [
    "ssh",
    "-o",
    "ConnectTimeout=2",
    "-o",
    "BatchMode=yes",
    "-o",
    "StrictHostKeyChecking=no",
    "-o",
    "UserKnownHostsFile=/dev/null",
    "-o",
    "GlobalKnownHostsFile=/dev/null",
    "-o",
    "LogLevel=ERROR",
    "localhost",
    "true",
]


def classify_ssh_result(result: CommandResult, latency_ms: float) -> dict[str, Any]:
    """Distinguish timeout, auth failure, local config errors, and handshake latency."""
    stderr = (result.stderr or "").lower()
    stdout = (result.stdout or "").lower()
    combined = f"{stderr}\n{stdout}"

    if result.timed_out:
        return {
            "ok": False,
            "responsive": False,
            "classification": "timeout",
            "timed_out": True,
            "latency_ms": latency_ms,
            "returncode": result.returncode,
            "termination_method": result.termination_method,
        }

    if "connection refused" in combined or "connection reset" in combined:
        return {
            "ok": False,
            "responsive": False,
            "classification": "connection_refused",
            "timed_out": False,
            "latency_ms": latency_ms,
            "returncode": result.returncode,
            "termination_method": result.termination_method,
        }

    if "could not resolve hostname" in combined or "name or service not known" in combined:
        return {
            "ok": False,
            "responsive": False,
            "classification": "dns_error",
            "timed_out": False,
            "latency_ms": latency_ms,
            "returncode": result.returncode,
            "termination_method": result.termination_method,
        }

    if (
        "read-only file system" in combined
        or "could not create directory" in combined
        and ".ssh" in combined
    ):
        return {
            "ok": False,
            "responsive": False,
            "classification": "local_config_error",
            "timed_out": False,
            "latency_ms": latency_ms,
            "returncode": result.returncode,
            "termination_method": result.termination_method,
            "detail": "known_hosts_write_blocked",
        }

    if result.returncode == 255 and (
        "permission denied" in combined
        or "authentication failed" in combined
        or "publickey" in combined
    ):
        # Handshake completed; auth refused is not a freeze signal by itself.
        return {
            "ok": False,
            "responsive": True,
            "classification": "auth_refused",
            "timed_out": False,
            "latency_ms": latency_ms,
            "returncode": result.returncode,
            "termination_method": result.termination_method,
        }

    if result.ok:
        return {
            "ok": True,
            "responsive": True,
            "classification": "success",
            "timed_out": False,
            "latency_ms": latency_ms,
            "returncode": result.returncode,
            "termination_method": result.termination_method,
        }

    if re.search(r"connection timed out|timed out waiting", combined):
        return {
            "ok": False,
            "responsive": False,
            "classification": "timeout",
            "timed_out": True,
            "latency_ms": latency_ms,
            "returncode": result.returncode,
            "termination_method": result.termination_method,
        }

    return {
        "ok": False,
        "responsive": latency_ms < 5000,
        "classification": "ssh_error",
        "timed_out": False,
        "latency_ms": latency_ms,
        "returncode": result.returncode,
        "termination_method": result.termination_method,
    }


def probe_ssh_localhost(runner: SubprocessRunner, timeout: float) -> dict[str, Any]:
    start = time.monotonic()
    result = runner.run(list(SSH_LOCALHOST_BASE), timeout=timeout)
    latency_ms = round((time.monotonic() - start) * 1000, 2)
    return classify_ssh_result(result, latency_ms)
