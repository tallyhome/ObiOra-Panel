"""Registry of built-in and plugin diagnostic modules."""

from __future__ import annotations

import json
from pathlib import Path

from core.module import DiagnosticModule
from core.plugins import discover_plugins
from modules.apache import ApacheModule
from modules.benchmark import BenchmarkModule
from modules.cpanel import CpanelModule
from modules.cpu import CpuModule
from modules.directadmin import DirectadminModule
from modules.disk import DiskModule
from modules.docker import DockerModule
from modules.firewall import FirewallModule
from modules.kernel import KernelModule
from modules.kvm import KvmModule
from modules.laravel import LaravelModule
from modules.litespeed import LitespeedModule
from modules.lxc import LxcModule
from modules.memcached import MemcachedModule
from modules.mysql import MysqlModule
from modules.network import NetworkModule
from modules.nginx import NginxModule
from modules.php import PhpModule
from modules.plesk import PleskModule
from modules.postgresql import PostgresqlModule
from modules.raid import RaidModule
from modules.ram import RamModule
from modules.reboot import RebootModule
from modules.redis import RedisModule
from modules.security import SecurityModule
from modules.smart import SmartModule
from modules.ssl import SslModule
from modules.swap import SwapModule
from modules.virtualizor import VirtualizorModule
from modules.whmcs import WhmcsModule

MODULES_CONFIG = Path(__file__).resolve().parents[1] / "config" / "modules.json"

BUILTIN_MODULES: list[type[DiagnosticModule]] = [
    CpuModule,
    RamModule,
    SwapModule,
    DiskModule,
    SmartModule,
    RaidModule,
    NetworkModule,
    KernelModule,
    RebootModule,
    DockerModule,
    KvmModule,
    LxcModule,
    VirtualizorModule,
    MysqlModule,
    PostgresqlModule,
    PhpModule,
    ApacheModule,
    NginxModule,
    LitespeedModule,
    LaravelModule,
    CpanelModule,
    PleskModule,
    DirectadminModule,
    FirewallModule,
    SecurityModule,
    SslModule,
    RedisModule,
    MemcachedModule,
    WhmcsModule,
    BenchmarkModule,
]


def all_modules() -> list[type[DiagnosticModule]]:
    """Return built-in modules plus discovered plugins."""

    plugins = discover_plugins()
    seen = {module.name for module in BUILTIN_MODULES}
    combined = list(BUILTIN_MODULES)
    for plugin in plugins:
        if plugin.name not in seen:
            combined.append(plugin)
            seen.add(plugin.name)
    return combined


def enabled_modules() -> list[type[DiagnosticModule]]:
    """Return modules enabled in config/modules.json."""

    enabled = _load_enabled_config()
    return [module for module in all_modules() if enabled.get(module.name, True)]


def module_names() -> list[str]:
    """Return enabled module names in execution order."""

    return [module_class.name for module_class in enabled_modules()]


def module_by_name(name: str) -> type[DiagnosticModule] | None:
    """Return a module class by name."""

    for module_class in all_modules():
        if module_class.name == name:
            return module_class
    return None


def _load_enabled_config() -> dict[str, bool]:
    """Load per-module enable flags."""

    if not MODULES_CONFIG.exists():
        return {}
    with MODULES_CONFIG.open(encoding="utf-8") as handle:
        data = json.load(handle)
    return data.get("enabled", {})
