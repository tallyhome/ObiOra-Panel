"""Tests for extended security Doctor modules."""

from __future__ import annotations

from core.runner import CommandRunner
from modules.accounts import AccountsModule
from modules.auth_logs import AuthLogsModule
from modules.hosting_security import HostingSecurityModule
from modules.malware import MalwareModule
from modules.obiora import ObioraModule
from modules.persistence import PersistenceModule
from modules.privesc import PrivescModule
from modules.security import SecurityModule
from modules.web_perms import WebPermsModule


def _run(module_class):
    module = module_class(CommandRunner(timeout_seconds=5))
    return module.run({"hostname": "test"})


def test_security_module_runs() -> None:
    result = _run(SecurityModule)
    assert result.module == "security"
    assert 0 <= result.score <= 100


def test_obiora_module_runs() -> None:
    assert _run(ObioraModule).module == "obiora"


def test_malware_module_runs() -> None:
    assert _run(MalwareModule).module == "malware"


def test_accounts_module_runs() -> None:
    assert _run(AccountsModule).module == "accounts"


def test_persistence_module_runs() -> None:
    assert _run(PersistenceModule).module == "persistence"


def test_privesc_module_runs() -> None:
    assert _run(PrivescModule).module == "privesc"


def test_auth_logs_module_runs() -> None:
    assert _run(AuthLogsModule).module == "auth_logs"


def test_web_perms_module_runs() -> None:
    assert _run(WebPermsModule).module == "web_perms"


def test_hosting_security_module_runs() -> None:
    assert _run(HostingSecurityModule).module == "hosting_security"
