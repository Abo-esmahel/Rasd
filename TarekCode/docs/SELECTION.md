# Selection

Deterministic credential ranking — no AI, no randomness, no invented quota.

## Inputs

For each credential `CredentialMeta`:

```text
capacity.percent        → int 0..100 or None (UNKNOWN)
capacity.source         → provider_api | response_headers | opencode_cli | unknown
capacity.timestamp      → ISO8601 or None
capacity freshness      → fresh (≤10m) | stale (>10m) | unknown
auth_status             → UNKNOWN, READY, ACTIVE, INVALID, UNAUTHORIZED, etc.
cooldown_until          → ISO8601 or None
last_used_at            → ISO8601 or None
```

## Algorithm (SelectionEngine.rank)

```
1. Remove where auth_status in (INVALID, UNAUTHORIZED)
2. Remove where now < cooldown_until
3. Check if any remaining has reliable capacity:
     reliable = percent is not None
              and source != unknown
              and timestamp is set
              and freshness != UNKNOWN
              and 0 ≤ percent ≤ 100

4a. If any reliable:
      reliable_sorted = sort reliable by (-percent, last_used_at asc)
      unknown_sorted  = sort unknown  by (last_used_at asc)
      return reliable_sorted + unknown_sorted

4b. Else (all UNKNOWN):
      return sorted(all by last_used_at asc)  # oldest first = LRU
```

- Highest reliable capacity wins.
- Tie → Least Recently Used (oldest `last_used_at`).
- `None` last_used_at is treated as epoch 0 (oldest), so never-used credentials are preferred — this matches LRU intent.
- UNKNOWN is never coerced to 0 or 100; unreliable values are segregated to the end, still ordered by LRU.

## Examples

```text
A 20% (fresh)  used 5m ago
B 87% (fresh)  used 1h ago
C 43% (fresh)  used yesterday
→ picks B (highest reliable)
```

```text
A 80% fresh  used 10m ago
B 80% fresh  used 3h ago
C 80% fresh  used yesterday
→ picks C (tie → LRU)
```

```text
A UNKNOWN used 10m ago
B UNKNOWN used 3h ago
C UNKNOWN used yesterday
→ picks C (LRU)
```

```text
A 60% reliable  used yesterday
B UNKNOWN       used 10m ago
C 90% stale→ treated as UNKNOWN because freshness check fails → UNKNOWN
Mixed: reliable = [A], unknown = [B,C] sorted LRU → order: A, C, B
```

## Refresh Policy

- Fresh: capacity timestamp ≤ TTL (default 10 minutes) → use directly, no network.
- Stale: timestamp > TTL → refresh when necessary (during selection decision).
- Unknown: no timestamp → refresh when necessary.

Current provider has no usage endpoint, so refresh always yields `UNKNOWN` (honest). The TTL machinery exists for future use when an endpoint appears.

## Failover Interaction

SelectionEngine only ranks; failover is in ApplicationService:

```
rank → try #1 → verify
  success → update last_used_at, set active, DONE
  eligible failure (INVALID, UNAUTHORIZED, RATE_LIMITED, confirmed quota) → try #2
  non-eligible (timeout, exe missing, UNKNOWN) → stop, don't burn cooldown
```

`cooldown_until` is set for rate-limited cases (default 30 min) and filters future ranks.

## Testing

See `tests/test_selection.py`: covers highest capacity, LRU fallback, tie, UNKNOWN, invalid filter, cooldown filter, stale handling, deterministic ordering.
