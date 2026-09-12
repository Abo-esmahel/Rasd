"""
Domain models for OpenCode API Manager.
No AI, purely deterministic state and algorithms.
"""
from __future__ import annotations

import enum
import uuid
from dataclasses import dataclass, field, asdict
from datetime import datetime, timezone
from typing import Optional, Dict, Any


def utcnow() -> datetime:
    return datetime.now(timezone.utc)


def utcnow_iso() -> str:
    return utcnow().isoformat()


class AuthStatus(str, enum.Enum):
    UNKNOWN = "UNKNOWN"
    READY = "READY"
    ACTIVE = "ACTIVE"
    INVALID = "INVALID"
    UNAUTHORIZED = "UNAUTHORIZED"
    STALE = "STALE"
    CHECKING = "CHECKING"
    RATE_LIMITED = "RATE_LIMITED"
    COOLDOWN = "COOLDOWN"
    FAILED = "FAILED"


class CapacitySource(str, enum.Enum):
    PROVIDER_API = "provider_api"
    RESPONSE_HEADERS = "response_headers"
    OPENCODE_CLI = "opencode_cli"
    UNKNOWN = "unknown"


class Freshness(str, enum.Enum):
    FRESH = "fresh"
    STALE = "stale"
    UNKNOWN = "unknown"


# TTL for capacity data: considered fresh for 10 minutes by default
CAPACITY_TTL_SECONDS = 10 * 60


@dataclass
class CapacityInfo:
    """Capacity remaining as percentage 0..100 or None if UNKNOWN."""
    percent: Optional[int] = None  # 0..100 or None
    source: CapacitySource = CapacitySource.UNKNOWN
    timestamp: Optional[str] = None  # ISO8601
    raw: Optional[Dict[str, Any]] = None  # raw provider response if any

    def is_unknown(self) -> bool:
        return self.percent is None

    def freshness(self, now: Optional[datetime] = None, ttl: int = CAPACITY_TTL_SECONDS) -> Freshness:
        if self.percent is None or not self.timestamp:
            return Freshness.UNKNOWN
        try:
            ts = datetime.fromisoformat(self.timestamp)
            if ts.tzinfo is None:
                ts = ts.replace(tzinfo=timezone.utc)
            n = now or utcnow()
            age = (n - ts).total_seconds()
            if age < 0:
                return Freshness.FRESH
            if age <= ttl:
                return Freshness.FRESH
            return Freshness.STALE
        except Exception:
            return Freshness.UNKNOWN

    def to_dict(self) -> Dict[str, Any]:
        return {
            "percent": self.percent,
            "source": self.source.value if isinstance(self.source, CapacitySource) else str(self.source),
            "timestamp": self.timestamp,
            "raw": self.raw,
        }

    @classmethod
    def from_dict(cls, d: Dict[str, Any]) -> "CapacityInfo":
        if not d:
            return cls()
        src = d.get("source", "unknown")
        try:
            source = CapacitySource(src)
        except ValueError:
            source = CapacitySource.UNKNOWN
        return cls(
            percent=d.get("percent"),
            source=source,
            timestamp=d.get("timestamp"),
            raw=d.get("raw"),
        )

    @classmethod
    def unknown(cls) -> "CapacityInfo":
        return cls(percent=None, source=CapacitySource.UNKNOWN, timestamp=None)


@dataclass
class CredentialMeta:
    """
    Metadata stored in JSON (no secrets).
    Mirrors spec: last_used_at, last_checked_at, capacity, capacity_source,
    capacity_timestamp, auth_status, rate_limit_status, cooldown_until,
    failure_count, last_failure
    """
    id: str = field(default_factory=lambda: uuid.uuid4().hex[:16])
    name: str = ""
    provider: str = "opencode"  # e.g., "opencode" (OpenCode Zen), "opencode-go"
    created_at: str = field(default_factory=utcnow_iso)
    last_used_at: Optional[str] = None
    last_checked_at: Optional[str] = None
    capacity: CapacityInfo = field(default_factory=CapacityInfo.unknown)
    auth_status: AuthStatus = AuthStatus.UNKNOWN
    rate_limit_status: str = "unknown"  # unknown | ok | limited
    cooldown_until: Optional[str] = None  # ISO8601
    failure_count: int = 0
    last_failure: Optional[str] = None  # ISO8601 or message

    def is_in_cooldown(self, now: Optional[datetime] = None) -> bool:
        if not self.cooldown_until:
            return False
        try:
            ts = datetime.fromisoformat(self.cooldown_until)
            if ts.tzinfo is None:
                ts = ts.replace(tzinfo=timezone.utc)
            n = now or utcnow()
            return n < ts
        except Exception:
            return False

    def is_invalid(self) -> bool:
        return self.auth_status in (AuthStatus.INVALID, AuthStatus.UNAUTHORIZED)

    def to_dict(self) -> Dict[str, Any]:
        return {
            "id": self.id,
            "name": self.name,
            "provider": self.provider,
            "created_at": self.created_at,
            "last_used_at": self.last_used_at,
            "last_checked_at": self.last_checked_at,
            "capacity": self.capacity.to_dict(),
            "auth_status": self.auth_status.value,
            "rate_limit_status": self.rate_limit_status,
            "cooldown_until": self.cooldown_until,
            "failure_count": self.failure_count,
            "last_failure": self.last_failure,
        }

    @classmethod
    def from_dict(cls, d: Dict[str, Any]) -> "CredentialMeta":
        cap = CapacityInfo.from_dict(d.get("capacity") or {})
        status_raw = d.get("auth_status", "UNKNOWN")
        try:
            auth = AuthStatus(status_raw)
        except ValueError:
            auth = AuthStatus.UNKNOWN
        return cls(
            id=d.get("id") or uuid.uuid4().hex[:16],
            name=d.get("name") or "",
            provider=d.get("provider") or "opencode",
            created_at=d.get("created_at") or utcnow_iso(),
            last_used_at=d.get("last_used_at"),
            last_checked_at=d.get("last_checked_at"),
            capacity=cap,
            auth_status=auth,
            rate_limit_status=d.get("rate_limit_status") or "unknown",
            cooldown_until=d.get("cooldown_until"),
            failure_count=int(d.get("failure_count") or 0),
            last_failure=d.get("last_failure"),
        )


@dataclass
class VerificationResult:
    """Result of verify() after activation."""
    status: AuthStatus
    message: str = ""
    raw: Optional[Dict[str, Any]] = None
