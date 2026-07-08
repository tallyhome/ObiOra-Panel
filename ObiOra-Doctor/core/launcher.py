"""Simple unified launcher menu for Obiora Doctor."""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CLI = ROOT / "bin" / "obiora-doctor.py"


MENU = """
+==========================================+
|           OBIORA DOCTOR                  |
|     Diagnostic serveur Linux             |
+==========================================+
|  1  Diagnostic complet (scan)            |
|  2  Diagnostic par module                |
|  3  Monitoring temps reel (watch)        |
|  4  Analyse reboot (instantanee)         |
|  5  Surveillance reboot 24h              |
|  6  Rapport support anonymise            |
|  7  Benchmark performance                |
|  8  Plan de depannage (rescue)           |
|  9  Historique des rapports              |
| 10  Interface web securisee (localhost)  |
| 11  API REST locale                        |
|  0  Quitter                              |
+==========================================+
"""


def run_launcher() -> int:
    """Display main menu and run selected action."""

    while True:
        print(MENU)
        choice = input("Votre choix: ").strip()

        if choice in {"0", "q", "quit", "exit"}:
            return 0
        if choice == "1":
            return _run(["scan"])
        if choice == "2":
            return _run(["interactive"])
        if choice == "3":
            return _run(["watch"])
        if choice == "4":
            return _run(["scan", "--module", "reboot"])
        if choice == "5":
            hours = input("Duree en heures [24]: ").strip() or "24"
            interval = input("Intervalle minutes [5]: ").strip() or "5"
            return _run(["reboot-monitor", "--hours", hours, "--interval", interval])
        if choice == "6":
            return _run(["scan", "--support", "--zip"])
        if choice == "7":
            return _run(["bench"])
        if choice == "8":
            return _run(["rescue"])
        if choice == "9":
            return _run(["history"])
        if choice == "10":
            print("\nSECURITE: interface liee a 127.0.0.1 uniquement.")
            print("Acces distant: ssh -L 8766:127.0.0.1:8766 root@serveur\n")
            return _run(["web"])
        if choice == "11":
            return _run(["api"])
        print("Choix invalide.\n")


def _run(args: list[str]) -> int:
    """Run CLI subprocess and return to menu unless quitting."""

    result = subprocess.run([sys.executable, str(CLI), *args], cwd=str(ROOT))
    input("\nAppuyez sur Entree pour revenir au menu...")
    return result.returncode
