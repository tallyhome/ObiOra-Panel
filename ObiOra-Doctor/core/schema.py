"""JSON schema validation for Obiora Doctor reports."""

from __future__ import annotations

from typing import Any

REPORT_SCHEMA_VERSION = "1.0"

REQUIRED_REPORT_KEYS = {"version", "generated_at", "host", "score", "results"}
REQUIRED_MODULE_KEYS = {"module", "status", "score", "findings", "duration_ms"}
REQUIRED_FINDING_KEYS = {"level", "title", "details", "recommendation"}


def validate_report(data: dict[str, Any]) -> list[str]:
    """Validate a report dictionary against the schema.

    Parameters:
        data: Report dictionary to validate.

    Returns:
        List of validation error messages. Empty if valid.
    """

    errors: list[str] = []
    missing = REQUIRED_REPORT_KEYS - set(data.keys())
    if missing:
        errors.append(f"Missing report keys: {', '.join(sorted(missing))}")

    for index, result in enumerate(data.get("results", [])):
        module_errors = REQUIRED_MODULE_KEYS - set(result.keys())
        if module_errors:
            errors.append(f"Module {index}: missing {', '.join(sorted(module_errors))}")
        for fidx, finding in enumerate(result.get("findings", [])):
            finding_errors = REQUIRED_FINDING_KEYS - set(finding.keys())
            if finding_errors:
                errors.append(
                    f"Module {index} finding {fidx}: missing "
                    f"{', '.join(sorted(finding_errors))}"
                )
    return errors
