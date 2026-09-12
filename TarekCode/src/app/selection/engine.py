"""
SelectionEngine — deterministic ranking per spec §6, §7, §9, §20.

Algorithm:

1. Remove credentials known to be invalid.
2. Remove credentials currently under cooldown.
3. Refresh stale capacity only when necessary (caller handles TTL; engine uses provided values).
4. If reliable capacity exists (percent is not None AND source != UNKNOWN AND freshness != UNKNOWN):
       highest remaining capacity wins.
5. If capacities tie: least recently used wins (oldest last_used_at).
6. If capacity is unavailable (all UNKNOWN): least recently used wins.
7. Return ranked candidates.

No random, no fixed priority, no invented balance.
Capacity == UNKNOWN never treated as 0% or 100% (§5).

Failure handling: caller marks cooldown/invalid; engine filters accordingly.
"""
from __future__ import annotations

from datetime import datetime, timezone
from typing import List, Optional, Tuple

from app.domain.models import CredentialMeta, AuthStatus, Freshness
from app.utils.time import parse_iso


def _parse_time(s: Optional[str]) -> Optional[datetime]:
    if not s:
        return None
    return parse_iso(s)


def _is_reliable_capacity(meta: CredentialMeta) -> bool:
    cap = meta.capacity
    if cap.is_unknown():
        return False
    # source must be known and freshness not UNKNOWN
    if cap.source.value == "unknown":
        return False
    # If no timestamp, consider unknown
    if not cap.timestamp:
        return False
    # freshness check: if UNKNOWN, not reliable
    if cap.freshness() == Freshness.UNKNOWN:
        return False
    # percent must be 0..100
    if cap.percent is None or not (0 <= cap.percent <= 100):
        return False
    return True


class SelectionEngine:
    """
    Stateless deterministic engine.
    """

    def rank(
        self,
        candidates: List[CredentialMeta],
        now: Optional[datetime] = None,
         # for testability
    ) -> List[CredentialMeta]:
        """
        Return candidates ranked best-first according to spec.
        Filters invalid and cooldown before ranking.
        """
        now = now or datetime.now(timezone.utc)

        # 1. Remove invalid
        eligible = [c for c in candidates if not c.is_invalid()]

        # 2. Remove cooldown
        eligible = [c for c in eligible if not c.is_in_cooldown(now)]

        if not eligible:
            return []

        # Determine if ANY candidate has reliable capacity
        any_reliable = any(_is_reliable_capacity(c) for c in eligible)

        if any_reliable:
            # Partition: reliable vs unknown? Unknown treated as lowest?
            # Spec: highest remaining capacity wins. Unknown is not a numeric value,
            # so we rank reliable candidates first, then unknown sorted by LRU.
            # This respects §5: UNKNOWN ≠ 0% and not comparable.
            reliable = [c for c in eligible if _is_reliable_capacity(c)]
            unknown = [c for c in eligible if not _is_reliable_capacity(c)]

            # Sort reliable: highest capacity first, tie -> oldest last_used_at
            def key_reliable(c: CredentialMeta) -> Tuple[int, float]:
                # negative capacity for descending, then last_used timestamp asc
                cap = c.capacity.percent or 0
                tu = _parse_time(c.last_used_at)
                # None => very old (least recently used wins, so older first)
                # Convert to epoch seconds, None => 0 (oldest)
                epoch = tu.timestamp() if tu else 0.0
                return (-cap, epoch)

            reliable_sorted = sorted(reliable, key=key_reliable)

            # Sort unknown by LRU (oldest last_used_at first)
            def key_lru(c: CredentialMeta) -> float:
                tu = _parse_time(c.last_used_at)
                return tu.timestamp() if tu else 0.0

            unknown_sorted = sorted(unknown, key=key_lru)

            return reliable_sorted + unknown_sorted
        else:
            # All UNKNOWN -> LRU
            def key_lru_all(c: CredentialMeta) -> float:
                tu = _parse_time(c.last_used_at)
                return tu.timestamp() if tu else 0.0

            return sorted(eligible, key=key_lru_all)

    def select_best(self, candidates: List[CredentialMeta], now: Optional[datetime] = None) -> Optional[CredentialMeta]:
        ranked = self.rank(candidates, now=now)
        return ranked[0] if ranked else None

    def should_refresh_capacity(self, meta: CredentialMeta, now: Optional[datetime] = None, ttl: int = 600) -> bool:
        """
        Decide if capacity should be refreshed.
        Uses freshness TTL; only stale or unknown should be refreshed when necessary (during selection).
        """
        from app.domain.models import CAPACITY_TTL_SECONDS

        ttl = ttl or CAPACITY_TTL_SECONDS
        fresh = meta.capacity.freshness(now=now, ttl=ttl)
        return fresh in (Freshness.STALE, Freshness.UNKNOWN)
