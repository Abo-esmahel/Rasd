from datetime import datetime, timezone, timedelta

from app.domain.models import CredentialMeta, CapacityInfo, CapacitySource
from app.selection.engine import SelectionEngine

def test_cache_fresh_stale_unknown():
    engine = SelectionEngine()
    now = datetime(2026, 9, 12, 12, 0, 0, tzinfo=timezone.utc)
    m_fresh = CredentialMeta(name="F")
    m_fresh.capacity = CapacityInfo(percent=50, source=CapacitySource.PROVIDER_API, timestamp=now.isoformat())
    assert not engine.should_refresh_capacity(m_fresh, now=now)

    m_stale = CredentialMeta(name="S")
    m_stale.capacity = CapacityInfo(percent=50, source=CapacitySource.PROVIDER_API, timestamp=(now - timedelta(minutes=20)).isoformat())
    assert engine.should_refresh_capacity(m_stale, now=now)

    m_unknown = CredentialMeta(name="U")
    assert engine.should_refresh_capacity(m_unknown, now=now)
