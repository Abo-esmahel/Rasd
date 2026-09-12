# Caching

Per spec §8, §21: do not re-check every API on every Switch.

## Stored per Credential

```text
last_used_at
last_checked_at
capacity.percent        // 0..100 or None
capacity.source         // provider_api | response_headers | opencode_cli | unknown
capacity.timestamp      // ISO8601 when sampled
auth_status
rate_limit_status       // unknown | ok | limited
cooldown_until
failure_count
last_failure
```

`capacity` includes `raw` field for provider payload if ever available (currently null).

## Freshness

```
FRESH   → timestamp within TTL (default 10 minutes)
STALE   → timestamp older than TTL
UNKNOWN → percent is None or timestamp missing or parse failed
```

TTL is configurable via `settings.json` → `capacity_ttl_seconds` (default 600). `MetadataStore` loads it but SelectionEngine currently uses passed `ttl`.

Decision (`SelectionEngine.should_refresh_capacity`):

```
should_refresh = freshness in (STALE, UNKNOWN)
```

Only when `should_refresh` is true does `ApplicationService.refresh_capacity_if_needed()` actually call `OpenCodeAdapter.get_usage()` (currently no network). Otherwise uses cached `capacity.percent`.

## When Refresh Happens

- On `refresh_ui` via `RefreshWorker` background thread: iterates all metas, refreshes stale/unknown.
- During `switch()`, before ranking, no forced refresh of all candidates; only the selected candidate's capacity is implicitly fresh enough for ranking. If capacity is stale, the engine still ranks using stale values (not optimal but deterministic) — but a background refresh will soon update it. Future: optionally refresh top N candidates on demand.

This avoids N network requests per Switch (spec §8).

## Reset Time Handling

If provider ever returns `{remaining, limit, reset_at}`, adapter would store `reset_at` and `limit` in `capacity.raw` + `timestamp`, and could locally compute freshness without network until `reset_at` passes. Implementation placeholder exists (`capacity.raw`), but no endpoint currently provides such fields, so no local inference is done. Never infer quota from incomplete data.

## Persistence

- `accounts.json` persists all fields above.
- On restart, `last_used_at` ordering is preserved, so LRU works across sessions (spec §26: Restart persistence).
- `capacity_timestamp` persists so freshness survives restart.

## Concurrency

Refresh runs in `QThread` (not UI thread) per spec §18. UI remains responsive. Atomic writes protect against concurrent file updates (Switch vs Refresh).
