from __future__ import annotations

from datetime import datetime, timezone, timedelta

def utcnow() -> datetime:
    return datetime.now(timezone.utc)

def parse_iso(s: str | None) -> datetime | None:
    if not s:
        return None
    try:
        dt = datetime.fromisoformat(s)
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=timezone.utc)
        return dt
    except Exception:
        return None

def isoformat(dt: datetime | None) -> str | None:
    if dt is None:
        return None
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.isoformat()

def age_seconds(iso: str | None, now: datetime | None = None) -> float | None:
    dt = parse_iso(iso)
    if dt is None:
        return None
    n = now or utcnow()
    return (n - dt).total_seconds()
