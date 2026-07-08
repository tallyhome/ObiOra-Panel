"""Interactive terminal UI for Obiora Doctor."""

from __future__ import annotations

import sys
from pathlib import Path

from core.engine import DiagnosticEngine
from core.reports import write_report_bundle
from core.terminal import print_summary
from modules.registry import module_names


def run_interactive(
    engine: DiagnosticEngine,
    reports_dir: str,
    *,
    color: bool = True,
) -> int:
    """Run the interactive module menu."""

    items = [(name, name.upper()) for name in module_names()]

    while True:
        _print_menu(items, color)
        choice = input("Choix: ").strip()
        if choice in {"0", "q", "quit", "exit"}:
            return 0
        if choice.lower() == "a":
            report = engine.run()
            output = write_report_bundle(report, Path(reports_dir))
            print_summary(report, str(output), color=color)
            return 1 if report.score < 70 else 0

        module_name = _resolve_choice(choice, items)
        if not module_name:
            print("Choix invalide.")
            continue

        report = engine.run([module_name])
        output = write_report_bundle(report, Path(reports_dir))
        print_summary(report, str(output), color=color)


def _print_menu(items: list[tuple[str, str]], color: bool) -> None:
    from core.terminal import BOLD, CYAN, colorize

    unicode_ok = _unicode_ok()
    top = "╔══════════════════════════╗" if unicode_ok else "+==========================+"
    mid = "══════════════════════════" if unicode_ok else "=========================="
    bottom = "╚══════════════════════════╝" if unicode_ok else "+==========================+"

    print(colorize(top, CYAN, color))
    print(colorize("OBIORA DOCTOR", BOLD, color))
    print("Health Score")
    print(colorize(mid, CYAN, color))
    for index, (_, title) in enumerate(items, start=1):
        print(f"{index} {title}")
    print("A Scan complet")
    print("0 Quitter")
    print(colorize(bottom, CYAN, color))


def _resolve_choice(choice: str, items: list[tuple[str, str]]) -> str | None:
    if choice.isdigit():
        index = int(choice)
        if 1 <= index <= len(items):
            return items[index - 1][0]
        return None
    if choice in module_names():
        return choice
    return None


def _unicode_ok() -> bool:
    encoding = sys.stdout.encoding or "utf-8"
    try:
        "╔".encode(encoding)
    except UnicodeEncodeError:
        return False
    return True
