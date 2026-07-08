"""Safe command execution layer for Obiora Doctor."""

from __future__ import annotations

import shutil
import subprocess
import time

from core.models import CommandResult


DEFAULT_TIMEOUT_SECONDS = 8


class CommandRunner:
    """Execute read-only system commands with timeout and structured output."""

    def __init__(self, timeout_seconds: int = DEFAULT_TIMEOUT_SECONDS) -> None:
        """Create a command runner.

        Parameters:
            timeout_seconds: Default timeout for commands.

        Returns:
            CommandRunner instance.

        Example:
            runner = CommandRunner(timeout_seconds=5)
        """

        self.timeout_seconds = timeout_seconds

    def exists(self, executable: str) -> bool:
        """Return True when an executable is available in PATH."""

        return shutil.which(executable) is not None

    def run(
        self,
        command: list[str],
        timeout_seconds: int | None = None,
    ) -> CommandResult:
        """Run a command safely and return a structured result.

        Parameters:
            command: Command and arguments. Shell execution is never used.
            timeout_seconds: Optional command-specific timeout.

        Returns:
            CommandResult with stdout, stderr, return code and timing.

        Example:
            runner.run(["uname", "-a"])
        """

        started_at = time.monotonic()
        if not command:
            return CommandResult([], "", "Empty command", None, 0, missing=True)

        if not self.exists(command[0]):
            return CommandResult(command, "", "Command not found", None, 0, missing=True)

        try:
            completed = subprocess.run(
                command,
                check=False,
                capture_output=True,
                text=True,
                timeout=timeout_seconds or self.timeout_seconds,
            )
        except subprocess.TimeoutExpired as exc:
            duration_ms = int((time.monotonic() - started_at) * 1000)
            return CommandResult(
                command=command,
                stdout=exc.stdout or "",
                stderr=exc.stderr or "Command timed out",
                returncode=None,
                duration_ms=duration_ms,
                timed_out=True,
            )

        duration_ms = int((time.monotonic() - started_at) * 1000)
        return CommandResult(
            command=command,
            stdout=completed.stdout.strip(),
            stderr=completed.stderr.strip(),
            returncode=completed.returncode,
            duration_ms=duration_ms,
        )
