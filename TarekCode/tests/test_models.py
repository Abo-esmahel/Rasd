from datetime import datetime, timezone, timedelta

from app.domain.models import CredentialMeta, CapacityInfo, CapacitySource, Freshness, AuthStatus

def test_capacity_unknown():
    c = CapacityInfo.unknown()
    assert c.is_unknown()
    assert c.freshness() == Freshness.UNKNOWN

def test_capacity_freshness():
    now = datetime.now(timezone.utc)
    c = CapacityInfo(percent=50, source=CapacitySource.PROVIDER_API, timestamp=now.isoformat())
    assert c.freshness(now=now) == Freshness.FRESH
    old = (now - timedelta(minutes=20)).isoformat()
    c2 = CapacityInfo(percent=50, source=CapacitySource.PROVIDER_API, timestamp=old)
    assert c2.freshness(now=now) == Freshness.STALE

def test_credential_metadata_roundtrip():
    c = CredentialMeta(name="Test", provider="opencode")
    d = c.to_dict()
    m2 = CredentialMeta.from_dict(d)
    assert m2.name == "Test"
    assert m2.provider == "opencode"
    assert m2.capacity.is_unknown()

def test_cooldown_detection():
    m = CredentialMeta(name="X")
    future = (datetime.now(timezone.utc) + timedelta(minutes=10)).isoformat()
    m.cooldown_until = future
    assert m.is_in_cooldown()
    past = (datetime.now(timezone.utc) - timedelta(minutes=10)).isoformat()
    m.cooldown_until = past
    assert not m.is_in_cooldown()

def test_invalid_detection():
    m = CredentialMeta(name="X", auth_status=AuthStatus.INVALID)
    assert m.is_invalid()
    m.auth_status = AuthStatus.READY
    assert not m.is_invalid()
