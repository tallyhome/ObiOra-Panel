"""Application logging for Obiora Doctor."""

from __future__ import annotations

import logging
from pathlib import Path


def setup_logging(logs_dir: str = "logs", verbose: bool = False) -> logging.Logger:
    """Configure and return the application logger.

    Parameters:
        logs_dir: Directory for log files.
        verbose: Enable DEBUG level when True.

    Returns:
        Configured logger instance.
    """

    Path(logs_dir).mkdir(parents=True, exist_ok=True)
    logger = logging.getLogger("obiora-doctor")
    logger.setLevel(logging.DEBUG if verbose else logging.INFO)
    logger.handlers.clear()

    formatter = logging.Formatter(
        "%(asctime)s [%(levelname)s] %(name)s: %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )

    file_handler = logging.FileHandler(Path(logs_dir) / "obiora-doctor.log", encoding="utf-8")
    file_handler.setLevel(logging.DEBUG)
    file_handler.setFormatter(formatter)
    logger.addHandler(file_handler)

    if verbose:
        console = logging.StreamHandler()
        console.setLevel(logging.DEBUG)
        console.setFormatter(formatter)
        logger.addHandler(console)

    return logger
