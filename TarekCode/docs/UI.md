# UI

Spec §15: Beautiful, formal, compact, professional — not dashboard, not gamer.

## Layout (420×620 fixed)

```
┌──────────────────────────────────────────┐
│ OpenCode API Manager                ⚙    │  Header: title + subtitle + gear (Diagnostics)
├──────────────────────────────────────────┤
│ ┌ Active Card (white, rounded 10px)    │  ACTIVE
│ │ ACTIVE  ●                             │  Active name (bold 15px)
│ │ Personal 02                           │  Provider (12px, #656D76)
│ │ OpenCode Zen                          │  Remaining capacity (11px)
│ │ Remaining capacity                    │  Bar (8px height)  82%
│ │ ████████████████░░░░  82%             │  Color: #1F2328 ≥50%, #9A6700 20-50%, #CF222E <20%
│ │          [  SWITCH  ]                 │  40px, #1F2328, 13px bold, hover #2D333B
│ │          Selecting… / ✓ Done          │  Status line (centered)
│ └───────────────────────────────────────┘
│ APIs  [3]                                │  Header count pill
│ ┌─────────────────────────────────────┐ │
│ │ ● Personal 01   24%   Ready    ⋮   │ │  Row: 54px, white, border #E6E6E6, radius 6px
│ │ ● Personal 02   82%   Active   ⋮   │ │  Bullet color: ACTIVE #1A7F37, READY #0969DA, invalid #CF222E
│ │ ● Personal 03    —   Unknown   ⋮   │ │  Capacity: 12px, UNKNOWN #8B949E gray, "—"
│ └─────────────────────────────────────┘ │  Pill status: Active green #DDFFF0, Ready gray, Invalid red
│                                          │
│ [+  Add API]                [⋮ More]    │  Footer buttons 34px
└──────────────────────────────────────────┘
```

## Colors

- Background: `#F6F8FA` (canvas), `#FFFFFF` (cards)
- Text: `#1F2328` (primary), `#656D76` (secondary), `#8B949E` (muted)
- Border: `#D0D7DE` / `#E6E6E6`
- Accent/primary button: `#1F2328` (near-black), hover `#2D333B`
- Success: `#1A7F37`, Error: `#CF222E`, Warning: `#9A6700`

## Fonts

- Family: `Segoe UI` (Windows system)
- Title 14px bold, Active name 15px bold, Section 12px 600, Body 11-12px

## Interactions

- **SWITCH**: large centered, disabled while switching or no eligible APIs. Shows `SWITCHING…` + spinner-like text. Emits progress messages from `SwitchWorker`.
- **+ Add API**: opens `AddApiDialogInline` (380×260): Name, Provider (combo editable, default `opencode`), API Key (password). Secret never shown after.
- **Row ⋮**: context menu → `Copy fingerprint` (copies non-usable `sk-••••...a3f9`), `Remove …` (confirm).
- **⚙**: opens `DiagnosticsDialog` (420×380) with rows: OpenCode Installed, Version, Auth integration, Usage information, Capacity source, file existence. Displays `— Not available` for usage honestly.
- **⋮ More**: menu → Diagnostics…, Refresh, About.
- **List placeholder**: when empty, shows dashed box “No APIs yet. Click + Add API…”.

## Responsive / Performance

- `refresh_ui()` is instant (reads local JSON, no network)
- `background_refresh` runs in `QThread` (RefreshWorker) so UI never waits
- `SwitchWorker` in `QThread`; UI thread only updates progress label
- Timer refresh every 30s keeps LRU/freshness indicators current

## Accessibility

- High contrast bullets + pill labels (not only color)
- Keyboard: Tab cycles Add/Switch/More; Enter activates
- No animations that block; subtle hover only

## Non-goals

- No gradients, no rounded boxes everywhere, no sidebars, no huge dashboard, no animations.
- No web view, no embedded browser.
