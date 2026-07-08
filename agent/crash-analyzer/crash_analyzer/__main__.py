"""Point d'entrée CLI du Crash Analyzer."""

from __future__ import annotations

import argparse
import logging
import sys

from crash_analyzer.config import CrashAnalyzerConfig
from crash_analyzer.daemon import CrashAnalyzerDaemon


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="ObiOra Crash Analyzer")
    parser.add_argument("-c", "--config", help="Chemin du fichier de configuration JSON")
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs détaillés")
    args = parser.parse_args(argv)

    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    )

    from pathlib import Path

    config_path = Path(args.config) if args.config else None
    config = CrashAnalyzerConfig.load(config_path)
    daemon = CrashAnalyzerDaemon(config)
    daemon.run()
    return 0


if __name__ == "__main__":
    sys.exit(main())
