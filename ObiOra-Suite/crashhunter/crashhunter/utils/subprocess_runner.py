"""Safe subprocess execution with timeouts."""

from __future__ import annotations

import logging
import subprocess
from dataclasses import dataclass

logger = logging.getLogger("crashhunter.subprocess")


@dataclass
class CommandResult:
    command: list[str]
    stdout: str
    stderr: str
    returncode: int
    timed_out: bool = False

    @property
    def ok(self) -> bool:
        return self.returncode == 0 and not self.timed_out


class SubprocessRunner:
    """Execute system commands with strict timeouts to avoid daemon stalls."""

    def __init__(self, default_timeout: float = 4.0) -> None:
        self.default_timeout = default_timeout

    def run(
        self,
        command: list[str],
        timeout: float | None = None,
    ) -> CommandResult:
        timeout = timeout if timeout is not None else self.default_timeout
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
            )
        except subprocess.TimeoutExpired as exc:
            logger.warning("Command timed out: %s", " ".join(command))
            stdout = exc.stdout.decode("utf-8", errors="replace") if exc.stdout else ""
            stderr = exc.stderr.decode("utf-8", errors="replace") if exc.stderr else ""
            return CommandResult(
                command=command,
                stdout=stdout,
                stderr=stderr,
                returncode=-1,
                timed_out=True,
            )
        except OSError as exc:
            logger.debug("Command failed: %s — %s", " ".join(command), exc)
            return CommandResult(
                command=command,
                stdout="",
                stderr=str(exc),
                returncode=-1,
            )

    def run_text(self, command: list[str], timeout: float | None = None) -> str:
        return self.run(command, timeout=timeout).stdout.strip()
