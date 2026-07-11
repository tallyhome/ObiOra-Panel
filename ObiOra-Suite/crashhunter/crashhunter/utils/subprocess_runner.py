"""Safe subprocess execution with timeouts and process-group termination."""

from __future__ import annotations

import logging
import os
import signal
import subprocess
import time
from dataclasses import dataclass

logger = logging.getLogger("crashhunter.subprocess")

_SENSITIVE_PATTERNS = ("password", "token", "secret", "apikey", "api_key")


def _redact_command(command: list[str]) -> str:
    text = " ".join(command)
    lower = text.lower()
    if any(p in lower for p in _SENSITIVE_PATTERNS):
        return "<redacted>"
    return text


@dataclass
class CommandResult:
    command: list[str]
    stdout: str
    stderr: str
    returncode: int
    timed_out: bool = False
    pid: int | None = None
    termination_method: str = "completed"

    @property
    def ok(self) -> bool:
        return self.returncode == 0 and not self.timed_out


class SubprocessRunner:
    """Execute system commands with strict timeouts to avoid daemon stalls."""

    def __init__(self, default_timeout: float = 4.0, term_grace_seconds: float = 0.5) -> None:
        self.default_timeout = default_timeout
        self.term_grace_seconds = term_grace_seconds

    def run(
        self,
        command: list[str],
        timeout: float | None = None,
        *,
        use_process_group: bool = True,
    ) -> CommandResult:
        timeout = timeout if timeout is not None else self.default_timeout
        cmd_display = _redact_command(command)

        if not use_process_group or os.name == "nt":
            return self._run_simple(command, timeout, cmd_display)

        return self._run_with_process_group(command, timeout, cmd_display)

    def _run_simple(self, command: list[str], timeout: float, cmd_display: str) -> CommandResult:
        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                text=True,
                timeout=timeout,
                check=False,
            )
            return CommandResult(
                command=command,
                stdout=completed.stdout,
                stderr=completed.stderr,
                returncode=completed.returncode,
                termination_method="completed",
            )
        except subprocess.TimeoutExpired as exc:
            logger.warning(
                "SUBPROCESS_TIMEOUT_TERMINATED command=%s timeout=%.2fs termination_method=simple_timeout",
                cmd_display,
                timeout,
            )
            stdout = exc.stdout.decode("utf-8", errors="replace") if exc.stdout else ""
            stderr = exc.stderr.decode("utf-8", errors="replace") if exc.stderr else ""
            return CommandResult(
                command=command,
                stdout=stdout,
                stderr=stderr,
                returncode=-1,
                timed_out=True,
                termination_method="simple_timeout",
            )
        except OSError as exc:
            logger.debug("Command failed: %s — %s", cmd_display, exc)
            return CommandResult(
                command=command,
                stdout="",
                stderr=str(exc),
                returncode=-1,
                termination_method="spawn_error",
            )

    def _run_with_process_group(
        self,
        command: list[str],
        timeout: float,
        cmd_display: str,
    ) -> CommandResult:
        proc = None
        try:
            proc = subprocess.Popen(
                command,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                start_new_session=True,
            )
            stdout, stderr = proc.communicate(timeout=timeout)
            return CommandResult(
                command=command,
                stdout=stdout,
                stderr=stderr,
                returncode=proc.returncode if proc.returncode is not None else -1,
                pid=proc.pid,
                termination_method="completed",
            )
        except subprocess.TimeoutExpired:
            pid = proc.pid if proc is not None else None
            method = self._terminate_process_group(proc)
            stdout = ""
            stderr = ""
            if proc is not None:
                try:
                    out, err = proc.communicate(timeout=1.0)
                    stdout = out or ""
                    stderr = err or ""
                except subprocess.TimeoutExpired:
                    pass
            logger.warning(
                "SUBPROCESS_TIMEOUT_TERMINATED command=%s pid=%s timeout=%.2fs termination_method=%s returncode=%s",
                cmd_display,
                pid,
                timeout,
                method,
                proc.returncode if proc is not None else -1,
            )
            return CommandResult(
                command=command,
                stdout=stdout,
                stderr=stderr,
                returncode=proc.returncode if proc is not None and proc.returncode is not None else -1,
                timed_out=True,
                pid=pid,
                termination_method=method,
            )
        except OSError as exc:
            logger.debug("Command failed: %s — %s", cmd_display, exc)
            return CommandResult(
                command=command,
                stdout="",
                stderr=str(exc),
                returncode=-1,
                termination_method="spawn_error",
            )

    def _terminate_process_group(self, proc: subprocess.Popen[str] | None) -> str:
        if proc is None or proc.pid is None:
            return "no_process"
        pgid = proc.pid
        try:
            os.killpg(pgid, signal.SIGTERM)
        except ProcessLookupError:
            return "already_exited"
        except OSError:
            try:
                proc.terminate()
            except OSError:
                return "term_failed"

        deadline = time.monotonic() + self.term_grace_seconds
        while time.monotonic() < deadline:
            if proc.poll() is not None:
                return "sigterm_group"
            time.sleep(0.05)

        try:
            os.killpg(pgid, signal.SIGKILL)
        except ProcessLookupError:
            return "sigterm_group"
        except OSError:
            try:
                proc.kill()
            except OSError:
                return "kill_failed"
            return "sigkill_process"

        try:
            proc.wait(timeout=1.0)
        except subprocess.TimeoutExpired:
            return "sigkill_group_timeout"
        return "sigkill_group"

    def run_text(self, command: list[str], timeout: float | None = None) -> str:
        return self.run(command, timeout=timeout).stdout.strip()
