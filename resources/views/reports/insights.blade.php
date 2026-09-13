@extends('layouts.app')

@section('content')
<style>
/* ===== لوحة المؤشرات: نظام مدمج، ريسبونسيف، بلا تسريب ===== */
.ins-root{width:100%;max-width:100%;min-width:0;overflow-x:clip;display:grid;gap:10px;container-type:inline-size;font-family:'IBM Plex Sans Arabic','Almarai','Inter','Segoe UI',Tahoma,sans-serif;text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased}
.ins-root *,.ins-root *::before,.ins-root *::after{box-sizing:border-box;max-width:100%}
.ins-root .tabular-nums{font-family:'IBM Plex Sans Arabic','Inter','Almarai',sans-serif;font-variant-numeric:tabular-nums lining-nums;font-feature-settings:'tnum' 1,'lnum' 1;letter-spacing:.01em}
.ins-card{background:#fdfcfa;border-radius:14px;box-shadow:0 1px 2px rgba(26,46,31,.05);min-width:0;overflow:hidden}
.ins-pad{padding:12px}
@media(min-width:640px){.ins-pad{padding:16px}.ins-root{gap:12px}}

/* ترويسة */
.ins-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;min-width:0}
.ins-head-txt{min-width:0;flex:1}
.ins-crumb{font-size:11px;font-weight:500;color:#6b7a6e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ins-title{font-family:'IBM Plex Sans Arabic','Almarai','Inter',sans-serif;font-size:19px;font-weight:700;color:#1a2e1f;line-height:1.5;margin-top:2px;letter-spacing:-.015em;overflow-wrap:break-word}
@media(min-width:640px){.ins-title{font-size:22px}}
.ins-sub{font-size:12.5px;color:#6b7a6e;margin-top:2px;line-height:1.8;overflow-wrap:break-word;font-weight:400}
.ins-back{flex:none;display:inline-flex;align-items:center;gap:6px;min-height:36px;max-width:44vw;padding:0 12px;border-radius:11px;background:#fdfcfa;border:1px solid #e6e9e1;font-size:12.5px;font-weight:800;color:#4a5a4f;box-shadow:0 1px 2px rgba(26,46,31,.05)}
.ins-back:hover{border-color:#0e6a38;color:#0e6a38}

/* النطاق */
.ins-range{padding:8px;display:grid;gap:8px}
.ins-pills{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;padding:4px;border-radius:11px;background:#f1f3f0;min-width:0}
.ins-pill{display:flex;align-items:center;justify-content:center;min-width:0;min-height:36px;padding:0 6px;border-radius:8px;font-size:13px;font-weight:600;color:#4a5a4f;border:1px solid transparent;white-space:nowrap;overflow:hidden;font-family:'IBM Plex Sans Arabic','Almarai',sans-serif}
.ins-pill:hover{color:#0e6a38}
.ins-pill.is-on{background:#fdfcfa;color:#0e6a38;box-shadow:0 1px 2px rgba(26,46,31,.08);border-color:#e6e9e1;font-weight:700}
.ins-range-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:0 4px;font-size:11.5px;font-weight:500;color:#6b7a6e;min-width:0;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}

/* البطل */
.ins-hero{padding:12px;display:flex;flex-direction:column;gap:12px}
@media(min-width:640px){.ins-hero{padding:16px;flex-direction:row;align-items:center}}
.ins-hero-ring{display:flex;align-items:center;gap:12px;min-width:0}
@media(min-width:640px){.ins-hero-ring{flex-direction:column;gap:6px;flex:none;width:140px;border-inline-end:1px solid #e6e9e1;padding-inline-end:12px;text-align:center}}
.ins-donut{position:relative;width:84px;height:84px;flex:none}
@media(min-width:640px){.ins-donut{width:96px;height:96px}}
.ins-donut-c{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
#ins-health-num{font-family:'IBM Plex Sans Arabic','Inter','Almarai',sans-serif;font-size:26px;font-weight:700;color:#1a2e1f;font-variant-numeric:tabular-nums lining-nums;line-height:1;letter-spacing:-.02em}
#ins-health-num small{font-size:13px;font-weight:600}
.ins-hero-lvl{min-width:0}
@media(min-width:640px){.ins-hero-lvl{text-align:center}}
.ins-lbl{font-size:11px;font-weight:700;color:#6b7a6e}
.ins-health-badge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;color:var(--hc,#0e6a38);background:color-mix(in srgb,var(--hc,#0e6a38) 9%,#fff);border:1px solid color-mix(in srgb,var(--hc,#0e6a38) 28%,transparent);border-radius:999px;padding:3px 10px;max-width:100%}
.ins-health-badge::before{content:'';width:7px;height:7px;border-radius:99px;background:var(--hc,#0e6a38);flex:none}
.ins-hero-txt{flex:1;min-width:0}
.ins-brief1{font-family:'IBM Plex Sans Arabic','Almarai','Inter',sans-serif;font-size:14px;font-weight:700;line-height:1.9;color:#1a2e1f;overflow-wrap:break-word;letter-spacing:-.01em}
@media(min-width:640px){.ins-brief1{font-size:15px}}
.ins-brief2{margin-top:4px;font-size:12.5px;line-height:1.9;font-weight:400;color:#4a5a4f;overflow-wrap:break-word}
.ins-peak-dot{display:inline-block;width:6px;height:6px;border-radius:99px;background:#0e6a38;margin:0 2px;vertical-align:middle}
.ins-chips{margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-width:0}
.ins-chip{display:inline-flex;align-items:center;gap:6px;max-width:100%;font-size:11.5px;font-weight:600;color:#1a2e1f;background:#f6f7f5;border:1px solid #e6e9e1;border-radius:999px;padding:5px 11px;white-space:nowrap;font-variant-numeric:tabular-nums}
.ins-chip-k{color:#6b7a6e;font-weight:500}
.ins-chip-note{font-size:10px;font-weight:500;color:#9aa99a}
.ins-delta-up{color:#0e6a38}.ins-delta-down{color:#b45309}.ins-delta-flat{color:#6b7a6e}

/* عناوين الأقسام */
.ins-sec{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;min-width:0}
.ins-h2{font-family:'IBM Plex Sans Arabic','Almarai','Inter',sans-serif;font-size:14px;font-weight:700;color:#1a2e1f;min-width:0;overflow-wrap:break-word;letter-spacing:-.01em}
@media(min-width:640px){.ins-h2{font-size:15px}}
.ins-sec-hint{font-size:11px;font-weight:700;color:#6b7a6e;flex:none}
.ins-sec-hint b{color:#1a2e1f}

/* شبكات KPI — مفتاح منع التسريب: minmax(0,1fr) */
.ins-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;min-width:0}
@media(min-width:1024px){.ins-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}}
.ins-grid-2{grid-template-columns:minmax(0,1fr)}
@media(min-width:1024px){.ins-grid-2{grid-template-columns:minmax(0,2fr) minmax(0,3fr)}}
.ins-grid > *{min-width:0}
.ins-kpi{padding:10px 12px}
@media(min-width:640px){.ins-kpi{padding:14px}}
.ins-kpi-k{font-size:11.5px;font-weight:500;color:#6b7a6e;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.2em;letter-spacing:0}
.ins-kpi-v{margin-top:4px;font-family:'IBM Plex Sans Arabic','Inter','Almarai',sans-serif;font-size:30px;line-height:1.1;font-weight:700;font-variant-numeric:tabular-nums lining-nums;letter-spacing:-.02em;color:#1a2e1f;overflow-wrap:anywhere}
@media(min-width:640px){.ins-kpi-v{font-size:34px}}
.ins-kpi-v.ins-green{color:#0e6a38}
.ins-kpi-s{margin-top:4px;font-size:11.5px;color:#6b7a6e;font-weight:500;line-height:1.7;overflow-wrap:break-word;font-variant-numeric:tabular-nums}
.ins-kpi-s b{color:#1a2e1f;font-weight:700}
.ins-rate{border-top:3px solid #0e6a38}
.ins-kpi.is-warn{border-color:#e8c468}
.ins-amber{color:#b45309 !important}.ins-amber-text{color:#b45309 !important}
.ins-link{color:#0e6a38;font-weight:800}.ins-link:hover{text-decoration:underline}
.ins-bar{margin-top:8px;height:6px;border-radius:99px;background:#eef2ee;overflow:hidden}
.ins-bar-fill{height:100%;border-radius:99px;background:#0e6a38;transition:width .5s ease}

/* الإجراءات */
.ins-boxhead{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;min-width:0}
.ins-boxhead-wrap{flex-wrap:wrap}
.ins-count{font-size:11px;font-weight:800;padding:2px 9px;border-radius:999px;background:#f1f3f0;color:#4a5a4f}
.ins-muted-s{font-size:10.5px;font-weight:700;color:#6b7a6e}
.ins-acts{display:grid;gap:8px;min-width:0}
.ins-act{display:flex;gap:10px;padding:10px;border:1px solid #e6e9e1;border-radius:12px;background:#fff;min-width:0}
.ins-act-n{font-family:'IBM Plex Sans Arabic','Inter',sans-serif;font-size:12px;font-weight:700;color:#9aa99a;font-variant-numeric:tabular-nums lining-nums;padding-top:2px;flex:none;letter-spacing:.02em}
.ins-act-body{flex:1;min-width:0}
.ins-act-top{display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0}
.ins-act-t{font-family:'IBM Plex Sans Arabic','Almarai',sans-serif;font-size:13.5px;font-weight:700;color:#1a2e1f;line-height:1.8;overflow-wrap:break-word;flex:1;min-width:0}
.ins-act-d{margin-top:2px;font-size:12.5px;line-height:1.9;color:#4a5a4f;overflow-wrap:break-word;font-weight:400}
.ins-lvl{font-size:10px;font-weight:800;border-radius:999px;padding:2px 8px;border:1px solid transparent;white-space:nowrap;flex:none}
.ins-lvl-critical{color:#b91c1c;background:#fef2f2;border-color:#fecaca}
.ins-lvl-high{color:#92400e;background:#fffbeb;border-color:#f3dfa8}
.ins-lvl-medium,.ins-lvl-ok{color:#0e6a38;background:#eef4f0;border-color:#cde7d6}
.ins-lvl-info{color:#4a5a4f;background:#f6f7f5;border-color:#e6e9e1}
.ins-act-link{display:inline-flex;align-items:center;gap:3px;margin-top:4px;font-size:11px;font-weight:800;color:#0e6a38;min-height:28px}
.ins-act-link:hover{text-decoration:underline}

/* الرسم — سكرول داخلي محصور، بلا تسريب للصفحة */
.ins-legend{margin-inline-start:auto;display:flex;align-items:center;gap:10px;font-size:11px;font-weight:700;color:#6b7a6e;flex:none}
.ins-legend-i{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
.ins-dot{width:10px;height:10px;border-radius:3px;flex:none;display:inline-block}
.ins-dot-a{background:#0e6a38}.ins-dot-b{background:#bcd8c4}.ins-dot-b2{background:#d9a821}.ins-dot-c{background:#d1d5d1}
.ins-chart-wrap{max-width:100%;min-width:0;overflow-x:auto;overscroll-behavior-x:contain;scrollbar-width:thin;padding:10px 2px 4px}
.ins-chart{display:flex;align-items:flex-end;gap:6px;width:max-content;min-width:100%;min-height:132px;margin:0 auto}
@media(min-width:640px){.ins-chart{gap:8px}}
.ins-col{flex:1 0 auto;min-width:26px;max-width:52px;width:100%;display:flex;flex-direction:column;align-items:center;gap:6px}
.ins-bars{display:flex;align-items:flex-end;justify-content:center;gap:3px;height:100px}
.ins-b{width:9px;border-radius:4px 4px 2px 2px}
@media(min-width:640px){.ins-b{width:12px}}
.ins-b-a{background:#0e6a38}.ins-b-b{background:#bcd8c4}
.ins-col.is-peak .ins-col-lbl{color:#0e6a38;font-weight:800}
.ins-b.is-peak-bar{outline:2px solid #0e6a38;outline-offset:1px}
.ins-col-lbl{font-size:10px;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;color:#6b7a6e}
.ins-trend-foot{margin-top:8px;padding-top:8px;border-top:1px solid #e6e9e1;display:grid;gap:2px;min-width:0}
.ins-peak-line{font-size:11px;font-weight:800;color:#0e6a38;overflow-wrap:break-word}
.ins-empty{text-align:center;font-size:13px;font-weight:700;color:#6b7a6e;padding:28px 12px}

/* التوزيع — هوية واحدة */
.ins-h3{font-size:12px;font-weight:800;color:#1a2e1f;margin-bottom:10px}
.ins-dist{height:10px;border-radius:99px;overflow:hidden;display:flex;background:#f1f3f0;max-width:100%}
.ins-seg{height:100%;transition:width .5s ease;min-width:0}
.ins-seg-a{background:#0e6a38}.ins-seg-b{background:#d9a821}.ins-seg-c{background:#cfd4cf}
.ins-dist-legend{margin-top:10px;display:grid;gap:6px;font-size:11px;font-weight:700;color:#4a5a4f;min-width:0}
.ins-dist-legend p{display:flex;align-items:center;gap:6px;min-width:0;overflow-wrap:break-word}
.ins-nums{margin-inline-start:auto;font-variant-numeric:tabular-nums;flex:none}

/* دارك مود موحد */
html.dark .ins-card{background-color:#232926 !important;box-shadow:none !important}
html.dark .ins-title,html.dark .ins-h2,html.dark .ins-h3{color:#e7ece5 !important}
html.dark .ins-sub,html.dark .ins-crumb,html.dark .ins-kpi-k,html.dark .ins-kpi-s,html.dark .ins-sec-hint,html.dark .ins-range-meta,html.dark .ins-lbl,html.dark .ins-muted-s,html.dark .ins-legend,html.dark .ins-col-lbl{color:#9bb0a0 !important}
html.dark .ins-brief1{color:#e7ece5 !important}
html.dark .ins-brief2,html.dark .ins-act-d,html.dark .ins-dist-legend{color:#c3cec4 !important}
html.dark .ins-kpi-s b,html.dark .ins-sec-hint b,html.dark #ins-health-num,html.dark .ins-act-t{color:#e7ece5 !important}
html.dark .ins-kpi-v{color:#e7ece5 !important}
html.dark .ins-kpi-v.ins-green{color:#4ade80 !important}
html.dark .ins-amber,html.dark .ins-amber-text{color:#f0c040 !important}
html.dark .ins-link,html.dark .ins-act-link,html.dark .ins-peak-line{color:#4ade80 !important}
html.dark .ins-pills{background-color:#1e2320 !important}
html.dark .ins-pill{color:#9bb0a0 !important}
html.dark .ins-pill.is-on{background-color:#2a302b !important;color:#4ade80 !important;border-color:#343a34 !important}
html.dark .ins-chip{background:#1e2320 !important;border-color:#333b34 !important;color:#e7ece5 !important}
html.dark .ins-chip-k{color:#9bb0a0 !important}
html.dark .ins-act{background:#1e2320 !important;border-color:#333b34 !important}
html.dark .ins-bar{background:#1e2320 !important}
html.dark .ins-dist{background:#1e2320 !important}
html.dark .ins-ring-bg{stroke:#333b34 !important}
html.dark .ins-back{background:#232926 !important;border-color:#333b34 !important;color:#e7ece5 !important}
html.dark .ins-count{background:#1e2320 !important;color:#9bb0a0 !important}
html.dark .ins-trend-foot{border-color:#333b34 !important}
@media(min-width:640px){html.dark .ins-hero-ring{border-color:#333b34 !important}}
@media(prefers-reduced-motion:reduce){.ins-bar-fill,.ins-seg{transition:none}}
</style>

@php
    $range = $insights['range'] ?? '30d';
    $r = $insights['reports'];
    $n = $insights['notes'];
    $daily = $insights['daily'] ?? [];
    $maxDaily = max(1, (int) ($insights['max_daily'] ?? 1));
    $ranges = ['today' => __('ui.range_today'), '7d' => __('ui.range_7d'), '30d' => __('ui.range_30d'), 'all' => __('ui.range_all')];

    $health = (int) ($insights['health'] ?? 0);
    $healthKey = $insights['health_key'] ?? 'stable';
    $isEmpty = (($r['total'] ?? 0) === 0 && ($n['total'] ?? 0) === 0);
    if ($isEmpty) $healthKey = 'empty';
    $healthLabel = [
        'excellent' => __('ui.health_excellent'),
        'stable' => __('ui.health_stable'),
        'watch' => __('ui.health_watch'),
        'critical' => __('ui.health_critical'),
        'empty' => __('ui.health_empty'),
    ][$healthKey] ?? __('ui.health_stable');
    $healthColor = [
        'excellent' => '#0e6a38', 'stable' => '#149a52', 'watch' => '#b45309',
        'critical' => '#b91c1c', 'empty' => '#9aa99a',
    ][$healthKey] ?? '#0e6a38';

    $coverage = (int) ($insights['coverage'] ?? 0);
    $activeDays = (int) ($insights['active_days'] ?? 0);
    $totalDays = max(1, (int) ($insights['total_days'] ?? max(1, count($daily))));
    $quietDays = (int) ($insights['quiet_days'] ?? 0);
    $streak = (int) ($insights['streak'] ?? 0);
    $backlog = (int) ($insights['backlog'] ?? (($n['pending'] ?? 0) + ($n['unlinked_accepted'] ?? 0) + ($r['draft'] ?? 0)));
    $peak = $insights['peak'] ?? null;
    $deltaR = $r['delta'] ?? null;
    $deltaN = $n['delta'] ?? null;
    $pendingShare = (int) ($n['pending_share'] ?? 0);
    $perWeek = $r['per_week'] ?? round(($r['total'] ?? 0) / $totalDays * 7, 1);
    $avgPerDay = $r['avg_per_day'] ?? round(($r['total'] ?? 0) / $totalDays, 1);
    $visibleRate = (int) ($r['visible_rate'] ?? 0);
    $actions = $insights['actions'] ?? [['key' => 'steady', 'level' => 'ok', 'count' => 0]];

    $fmtDelta = function ($v) {
        if ($v === null) return ['t' => '—', 'c' => 'ins-delta-flat'];
        $v = (float) $v;
        $sign = $v > 0.05 ? '+' : ($v < -0.05 ? '' : '±');
        $arrow = $v > 0.05 ? '▲' : ($v < -0.05 ? '▼' : '•');
        $cls = $v > 0.05 ? 'ins-delta-up' : ($v < -0.05 ? 'ins-delta-down' : 'ins-delta-flat');
        return ['t' => $arrow.' '.rtrim(rtrim(number_format($sign === '±' ? 0 : $v, 1), '0'), '.').'%', 'c' => $cls];
    };
    $dR = $fmtDelta($deltaR);
    $dN = $fmtDelta($deltaN);

    $lvlMeta = [
        'critical' => ['t' => __('ui.lvl_critical'), 'c' => 'ins-lvl-critical'],
        'high' => ['t' => __('ui.lvl_high'), 'c' => 'ins-lvl-high'],
        'medium' => ['t' => __('ui.lvl_medium'), 'c' => 'ins-lvl-medium'],
        'ok' => ['t' => __('ui.lvl_ok'), 'c' => 'ins-lvl-ok'],
        'info' => ['t' => __('ui.lvl_info'), 'c' => 'ins-lvl-info'],
    ];
    $actMeta = function ($a) use ($r, $n, $coverage, $streak) {
        $k = $a['key']; $c = (int) ($a['count'] ?? 0);
        return match ($k) {
            'pending' => ['t' => __('ui.act_pending_t', ['n' => $c]), 'd' => __('ui.act_pending_d', ['p' => (int)($n['pending_share'] ?? 0)]), 'href' => route('notes.index')],
            'unlinked' => ['t' => __('ui.act_unlinked_t', ['n' => $c]), 'd' => __('ui.act_unlinked_d'), 'href' => route('reports.index')],
            'drafts' => ['t' => __('ui.act_drafts_t', ['n' => $c]), 'd' => __('ui.act_drafts_d'), 'href' => route('reports.index')],
            'drafts_blocked' => ['t' => __('ui.act_drafts_blocked_t', ['n' => $c]), 'd' => __('ui.act_drafts_blocked_d'), 'href' => route('reports.index')],
            'coverage' => ['t' => __('ui.act_coverage_t', ['n' => $c]), 'd' => __('ui.act_coverage_d', ['c' => $coverage]), 'href' => route('reports.create')],
            'empty' => ['t' => __('ui.act_empty_t'), 'd' => __('ui.act_empty_d'), 'href' => route('reports.create')],
            default => ['t' => __('ui.act_steady_t'), 'd' => __('ui.act_steady_d', ['s' => $streak]), 'href' => route('reports.index')],
        };
    };

    $repTotal = max(1, (int) $r['total']);
    $noteTotal = max(1, (int) $n['total']);
    $repPubPct = $r['total'] > 0 ? round($r['published'] / $r['total'] * 100) : 0;
    $repDraftPct = $r['total'] > 0 ? round($r['draft'] / $r['total'] * 100) : 0;
    $ntAccPct = $n['total'] > 0 ? round($n['accepted'] / $n['total'] * 100) : 0;
    $ntPenPct = $n['total'] > 0 ? round($n['pending'] / $n['total'] * 100) : 0;
    $ntRejPct = $n['total'] > 0 ? round($n['rejected'] / $n['total'] * 100) : 0;

    $circ = 2 * pi() * 44;
    $dash = $circ * max(0, min(100, $health)) / 100;
@endphp

<div id="insights-root"
     data-range="{{ $range }}"
     data-endpoint="{{ route('reports.insights') }}"
     data-link-notes="{{ route('notes.index') }}"
     data-link-reports="{{ route('reports.index') }}"
     data-link-create="{{ route('reports.create') }}"
     class="ins-root">

    {{-- الترويسة مدمجة --}}
    <div class="ins-head">
        <div class="ins-head-txt">
            <p class="ins-crumb">{{ __('ui.nav_reports') }} / {{ __('ui.view_insights') }}</p>
            <h1 class="ins-title">{{ __('ui.insights_title') }}</h1>
            <p class="ins-sub">{{ __('ui.insights_sub') }}</p>
        </div>
        <a href="{{ route('reports.index') }}" class="ins-back">
            <svg class="w-4 h-4 rtl:rotate-180 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            <span class="truncate">{{ __('ui.back_to_reports') }}</span>
        </a>
    </div>

    {{-- شريط النطاق: شبكة ثابتة بلا سكرول أفقي --}}
    <div class="ins-card ins-range">
        <div class="ins-pills" role="tablist" aria-label="{{ __('ui.filter_time') }}">
            @foreach($ranges as $key => $label)
                <a href="{{ route('reports.insights', ['range' => $key]) }}" data-range-link="{{ $key }}"
                   role="tab" aria-selected="{{ $range === $key ? 'true' : 'false' }}"
                   class="ins-pill {{ $range === $key ? 'is-on' : '' }}"><span class="truncate">{{ $label }}</span></a>
            @endforeach
        </div>
        <div class="ins-range-meta">
            <span id="ins-range-meta" class="tabular-nums">{{ __('ui.insights_range_meta', ['from' => $insights['from'] ?? '—', 'to' => $insights['to'] ?? '—', 'days' => $totalDays]) }}</span>
            <span id="insights-spinner" class="hidden items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded-full border-2 border-[#dce5dd] border-t-[#0e6a38] animate-spin inline-block"></span>
                {{ __('ui.insights_loading') }}
            </span>
        </div>
    </div>

    {{-- الملخص التنفيذي --}}
    <section class="ins-card ins-hero" aria-label="{{ __('ui.health_title') }}">
        <div class="ins-hero-ring">
            <div class="ins-donut" role="img" aria-label="{{ __('ui.health_title') }} {{ $health }}%">
                <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90" aria-hidden="true">
                    <circle cx="50" cy="50" r="44" fill="none" stroke-width="10" class="ins-ring-bg" stroke="#eceee9"/>
                    <circle id="ins-health-ring" cx="50" cy="50" r="44" fill="none" stroke="{{ $healthColor }}" stroke-width="10"
                            stroke-linecap="round" stroke-dasharray="{{ number_format($dash, 1) }} {{ number_format($circ, 1) }}"/>
                </svg>
                <div class="ins-donut-c">
                    <span id="ins-health-num" dir="ltr">{{ $health }}<small>%</small></span>
                </div>
            </div>
            <div class="ins-hero-lvl">
                <p class="ins-lbl">{{ __('ui.health_title') }}</p>
                <p class="mt-1"><span id="ins-health-level" class="ins-health-badge" style="--hc: {{ $healthColor }}">{{ $healthLabel }}</span></p>
            </div>
        </div>
        <div class="ins-hero-txt">
            <p id="ins-brief-1" class="ins-brief1">
                @if($isEmpty)
                    {{ __('ui.brief_empty') }}
                @else
                    {{ __('ui.brief_volume', ['reports' => $r['total'], 'published' => $r['published'], 'notes' => $n['accepted'], 'days' => $totalDays]) }}
                @endif
            </p>
            @unless($isEmpty)
            <p id="ins-brief-2" class="ins-brief2 tabular-nums">
                {{ __('ui.brief_rates', ['pr' => $r['publish_rate'], 'ar' => $n['accept_rate'], 'cov' => $coverage, 'active' => $activeDays, 'total' => $totalDays]) }}
            </p>
            <p id="ins-brief-3" class="ins-brief2 tabular-nums">
                @if($backlog > 0)
                    {{ __('ui.brief_backlog', ['n' => $backlog, 'p' => $n['pending'], 'u' => $n['unlinked_accepted'], 'd' => $r['draft']]) }}
                @else
                    {{ __('ui.brief_steady', ['s' => $streak, 'w' => $perWeek]) }}
                @endif
                @if($peak) <span class="ins-peak-dot" aria-hidden="true"></span> {{ __('ui.brief_peak', ['day' => $peak['day'], 'r' => $peak['reports'], 'n' => $peak['notes']]) }} @endif
            </p>
            @endunless
            <div class="ins-chips" aria-label="{{ __('ui.vs_prev') }}">
                <span class="ins-chip"><span class="ins-chip-k">{{ __('ui.kpi_total_reports') }}</span><span id="chip-reports" dir="ltr" class="tabular-nums {{ $dR['c'] }}">{{ $dR['t'] }}</span></span>
                <span class="ins-chip"><span class="ins-chip-k">{{ __('ui.kpi_accepted') }}</span><span id="chip-notes" dir="ltr" class="tabular-nums {{ $dN['c'] }}">{{ $dN['t'] }}</span></span>
                <span class="ins-chip"><span class="ins-chip-k">{{ __('ui.kpi_coverage') }}</span><span id="chip-coverage" dir="ltr" class="tabular-nums">{{ $coverage }}%</span></span>
                <span class="ins-chip"><span class="ins-chip-k">{{ __('ui.kpi_backlog') }}</span><span id="chip-backlog" dir="ltr" class="tabular-nums">{{ $backlog }}</span></span>
                <span class="ins-chip-note">{{ __('ui.vs_prev') }}</span>
            </div>
        </div>
    </section>

    {{-- مؤشرات التقارير --}}
    <div class="ins-sec">
        <h2 class="ins-h2">{{ __('ui.section_reports') }}</h2>
        <span class="ins-sec-hint tabular-nums">{{ __('ui.kpi_velocity') }}: <b id="kpi-velocity-hint" dir="ltr">{{ $perWeek }}</b></span>
    </div>
    <div class="ins-grid">
        <div class="ins-card ins-kpi">
            <p class="ins-kpi-k">{{ __('ui.kpi_total_reports') }}</p>
            <p class="ins-kpi-v" data-kpi="reports.total">{{ $r['total'] }}</p>
            <p class="ins-kpi-s"><span data-delta="reports.delta" dir="ltr" class="tabular-nums {{ $dR['c'] }}">{{ $dR['t'] }}</span> · <span class="tabular-nums"><span data-kpi="reports.avg_per_day">{{ $avgPerDay }}</span>/{{ __('ui.trend_title') === 'النشاط اليومي' ? 'يوم' : 'day' }}</span></p>
        </div>
        <div class="ins-card ins-kpi">
            <p class="ins-kpi-k">{{ __('ui.kpi_published') }}</p>
            <p class="ins-kpi-v ins-green" data-kpi="reports.published">{{ $r['published'] }}</p>
            <p class="ins-kpi-s">{{ __('ui.kpi_visible') }}: <b class="tabular-nums" data-kpi="reports.visible">{{ $r['visible'] }}</b> (<b class="tabular-nums" data-kpi="reports.visible_rate">{{ $visibleRate }}</b>%)</p>
        </div>
        <div class="ins-card ins-kpi">
            <p class="ins-kpi-k">{{ __('ui.kpi_draft') }}</p>
            <p class="ins-kpi-v" data-kpi="reports.draft">{{ $r['draft'] }}</p>
            <p class="ins-kpi-s tabular-nums"><span data-kpi="reports.with_notes">{{ $r['with_notes'] }}</span>/<span data-kpi="reports.total2">{{ $r['total'] }}</span> {{ __('ui.notes_many') }}</p>
        </div>
        <div class="ins-card ins-kpi ins-rate">
            <p class="ins-kpi-k">{{ __('ui.kpi_publish_rate') }}</p>
            <p class="ins-kpi-v" dir="ltr"><span data-kpi="reports.publish_rate">{{ $r['publish_rate'] }}</span>%</p>
            <div class="ins-bar"><div id="insights-pub-bar" class="ins-bar-fill" style="width: {{ $r['publish_rate'] }}%"></div></div>
        </div>
    </div>

    {{-- مؤشرات الملاحظات --}}
    <div class="ins-sec">
        <h2 class="ins-h2">{{ __('ui.section_notes') }}</h2>
        <span class="ins-sec-hint tabular-nums">{{ __('ui.kpi_streak') }}: <b id="kpi-streak-hint">{{ $streak }}</b></span>
    </div>
    <div class="ins-grid">
        <div class="ins-card ins-kpi">
            <p class="ins-kpi-k">{{ __('ui.kpi_total_notes') }}</p>
            <p class="ins-kpi-v" data-kpi="notes.total">{{ $n['total'] }}</p>
            <p class="ins-kpi-s">{{ __('ui.kpi_rejected') }}: <b class="tabular-nums" data-kpi="notes.rejected">{{ $n['rejected'] }}</b></p>
        </div>
        <div class="ins-card ins-kpi">
            <p class="ins-kpi-k">{{ __('ui.kpi_accepted') }}</p>
            <p class="ins-kpi-v ins-green" data-kpi="notes.accepted">{{ $n['accepted'] }}</p>
            <p class="ins-kpi-s">{{ __('ui.kpi_unlinked') }}: <b class="tabular-nums ins-amber" data-kpi="notes.unlinked_accepted">{{ $n['unlinked_accepted'] }}</b></p>
        </div>
        <div class="ins-card ins-kpi {{ ($n['pending'] ?? 0) > 0 ? 'is-warn' : '' }}" id="kpi-pending-card">
            <p class="ins-kpi-k">{{ __('ui.kpi_pending') }}</p>
            <p class="ins-kpi-v {{ ($n['pending'] ?? 0) > 0 ? 'ins-amber-text' : '' }}" id="kpi-pending-num" data-kpi="notes.pending">{{ $n['pending'] }}</p>
            <p class="ins-kpi-s"><span class="tabular-nums" dir="ltr"><span data-kpi="notes.pending_share">{{ $pendingShare }}</span>%</span> · <a href="{{ route('notes.index') }}" class="ins-link">{{ __('ui.view_details') }}</a></p>
        </div>
        <div class="ins-card ins-kpi ins-rate">
            <p class="ins-kpi-k">{{ __('ui.kpi_accept_rate') }}</p>
            <p class="ins-kpi-v" dir="ltr"><span data-kpi="notes.accept_rate">{{ $n['accept_rate'] }}</span>%</p>
            <div class="ins-bar"><div id="insights-acc-bar" class="ins-bar-fill" style="width: {{ $n['accept_rate'] }}%"></div></div>
        </div>
    </div>

    {{-- الإجراءات + الاتجاه --}}
    <div class="ins-grid ins-grid-2">
        <section class="ins-card ins-pad" aria-label="{{ __('ui.actions_title') }}">
            <div class="ins-boxhead">
                <h2 class="ins-h2">{{ __('ui.actions_title') }}</h2>
                <span id="ins-actions-count" class="ins-count tabular-nums">{{ count($actions) }}</span>
            </div>
            <ol id="ins-actions" class="ins-acts">
                @foreach($actions as $i => $a)
                    @php $m = $actMeta($a); $lvl = $lvlMeta[$a['level']] ?? $lvlMeta['info']; @endphp
                    <li class="ins-act">
                        <span class="ins-act-n" dir="ltr">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="ins-act-body">
                            <div class="ins-act-top">
                                <p class="ins-act-t">{{ $m['t'] }}</p>
                                <span class="ins-lvl {{ $lvl['c'] }}">{{ $lvl['t'] }}</span>
                            </div>
                            <p class="ins-act-d">{{ $m['d'] }}</p>
                            <a href="{{ $m['href'] }}" class="ins-act-link">{{ __('ui.act_open') }}
                                <svg class="w-3 h-3 rtl:rotate-180 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
                            </a>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="ins-card ins-pad" aria-label="{{ __('ui.trend_title') }}">
            <div class="ins-boxhead ins-boxhead-wrap">
                <h2 class="ins-h2">{{ __('ui.trend_title') }}</h2>
                @if($range === 'all')<span class="ins-muted-s">{{ __('ui.trend_all_hint') }}</span>@endif
                <span class="ins-legend">
                    <span class="ins-legend-i"><span class="ins-dot ins-dot-a"></span>{{ __('ui.trend_reports') }}</span>
                    <span class="ins-legend-i"><span class="ins-dot ins-dot-b"></span>{{ __('ui.trend_notes') }}</span>
                </span>
            </div>
            @if(empty($daily) || array_sum(array_column($daily, 'reports')) + array_sum(array_column($daily, 'notes')) === 0)
                <p class="ins-empty">{{ __('ui.insights_no_data') }}</p>
            @else
            <div id="insights-chart-wrap" class="ins-chart-wrap">
                <div id="insights-chart" class="ins-chart">
                    @foreach($daily as $d)
                        @php
                            $rh = max(4, round($d['reports'] / $maxDaily * 96));
                            $nh = max(4, round($d['notes'] / $maxDaily * 96));
                            $isPeak = $peak && $d['day'] === $peak['day'];
                        @endphp
                        <div class="ins-col {{ $isPeak ? 'is-peak' : '' }}" title="{{ $d['day'] }} — {{ $d['reports'] }} / {{ $d['notes'] }}">
                            <div class="ins-bars">
                                <div class="ins-b ins-b-a {{ $isPeak ? 'is-peak-bar' : '' }}" style="height: {{ $rh }}px" data-bar="reports" data-day="{{ $d['day'] }}"></div>
                                <div class="ins-b ins-b-b" style="height: {{ $nh }}px" data-bar="notes" data-day="{{ $d['day'] }}"></div>
                            </div>
                            <span class="ins-col-lbl">{{ $d['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="ins-trend-foot">
                <p id="trend-peak" class="ins-peak-line tabular-nums">
                    @if($peak){{ __('ui.trend_peak_line', ['day' => $peak['day'], 'r' => $peak['reports'], 'n' => $peak['notes']]) }}@endif
                </p>
                <p id="trend-meta" class="ins-muted-s tabular-nums">{{ __('ui.trend_meta_line', ['a' => $activeDays, 't' => $totalDays, 'v' => $avgPerDay, 'q' => $quietDays]) }}</p>
            </div>
            @endif
        </section>
    </div>

    {{-- التوزيع --}}
    <div class="ins-grid ins-grid-2">
        <section class="ins-card ins-pad">
            <h3 class="ins-h3">{{ __('ui.dist_reports_t') }}</h3>
            <div class="ins-dist" dir="ltr" aria-hidden="true">
                <div id="dist-rep-pub" class="ins-seg ins-seg-a" style="width: {{ $repPubPct }}%"></div>
                <div id="dist-rep-draft" class="ins-seg ins-seg-b" style="width: {{ $repDraftPct }}%"></div>
            </div>
            <div class="ins-dist-legend">
                <p><span class="ins-dot ins-dot-a"></span>{{ __('ui.kpi_published') }} <span class="ins-nums" dir="ltr"><span id="dist-rep-pub-n" data-kpi="reports.published2">{{ $r['published'] }}</span> · <span id="dist-rep-pub-p">{{ $repPubPct }}</span>%</span></p>
                <p><span class="ins-dot ins-dot-b2"></span>{{ __('ui.kpi_draft') }} <span class="ins-nums" dir="ltr"><span data-kpi="reports.draft2">{{ $r['draft'] }}</span> · <span id="dist-rep-draft-p">{{ $repDraftPct }}</span>%</span></p>
            </div>
        </section>
        <section class="ins-card ins-pad">
            <h3 class="ins-h3">{{ __('ui.dist_notes_t') }}</h3>
            <div class="ins-dist" dir="ltr" aria-hidden="true">
                <div id="dist-note-acc" class="ins-seg ins-seg-a" style="width: {{ $ntAccPct }}%"></div>
                <div id="dist-note-pen" class="ins-seg ins-seg-b" style="width: {{ $ntPenPct }}%"></div>
                <div id="dist-note-rej" class="ins-seg ins-seg-c" style="width: {{ $ntRejPct }}%"></div>
            </div>
            <div class="ins-dist-legend">
                <p><span class="ins-dot ins-dot-a"></span>{{ __('ui.kpi_accepted') }} <span class="ins-nums" dir="ltr"><span data-kpi="notes.accepted2">{{ $n['accepted'] }}</span> · <span id="dist-note-acc-p">{{ $ntAccPct }}</span>%</span></p>
                <p><span class="ins-dot ins-dot-b2"></span>{{ __('ui.kpi_pending') }} <span class="ins-nums" dir="ltr"><span data-kpi="notes.pending2">{{ $n['pending'] }}</span> · <span id="dist-note-pen-p">{{ $ntPenPct }}</span>%</span></p>
                <p><span class="ins-dot ins-dot-c"></span>{{ __('ui.kpi_rejected') }} <span class="ins-nums" dir="ltr"><span data-kpi="notes.rejected2">{{ $n['rejected'] }}</span> · <span id="dist-note-rej-p">{{ $ntRejPct }}</span>%</span></p>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('insights-root');
    if (!root) return;
    const endpoint = root.getAttribute('data-endpoint');
    const linkNotes = root.getAttribute('data-link-notes');
    const linkReports = root.getAttribute('data-link-reports');
    const linkCreate = root.getAttribute('data-link-create');
    const spinner = document.getElementById('insights-spinner');
    const pills = Array.from(document.querySelectorAll('[data-range-link]'));
    const CIRC = 2 * Math.PI * 44;
    const isEN = () => document.documentElement.lang === 'en';

    const HEALTH = {
        excellent: { ar: 'جاهزية عالية', en: 'High readiness', c: '#0e6a38' },
        stable: { ar: 'مستقر', en: 'Stable', c: '#149a52' },
        watch: { ar: 'يحتاج متابعة', en: 'Needs follow-up', c: '#b45309' },
        critical: { ar: 'تدخل مطلوب', en: 'Action required', c: '#b91c1c' },
        empty: { ar: 'بلا نشاط', en: 'No activity', c: '#9aa99a' }
    };
    const LVL = {
        critical: { ar: 'عاجل', en: 'Urgent', c: 'ins-lvl-critical' },
        high: { ar: 'مهم', en: 'High', c: 'ins-lvl-high' },
        medium: { ar: 'متابعة', en: 'Follow-up', c: 'ins-lvl-medium' },
        ok: { ar: 'استقرار', en: 'Steady', c: 'ins-lvl-ok' },
        info: { ar: 'معلومة', en: 'Info', c: 'ins-lvl-info' }
    };

    function setActive(key) {
        pills.forEach(function (a) {
            const on = a.getAttribute('data-range-link') === key;
            a.setAttribute('aria-selected', on ? 'true' : 'false');
            a.classList.toggle('is-on', on);
        });
        root.setAttribute('data-range', key);
    }

    function fmtDelta(v) {
        if (v === null || v === undefined) return { t: '—', c: 'ins-delta-flat' };
        v = parseFloat(v);
        if (isNaN(v)) return { t: '—', c: 'ins-delta-flat' };
        const arrow = v > 0.05 ? '▲' : (v < -0.05 ? '▼' : '•');
        const cls = v > 0.05 ? 'ins-delta-up' : (v < -0.05 ? 'ins-delta-down' : 'ins-delta-flat');
        const num = (v > 0.05 ? '+' : '') + (Math.abs(v) < 0.05 ? '0' : String(Math.round(v * 10) / 10)) + '%';
        return { t: arrow + ' ' + num, c: cls };
    }

    function actText(a, d) {
        const en = isEN(), c = a.count || 0;
        const ps = d.notes.pending_share ?? 0, cov = d.coverage ?? 0, st = d.streak ?? 0;
        const open = en ? 'Open' : 'فتح';
        switch (a.key) {
            case 'pending': return { t: en ? `Decide pending notes (${c})` : `بتّ في الملاحظات المعلقة (${c})`, d: en ? `${ps}% of notes await your decision — clearing them raises acceptance.` : `${ps}% من الملاحظات بانتظار قرارك — إنجازها يرفع القبول ويصفّر التراكم.`, href: linkNotes, open };
            case 'unlinked': return { t: en ? `Attach unlinked accepted notes (${c})` : `وظّف الملاحظات المقبولة غير المربوطة (${c})`, d: en ? 'Ready notes with no report — attach them to today’s report.' : 'ملاحظات جاهزة بلا تقرير — ألحقها بتقرير اليوم حتى لا تضيع.', href: linkReports, open };
            case 'drafts': return { t: en ? `Review drafts (${c})` : `راجع المسودات (${c})`, d: en ? 'Drafts need approval and publishing to reach monitors.' : 'مسودات تحتاج اعتماداً ونشراً لتظهر للمراقبين.', href: linkReports, open };
            case 'drafts_blocked': return { t: en ? `Nothing published — approve drafts (${c})` : `لا نشر بعد — اعتمد المسودات (${c})`, d: en ? 'All reports are drafts. Publishing now raises readiness immediately.' : 'كل التقارير مسودات. النشر الآن يرفع الجاهزية فوراً.', href: linkReports, open };
            case 'coverage': return { t: en ? `Close coverage gaps (${c} silent days)` : `سدّ فجوات التغطية (${c} يوماً صامتاً)`, d: en ? `Coverage only ${cov}% — keep a short daily report instead of gaps.` : `التغطية ${cov}% فقط — ثبّت تقريراً يومياً قصيراً بدل الانقطاع.`, href: linkCreate, open };
            case 'empty': return { t: en ? 'Start with today’s report' : 'ابدأ بتقرير اليوم', d: en ? 'No data yet — one published report activates all indicators.' : 'لا بيانات بعد — أول تقرير منشور يفعّل كل المؤشرات.', href: linkCreate, open };
            default: return { t: en ? 'Keep the cadence' : 'حافظ على الإيقاع', d: en ? `No backlog for ${st} days — continue at the same pace.` : `لا تراكم مؤثر منذ ${st} يوماً — استمر بنفس الوتيرة.`, href: linkReports, open };
        }
    }

    function render(data) {
        const set = (k, v) => { root.querySelectorAll('[data-kpi="' + k + '"]').forEach((el) => { el.textContent = v; }); };
        set('reports.total', data.reports.total); set('reports.total2', data.reports.total);
        set('reports.published', data.reports.published); set('reports.published2', data.reports.published);
        set('reports.draft', data.reports.draft); set('reports.draft2', data.reports.draft);
        set('reports.publish_rate', data.reports.publish_rate);
        set('reports.visible', data.reports.visible); set('reports.visible_rate', data.reports.visible_rate ?? 0);
        set('reports.avg_per_day', data.reports.avg_per_day ?? 0);
        set('reports.with_notes', data.reports.with_notes);
        set('notes.total', data.notes.total);
        set('notes.accepted', data.notes.accepted); set('notes.accepted2', data.notes.accepted);
        set('notes.pending', data.notes.pending); set('notes.pending2', data.notes.pending);
        set('notes.rejected', data.notes.rejected); set('notes.rejected2', data.notes.rejected);
        set('notes.accept_rate', data.notes.accept_rate);
        set('notes.pending_share', data.notes.pending_share ?? 0);
        set('notes.unlinked_accepted', data.notes.unlinked_accepted);
        const pub = document.getElementById('insights-pub-bar');
        if (pub) pub.style.width = data.reports.publish_rate + '%';
        const acc = document.getElementById('insights-acc-bar');
        if (acc) acc.style.width = data.notes.accept_rate + '%';

        const dR = fmtDelta(data.reports.delta), dN = fmtDelta(data.notes.delta);
        document.querySelectorAll('[data-delta="reports.delta"]').forEach((el) => { el.textContent = dR.t; el.className = dR.c + ' tabular-nums'; el.setAttribute('dir', 'ltr'); });
        const cR = document.getElementById('chip-reports'); if (cR) { cR.textContent = dR.t; cR.className = 'tabular-nums ' + dR.c; }
        const cN = document.getElementById('chip-notes'); if (cN) { cN.textContent = dN.t; cN.className = 'tabular-nums ' + dN.c; }
        const cC = document.getElementById('chip-coverage'); if (cC) cC.textContent = (data.coverage ?? 0) + '%';
        const cB = document.getElementById('chip-backlog'); if (cB) cB.textContent = data.backlog ?? 0;

        const empty = (data.reports.total === 0 && data.notes.total === 0);
        const hk = empty ? 'empty' : (data.health_key || 'stable');
        const hm = HEALTH[hk] || HEALTH.stable;
        const ring = document.getElementById('ins-health-ring');
        if (ring) {
            const h = Math.max(0, Math.min(100, data.health || 0));
            ring.setAttribute('stroke', hm.c);
            ring.setAttribute('stroke-dasharray', (CIRC * h / 100).toFixed(1) + ' ' + CIRC.toFixed(1));
        }
        const hn = document.getElementById('ins-health-num');
        if (hn) hn.innerHTML = (data.health || 0) + '<small>%</small>';
        const hl = document.getElementById('ins-health-level');
        if (hl) { hl.textContent = isEN() ? hm.en : hm.ar; hl.style.setProperty('--hc', hm.c); }
        const b1 = document.getElementById('ins-brief-1'), b2 = document.getElementById('ins-brief-2'), b3 = document.getElementById('ins-brief-3');
        const days = data.total_days || Math.max(1, (data.daily || []).length);
        if (b1) b1.textContent = empty
            ? (isEN() ? 'No activity in this range. Create today’s report to activate the indicators.' : 'لا نشاط مسجل في هذا النطاق. أنشئ تقرير اليوم لتبدأ القراءة التشغيلية.')
            : (isEN() ? `${data.reports.total} reports (${data.reports.published} published) and ${data.notes.accepted} accepted notes over ${days} days.`
                      : `${data.reports.total} تقريراً (${data.reports.published} منشوراً) و ${data.notes.accepted} ملاحظة مقبولة خلال ${days} يوماً.`);
        if (b2) {
            b2.style.display = empty ? 'none' : '';
            if (!empty) b2.textContent = isEN()
                ? `Publish ${data.reports.publish_rate}% • Accept ${data.notes.accept_rate}% • Coverage ${data.coverage}% (active ${data.active_days} of ${data.total_days} days).`
                : `النشر ${data.reports.publish_rate}% • القبول ${data.notes.accept_rate}% • التغطية ${data.coverage}% (النشاط في ${data.active_days} من ${data.total_days} يوماً).`;
        }
        if (b3) {
            b3.style.display = empty ? 'none' : '';
            if (!empty) {
                let s = (data.backlog || 0) > 0
                    ? (isEN() ? `Current backlog ${data.backlog} (pending ${data.notes.pending} • unlinked ${data.notes.unlinked_accepted} • drafts ${data.reports.draft}) — start with the highest impact below.`
                              : `التراكم الحالي ${data.backlog} (انتظار ${data.notes.pending} • بلا تقرير ${data.notes.unlinked_accepted} • مسودات ${data.reports.draft}) — عالج الأعلى أثراً أدناه.`)
                    : (isEN() ? `No significant backlog. Streak ${data.streak} active days at ${data.reports.per_week} reports/week — keep the cadence.`
                              : `لا تراكم مؤثر. الاستمرارية ${data.streak} يوماً متتالياً بوتيرة ${data.reports.per_week} تقرير/أسبوع — حافظ على الإيقاع.`);
                if (data.peak) s += isEN() ? ` Peak: ${data.peak.day} (${data.peak.reports} reports, ${data.peak.notes} notes).` : ` ذروة النشاط: ${data.peak.day} (${data.peak.reports} تقرير، ${data.peak.notes} ملاحظة).`;
                b3.textContent = s;
            }
        }

        const vh = document.getElementById('kpi-velocity-hint'); if (vh) vh.textContent = data.reports.per_week ?? '—';
        const sh = document.getElementById('kpi-streak-hint'); if (sh) sh.textContent = data.streak ?? 0;
        const rm = document.getElementById('ins-range-meta');
        if (rm) rm.textContent = isEN() ? `Range: ${data.from || '—'} → ${data.to || '—'} • ${data.total_days || days} days` : `النطاق: ${data.from || '—'} ← ${data.to || '—'} • ${data.total_days || days} يوم`;

        const box = document.getElementById('ins-actions');
        const cnt = document.getElementById('ins-actions-count');
        if (box && Array.isArray(data.actions)) {
            box.innerHTML = '';
            data.actions.forEach(function (a, i) {
                const m = actText(a, data), lv = LVL[a.level] || LVL.info;
                const li = document.createElement('li');
                li.className = 'ins-act';
                li.innerHTML = '<span class="ins-act-n" dir="ltr">' + String(i + 1).padStart(2, '0') + '</span>'
                    + '<div class="ins-act-body"><div class="ins-act-top">'
                    + '<p class="ins-act-t"></p>'
                    + '<span class="ins-lvl ' + lv.c + '">' + (isEN() ? lv.en : lv.ar) + '</span></div>'
                    + '<p class="ins-act-d"></p>'
                    + '<a class="ins-act-link">' + m.open + ' <svg class="w-3 h-3 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg></a></div>';
                li.querySelector('.ins-act-t').textContent = m.t;
                li.querySelector('.ins-act-d').textContent = m.d;
                const link = li.querySelector('a'); link.setAttribute('href', m.href);
                box.appendChild(li);
            });
            if (cnt) cnt.textContent = data.actions.length;
        }

        const chart = document.getElementById('insights-chart');
        if (chart && Array.isArray(data.daily) && data.daily.length) {
            const max = Math.max(1, data.max_daily || 1);
            chart.innerHTML = '';
            data.daily.forEach(function (d) {
                const rh = Math.max(4, Math.round(d.reports / max * 96));
                const nh = Math.max(4, Math.round(d.notes / max * 96));
                const isPeak = data.peak && d.day === data.peak.day;
                const col = document.createElement('div');
                col.className = 'ins-col' + (isPeak ? ' is-peak' : '');
                col.setAttribute('title', d.day + ' — ' + d.reports + ' / ' + d.notes);
                col.innerHTML = '<div class="ins-bars">'
                    + '<div class="ins-b ins-b-a' + (isPeak ? ' is-peak-bar' : '') + '" style="height: ' + rh + 'px"></div>'
                    + '<div class="ins-b ins-b-b" style="height: ' + nh + 'px"></div>'
                    + '</div><span class="ins-col-lbl">' + d.label + '</span>';
                chart.appendChild(col);
            });
        }
        const tp = document.getElementById('trend-peak');
        if (tp) tp.textContent = data.peak ? (isEN() ? `Peak ${data.peak.day} — ${data.peak.reports} reports / ${data.peak.notes} notes` : `الذروة ${data.peak.day} — ${data.peak.reports} تقرير / ${data.peak.notes} ملاحظة`) : '';
        const tm = document.getElementById('trend-meta');
        if (tm) tm.textContent = isEN() ? `Active ${data.active_days}/${data.total_days} days • avg ${data.reports.avg_per_day} reports/day • silent ${data.quiet_days} days`
                                        : `نشاط ${data.active_days}/${data.total_days} يوم • متوسط ${data.reports.avg_per_day} تقرير/يوم • صمت ${data.quiet_days} يوم`;

        const pct = (a, b) => (b > 0 ? Math.round(a / b * 100) : 0);
        const rP = pct(data.reports.published, data.reports.total), rD = pct(data.reports.draft, data.reports.total);
        const nA = pct(data.notes.accepted, data.notes.total), nP = pct(data.notes.pending, data.notes.total), nR = pct(data.notes.rejected, data.notes.total);
        const setW = (id, v) => { const el = document.getElementById(id); if (el) el.style.width = v + '%'; };
        const setT = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setW('dist-rep-pub', rP); setW('dist-rep-draft', rD);
        setW('dist-note-acc', nA); setW('dist-note-pen', nP); setW('dist-note-rej', nR);
        setT('dist-rep-pub-p', rP); setT('dist-rep-draft-p', rD);
        setT('dist-note-acc-p', nA); setT('dist-note-pen-p', nP); setT('dist-note-rej-p', nR);

        const pc = document.getElementById('kpi-pending-card'), pn = document.getElementById('kpi-pending-num');
        if (pc) pc.classList.toggle('is-warn', (data.notes.pending || 0) > 0);
        if (pn) pn.classList.toggle('ins-amber-text', (data.notes.pending || 0) > 0);
    }

    pills.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            const key = a.getAttribute('data-range-link');
            setActive(key);
            try { history.replaceState(null, '', endpoint + '?range=' + encodeURIComponent(key)); } catch (err) {}
            if (spinner) { spinner.classList.remove('hidden'); spinner.classList.add('inline-flex'); }
            fetch(endpoint + '?range=' + encodeURIComponent(key), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (j) { if (j && j.success && j.data) render(j.data); })
                .catch(function () { window.location.href = a.href; })
                .finally(function () { if (spinner) { spinner.classList.add('hidden'); spinner.classList.remove('inline-flex'); } });
        });
    });
})();
</script>
@endsection
