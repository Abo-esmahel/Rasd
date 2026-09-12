"""
Secret redaction utilities. Never expose real API keys.
"""
from __future__ import annotations

import re
from typing import Any

# Pattern for sk- style keys and generic long tokens
_SECRET_RE = re.compile(r"sk-[A-Za-z0-9_\-]{8,}")

def redact_text(text: str) -> str:
    """Redact any string that looks like an API key."""
    if not text:
        return text
    # Replace sk- keys
    redacted = _SECRET_RE.sub("sk-••••••••", text)
    # Also redact long base64-like tokens outside sk- pattern if needed
    # But keep it conservative: only sk- pattern for now to avoid false positives
    return redacted

def redact_dict(obj: Any) -> Any:
    """Recursively redact dict/list/str values."""
    if isinstance(obj, dict):
        out = {}
        for k, v in obj.items():
            if "key" in k.lower() or "secret" in k.lower() or "token" in k.lower():
                out[k] = "••••••••"
            else:
                out[k] = redact_dict(v)
        return out
    if isinstance(obj, list):
        return [redact_dict(x) for x in obj]
    if isinstance(obj, str):
        return redact_text(obj)
    return obj

def fingerprint(key: str) -> str:
    """
    Short non-usable fingerprint for UI, e.g. 'sk-••••...a3f9'
    Never returns full key.
    """
    if not key:
        return "••••••••"
    key = key.strip()
    if len(key) <= 8:
        return "••••••••"
    # show last 4 chars of suffix after prefix
    # keep prefix type (sk-)
    prefix = key[:3] if key.startswith("sk-") else key[:2]
    suffix = key[-4:]
    return f"{prefix}••••...{suffix}"
