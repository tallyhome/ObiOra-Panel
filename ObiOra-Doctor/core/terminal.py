"""Terminal UI helpers for Obiora Doctor."""

from __future__ import annotations

import sys

from core.models import ModuleResult, Report, Severity


GREEN = "\033[32m"
YELLOW = "\033[33m"
RED = "\033[31m"
CYAN = "\033[36m"
BOLD = "\033[1m"
RESET = "\033[0m"


def colorize(text: str, color: str, enabled: bool = True) -> str:
    """Return colored text when color output is enabled."""

    if not enabled:
        return text
    return f"{color}{text}{RESET}"


def print_summary(report: Report, report_dir: str, color: bool = True) -> None:
    """Print a concise terminal summary for a report."""

    unicode_enabled = _supports_unicode()
    top = "╔══════════════════════════╗" if unicode_enabled else "+==========================+"
    middle = "══════════════════════════" if unicode_enabled else "=========================="
    bottom = "╚══════════════════════════╝" if unicode_enabled else "+==========================+"

    print(colorize(top, CYAN, color))
    print(colorize("OBIORA DOCTOR", BOLD, color))
    print("Health Score")
    print(colorize(f"{report.score} %", GREEN if report.score >= 90 else YELLOW, color))
    print(colorize(middle, CYAN, color))
    for result in report.results:
        print(_module_line(result, color, unicode_enabled))
    print(colorize(bottom, CYAN, color))
    print(f"Rapport: {report_dir}")


def _module_line(result: ModuleResult, color: bool, unicode_enabled: bool) -> str:
    symbol = "✔" if unicode_enabled else "+"
    line_color = GREEN
    if any(finding.level == Severity.CRITICAL for finding in result.findings):
        symbol = "✖" if unicode_enabled else "x"
        line_color = RED
    elif any(finding.level == Severity.WARNING for finding in result.findings):
        symbol = "!"
        line_color = YELLOW
    return colorize(f"{symbol} {result.module} ({result.score}%)", line_color, color)


def _supports_unicode() -> bool:
    """Return True when stdout can encode the terminal UI symbols."""

    encoding = sys.stdout.encoding or "utf-8"
    try:
        "╔✔✖".encode(encoding)
    except UnicodeEncodeError:
        return False
    return True
