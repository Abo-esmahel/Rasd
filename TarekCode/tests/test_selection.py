"""
Tests for SelectionEngine — deterministic ranking.

Spec §6, §7, §9, §20
"""
import pytest
from datetime import datetime, timezone, timedelta

from app.domain.models import CredentialMeta, CapacityInfo, CapacitySource, AuthStatus
from app.selection.engine import SelectionEngine
from app.utils.time import utcnow

def now():
    return datetime(2026, 9, 12, 12, 0, 0, tzinfo=timezone.utc)

def make_meta(name, capacity_percent, last_used_delta_minutes, source=CapacitySource.PROVIDER_API):
    # capacity: if percent None => unknown
    if capacity_percent is None:
        cap = CapacityInfo.unknown()
    else:
        ts = (now() - timedelta(minutes=1)).isoformat()  # fresh
        cap = CapacityInfo(percent=capacity_percent, source=source, timestamp=ts)
    lu = (now() - timedelta(minutes=last_used_delta_minutes)).isoformat() if last_used_delta_minutes is not None else None
    return CredentialMeta(
        name=name,
        provider="opencode",
        capacity=cap,
        last_used_at=lu,
        auth_status=AuthStatus.READY,
    )

def test_highest_capacity_wins():
    engine = SelectionEngine()
    metas = [
        make_meta("Personal 01", 20, 10),
        make_meta("Personal 02", 87, 30),
        make_meta("Personal 03", 43, 60),
    ]
    ranked = engine.rank(metas, now=now())
    assert ranked[0].name == "Personal 02"
    assert ranked[1].name == "Personal 03"
    assert ranked[2].name == "Personal 01"

def test_capacity_tie_lru():
    engine = SelectionEngine()
    metas = [
        make_meta("Personal 01", 80, 10),
        make_meta("Personal 02", 80, 180),
        make_meta("Personal 03", 80, 1440),
    ]
    ranked = engine.rank(metas, now=now())
    assert ranked[0].name == "Personal 03"  # used yesterday
    assert ranked[1].name == "Personal 02"
    assert ranked[2].name == "Personal 01"

def test_unknown_capacity_lru():
    engine = SelectionEngine()
    metas = [
        make_meta("Personal 01", None, 10),
        make_meta("Personal 02", None, 180),
        make_meta("Personal 03", None, 1440),
    ]
    ranked = engine.rank(metas, now=now())
    assert ranked[0].name == "Personal 03"
    assert ranked[1].name == "Personal 02"
    assert ranked[2].name == "Personal 01"

def test_mixed_reliable_first_then_unknown_lru():
    engine = SelectionEngine()
    # A reliable, B unknown, C reliable but lower
    a = make_meta("A", 60, 1440)
    b = make_meta("B", None, 10)
    c = make_meta("C", 90, 5)
    # Actually C should win despite recent use because higher capacity
    ranked = engine.rank([a, b, c], now=now())
    assert ranked[0].name == "C"
    assert ranked[1].name == "A"
    assert ranked[2].name == "B"

def test_invalid_filtered():
    engine = SelectionEngine()
    inv = make_meta("Invalid", 90, 1440)
    inv.auth_status = AuthStatus.INVALID
    ok = make_meta("OK", 10, 10)
    ranked = engine.rank([inv, ok], now=now())
    assert len(ranked) == 1
    assert ranked[0].name == "OK"

def test_cooldown_filtered():
    engine = SelectionEngine()
    cool = make_meta("Cool", 90, 1440)
    cool.cooldown_until = (now() + timedelta(minutes=30)).isoformat()
    ok = make_meta("OK", 10, 10)
    ranked = engine.rank([cool, ok], now=now())
    assert len(ranked) == 1
    assert ranked[0].name == "OK"

def test_unknown_not_treated_as_zero():
    engine = SelectionEngine()
    unknown = make_meta("Unknown", None, 1440)
    low = make_meta("Low", 5, 10)
    # Low has reliable 5%, Unknown is not comparable — reliable wins? Actually per algorithm reliable first
    # But even if low is 5%, it should win over UNKNOWN, not the other way
    ranked = engine.rank([unknown, low], now=now())
    # Reliable (Low 5%) should be before Unknown despite higher "would-be" zero
    assert ranked[0].name == "Low"
    assert ranked[1].name == "Unknown"

def test_unknown_not_treated_as_100():
    engine = SelectionEngine()
    unknown = make_meta("Unknown", None, 10)
    high = make_meta("High", 95, 1440)
    ranked = engine.rank([unknown, high], now=now())
    assert ranked[0].name == "High"
    assert ranked[1].name == "Unknown"

def test_stale_capacity_still_reliable_beats_unknown():
    engine = SelectionEngine()
    # stale timestamp >10m is FRESHNESS=STALE but still has reliable source/percent
    stale = make_meta("Stale", 90, 10)
    stale.capacity.timestamp = (now() - timedelta(minutes=20)).isoformat()
    unknown = make_meta("Unknown", None, 1440)
    # stale still has reliable capacity (percent=90, source=provider_api) -> beats UNKNOWN
    ranked = engine.rank([stale, unknown], now=now())
    assert ranked[0].name == "Stale"  # reliable 90% beats UNKNOWN
    assert ranked[1].name == "Unknown"

def test_none_last_used_is_oldest():
    engine = SelectionEngine()
    never = make_meta("Never", None, None)
    recent = make_meta("Recent", None, 5)
    ranked = engine.rank([recent, never], now=now())
    assert ranked[0].name == "Never"

def test_select_best():
    engine = SelectionEngine()
    metas = [make_meta("A", 10, 10), make_meta("B", 90, 10)]
    best = engine.select_best(metas, now=now())
    assert best.name == "B"

def test_empty():
    engine = SelectionEngine()
    assert engine.rank([], now=now()) == []
    assert engine.select_best([], now=now()) is None
