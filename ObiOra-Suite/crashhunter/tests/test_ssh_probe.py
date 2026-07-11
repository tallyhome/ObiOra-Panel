"""SSH probe classification tests."""

from __future__ import annotations

from crashhunter.utils.ssh_probe import classify_ssh_result
from crashhunter.utils.subprocess_runner import CommandResult


def test_auth_refused_is_responsive() -> None:
    result = CommandResult(
        command=["ssh"],
        stdout="",
        stderr="Permission denied (publickey,password).",
        returncode=255,
    )
    classified = classify_ssh_result(result, 120.0)
    assert classified["classification"] == "auth_refused"
    assert classified["responsive"] is True
    assert classified["timed_out"] is False


def test_timeout_is_unresponsive() -> None:
    result = CommandResult(
        command=["ssh"],
        stdout="",
        stderr="",
        returncode=-1,
        timed_out=True,
        termination_method="sigterm_group",
    )
    classified = classify_ssh_result(result, 3000.0)
    assert classified["classification"] == "timeout"
    assert classified["responsive"] is False


def test_connection_refused() -> None:
    result = CommandResult(
        command=["ssh"],
        stdout="",
        stderr="ssh: connect to host localhost port 22: Connection refused",
        returncode=255,
    )
    classified = classify_ssh_result(result, 50.0)
    assert classified["classification"] == "connection_refused"


def test_local_known_hosts_error() -> None:
    result = CommandResult(
        command=["ssh"],
        stdout="",
        stderr="Could not create directory '/root/.ssh' (Read-only file system)",
        returncode=255,
    )
    classified = classify_ssh_result(result, 80.0)
    assert classified["classification"] == "local_config_error"
    assert classified["responsive"] is False
