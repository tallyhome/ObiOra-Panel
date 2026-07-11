"""Subprocess runner safety tests."""

from __future__ import annotations

import os
import signal
import subprocess
import sys
import time

from crashhunter.utils.subprocess_runner import SubprocessRunner


def test_normal_command() -> None:
    runner = SubprocessRunner(default_timeout=5.0)
    result = runner.run([sys.executable, "-c", "print('ok')"], timeout=5.0)
    assert result.ok
    assert "ok" in result.stdout
    assert result.termination_method == "completed"


def test_timeout_terminates_process_group() -> None:
    if os.name == "nt":
        return
    runner = SubprocessRunner(default_timeout=1.0, term_grace_seconds=0.2)
    script = "import time; time.sleep(30)"
    result = runner.run([sys.executable, "-c", script], timeout=0.5)
    assert result.timed_out
    assert result.termination_method in ("sigterm_group", "sigkill_group", "sigkill_group_timeout", "sigkill_process")


def test_child_with_grandchild_killed_on_timeout() -> None:
    if os.name == "nt":
        return
    runner = SubprocessRunner(default_timeout=1.0, term_grace_seconds=0.2)
    script = (
        "import os, subprocess, sys, time;"
        "subprocess.Popen([sys.executable, '-c', 'import time; time.sleep(30)'], start_new_session=True);"
        "time.sleep(30)"
    )
    result = runner.run(["bash", "-c", script], timeout=0.5)
    assert result.timed_out
