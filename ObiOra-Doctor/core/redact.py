"""Report anonymization and secret redaction."""

from __future__ import annotations

import copy
import re
from typing import Any

IPV4_PATTERN = re.compile(
    r"\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d?\d)\.){3}"
    r"(?:25[0-5]|2[0-4]\d|[01]?\d?\d)\b"
)
DOMAIN_PATTERN = re.compile(
    r"\b(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}\b"
)
SECRET_PATTERNS = [
    (re.compile(r"(?i)(password|passwd|secret|token|api[_-]?key)\s*[=:]\s*\S+"), r"\1=[REDACTED]"),
    (re.compile(r"(?i)DB_PASSWORD=\S+"), "DB_PASSWORD=[REDACTED]"),
    (re.compile(r"(?i)APP_KEY=\S+"), "APP_KEY=[REDACTED]"),
]
SENSITIVE_PATHS = [
    "/root/",
    "/home/",
    "/var/www/",
    "/etc/mysql/",
    "/etc/nginx/",
]


def redact_text(text: str, *, redact_ips: bool = True, redact_domains: bool = True) -> str:
    """Redact sensitive data from a text string."""

    result = text
    for pattern, replacement in SECRET_PATTERNS:
        result = pattern.sub(replacement, result)
    if redact_ips:
        result = IPV4_PATTERN.sub("[IP_REDACTED]", result)
    if redact_domains:
        result = DOMAIN_PATTERN.sub("[DOMAIN_REDACTED]", result)
    for path in SENSITIVE_PATHS:
        result = result.replace(path, "[PATH_REDACTED]/")
    return result


def redact_report_dict(data: dict[str, Any]) -> dict[str, Any]:
    """Return an anonymized copy of a report dictionary."""

    redacted = copy.deepcopy(data)
    if "host" in redacted:
        redacted["host"]["hostname"] = "[HOST_REDACTED]"
        redacted["host"]["platform"] = "[PLATFORM_REDACTED]"
    redacted["anonymized"] = True
    return _redact_value(redacted)


def _redact_value(value: Any) -> Any:
    """Recursively redact strings inside nested structures."""

    if isinstance(value, str):
        return redact_text(value)
    if isinstance(value, list):
        return [_redact_value(item) for item in value]
    if isinstance(value, dict):
        return {key: _redact_value(item) for key, item in value.items()}
    return value
