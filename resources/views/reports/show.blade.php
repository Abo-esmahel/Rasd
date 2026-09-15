@extends('layouts.app')

@section('content')
@php
    $isWriter = auth()->user()->isReportWriter();
    $isOwner = $isWriter && (int) $report->author_id === (int) auth()->id();
    $isDraft = $report->isDraft();
    $notesCount = $report->notes->count();
    $isSheet = ($sheet ?? null) !== null;
    $pvState = $sheetImageState ?? 'none';
    $hasApproved = in_array($pvState, ['system', 'custom'], true);
    $canPublish = $isOwner && $isDraft && $notesCount > 0 && (trim((string) $report->content) !== '' || $hasApproved);
    $locked = $report->isPublished() && $report->isLocked();
    $deadline = $report->editDeadline();
    $aiEnabled = (bool) config('ai.enabled', false);
    $aiKeyPresent = trim((string) config('ai.gemini.api_key', '')) !== '';
    // فالديشن واجهة: عند غياب المفتاح نعرض زراً معطلاً مع رسالة بدل إخفائه بصمت.
    $aiMissingKey = ($aiMissingKey ?? false) || ($aiEnabled && !$aiKeyPresent);
    $showFinal = $isOwner && $isDraft && $isSheet && !empty($editorData ?? null);
    $showLegacy = $isOwner && $isDraft && !$isSheet;
    $pageMenu = $isOwner && ($isDraft || !$locked);
    $publishMissingNotes = $notesCount === 0;
    $publishMissingApprove = trim((string) $report->content) === '' && !$hasApproved;
    $publishHint = ($publishMissingNotes && $publishMissingApprove) ? __('ui.publish_hint_both') : ($publishMissingNotes ? __('ui.publish_hint_note') : ($publishMissingApprove ? __('ui.publish_hint_approve') : ''));
@endphp

<style>
    [data-menu]{ position:relative; }
    [data-menu-list]{
        position:absolute; top:calc(100% + 8px); inset-inline-end:0; inset-inline-start:auto; min-width:190px; max-width:calc(100vw - 16px);
        background:#fff; border:1px solid #e6e9e1; border-radius:12px;
        box-shadow:0 12px 28px -10px rgba(26,46,31,.16); padding:4px; z-index:40;
        transform-origin:top center;
    }
    @media(max-width:640px){
        [data-menu-list]{ min-width:min(190px,calc(100vw - 24px)); max-width:calc(100vw - 24px); inset-inline-end:0; }
        [data-menu-list] .m-item{ min-height:44px; padding:11px 12px; font-size:13px; }
    }
    [data-menu-list]:not(.hidden){ animation:rpt-menu-in .15s ease-out; }
    @keyframes rpt-menu-in{ from{opacity:0;transform:translateY(-4px) scale(.98)} to{opacity:1;transform:translateY(0) scale(1)} }
    [data-menu-list] .m-item{
        display:flex; align-items:center; width:100%; padding:10px 11px; min-height:40px;
        font-size:12.5px; font-weight:700; color:#1a2e1f; border-radius:8px;
        text-align:start; background:transparent; border:none; cursor:pointer;
    }
    [data-menu-list] .m-item:hover{ background:#f6f7f5; }
    [data-menu-list] .m-danger{ color:#b91c1c; }
    [data-menu-list] .m-danger:hover{ background:#fef2f2; }
    [data-menu-list] form + form,
    [data-menu-list] a + form{ border-top:1px solid #eceee9; margin-top:4px; padding-top:4px; }
    [data-menu-btn]{ transition:border-color .15s,color .15s; }
    [data-menu-btn][aria-expanded="true"]{ border-color:#0e6a38; color:#0e6a38; }
    @media(max-width:640px){
        [data-menu-btn]{ width:40px; height:40px; min-width:40px; min-height:40px; }
        .rpt-head{ gap:10px; padding-bottom:10px; }
        .rpt-head-title{ font-size:14.5px; line-height:1.3; }
        .rpt-meta{ gap:6px 8px; font-size:11.5px; }
    }
    .spin{ animation:rspin 1s linear infinite; }
    @keyframes rspin{ to{ transform:rotate(360deg);} }
    @keyframes rpt-in{ from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:translateY(0)} }
    .rpt-anim-in{ animation:rpt-in .22s ease-out; }
    .rpt-anim-fade{ animation:rpt-in .18s ease-out; }
    .sheet-mini{ position:relative; width:100%; max-width:520px; margin:0 auto; aspect-ratio:896/1200; background-size:100% 100%; background-repeat:no-repeat; border-radius:6px; overflow:hidden; }
    .sheet-mini .sh-field,.sheet-mini .sh-note{ position:absolute; color:#1c1917; overflow:hidden; }
    .sheet-mini .sh-field{ text-align:center; white-space:nowrap; font-weight:700; }
    .sheet-mini .sh-note{ text-align:right; font-family:'ReportNaskh','ReportBody','Traditional Arabic','Simplified Arabic',serif; }
    .rpt-btn{ transition:background-color .15s,border-color .15s,color .15s,box-shadow .15s,transform .08s; }
    .rpt-btn:active:not(:disabled){ transform:translateY(1px); }
    .rpt-page{ max-width:860px; margin:0 auto; }
    .rpt-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding-bottom:14px; border-bottom:1px solid #eceee9; }
    .rpt-head-title{ font-size:15.5px; font-weight:800; line-height:1.35; color:#1a2e1f; letter-spacing:-.01em; }
    .rpt-meta{ display:flex; flex-wrap:wrap; align-items:center; gap:8px 10px; font-size:12px; line-height:1.5; color:#6b7a6e; }
    .rpt-meta-dot{ width:6px; height:6px; border-radius:999px; display:inline-block; flex:none; }
    .rpt-stack{ display:grid; gap:18px; }
    .rpt-card{ background:#fff; border:1px solid #e6e9e1; border-radius:14px; overflow:hidden; }
    .rpt-section-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .rpt-section-title{ font-size:12.5px; font-weight:800; color:#1a2e1f; }
    .rpt-note-row{ display:flex; gap:10px; padding:2px 0; }
    .rpt-num{ min-width:26px; width:26px; height:26px; border-radius:999px; background:#f6f7f5; border:1px solid #e6e9e1; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; color:#6b7a6e; flex:none; margin-top:1px; padding:0 2px; tabular-nums:tabular-nums; }
    @media(max-width:640px){ .rpt-num{ min-width:28px; width:28px; height:28px; font-size:11px; } }
    .rpt-note-card{ background:#fff; border:1px solid #e6e9e1; border-radius:14px; padding:12px 14px; display:flex; gap:10px; transition:border-color .15s, box-shadow .15s, transform .12s; }
    .rpt-note-card:hover{ border-color:#d4ddd3; box-shadow:0 4px 12px -4px rgba(26,46,31,.08); transform:translateY(-1px); }
    @media(max-width:640px){ .rpt-note-card{ padding:10px 11px; border-radius:12px; } .rpt-note-card:hover{ transform:none; box-shadow:0 2px 8px -4px rgba(26,46,31,.08); } }
    .rpt-note-body{ min-width:0; flex:1; }
    .rpt-note-text{ font-size:13.5px; line-height:1.75; color:#1a2e1f; word-break:break-word; }
    .rpt-note-meta{ margin-top:6px; display:flex; flex-wrap:wrap; align-items:center; gap:6px 10px; font-size:11.5px; line-height:1.6; color:#9aa99a; }
    .rpt-note-meta b{ font-weight:700; color:#6b7a6e; unicode-bidi:isolate; direction:ltr; display:inline-block; }
    .rpt-note-meta .tabular-nums{ unicode-bidi:isolate; direction:ltr; display:inline-block; }
    @media(max-width:640px){ .rpt-note-meta{ gap:5px 8px; font-size:11px; } }
    .rpt-note-actions{ display:flex; align-items:center; gap:4px; flex:none; padding-top:1px; }
    .rpt-ic{ width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; border:1px solid transparent; background:transparent; color:#9aa99a; transition:all .15s; }
    .rpt-ic:hover{ background:#f6f7f5; border-color:#e6e9e1; color:#1a2e1f; }
    .rpt-ic-danger:hover{ background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
    @media(max-width:640px){ .rpt-ic{ width:34px; height:34px; } }
    .rpt-preview-head{ display:flex; align-items:center; justify-content:space-between; gap:8px; padding:9px 12px; border-bottom:1px solid #eceee9; background:#f9faf8; border-radius:14px 14px 0 0; }
    .rpt-preview-viewport{ background:#ece7d9; padding:16px; max-height:560px; overflow:auto; border-radius:0 0 14px 14px; box-shadow:inset 0 2px 8px rgba(28,25,21,.06); scroll-behavior:smooth; -webkit-overflow-scrolling:touch; }
    @media(max-width:640px){ .rpt-preview-viewport{ padding:10px; max-height:480px; overflow:auto; } }
    .rpt-preview-viewport .report{ margin:0 auto; min-height:auto !important; box-shadow:0 8px 24px rgba(28,25,21,.12); border-radius:6px; }
    .rpt-preview-viewport .report-preview{ background:transparent !important; border:none !important; padding:0 !important; }
    /* sheet inside viewport */
    .rpt-preview-viewport .sheet-mini{ box-shadow:0 8px 24px rgba(28,25,21,.14); }
    .rpt-field{ display:grid; gap:6px; }
    .rpt-label{ font-size:11.5px; font-weight:700; color:#6b7a6e; }
    .rpt-input{ width:100%; border:1px solid #e6e9e1; border-radius:10px; padding:10px 12px; font-size:13.5px; line-height:1.6; color:#1a2e1f; background:#fff; transition:border-color .15s,box-shadow .15s; }
    .rpt-input:focus{ outline:none; border-color:#0e6a38; box-shadow:0 0 0 3px rgba(14,106,56,.08); }
    .rpt-publish{ display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border:1px solid #d9e6dd; background:#f6f7f5; border-radius:12px; }
    @media(max-width:640px){ .rpt-publish{ flex-direction:column; align-items:stretch; } .rpt-publish .rpt-btn{ width:100%; justify-content:center; } }
    @media(max-width:640px){
        .rpt-page{ padding:0 2px; }
        .rpt-head{ gap:10px; padding-bottom:10px; }
        .rpt-card{ border-radius:12px; }
        .rpt-note-row{ gap:8px; padding:10px 0; }
        .rpt-note-text{ font-size:13px; line-height:1.6; }
        .rpt-note-meta{ gap:4px 6px; font-size:11px; }
        .rpt-ic{ width:36px; height:36px; min-width:36px; min-height:36px; }
        .rpt-preview-viewport{ padding:10px; border-radius:0 0 12px 12px; }
        .rpt-input{ font-size:16px; min-height:44px; }
        .rpt-section-head{ margin-bottom:8px; }
        .rpt-section-title{ font-size:12px; }
        .sheet-mini{ border-radius:4px; max-width:100%; }
        #final-data-editor .rpt-card{ padding:10px !important; }
        #attach-form .grid{ gap:6px; }
        #attach-form label{ min-height:44px; }
    }

    /* dark */
    html.dark .rpt-head{ border-color:#2e352e; }
    html.dark .rpt-head-title{ color:#e7ece5; }
    html.dark .rpt-meta{ color:#9bb0a0; }
    html.dark .rpt-card{ background:#232926 !important; border-color:#333b34 !important; }
    html.dark .rpt-note-card{ background:#232926 !important; border-color:#333b34 !important; }
    html.dark .rpt-note-card:hover{ border-color:#3a443b !important; box-shadow:0 4px 12px -4px rgba(0,0,0,.25) !important; }
    html.dark .rpt-note-row + .rpt-note-row{ border-color:#2e352e !important; }
    html.dark .rpt-num{ background:#2a302b !important; border-color:#333b34 !important; color:#9bb0a0 !important; }
    html.dark .rpt-note-text{ color:#e7ece5 !important; }
    html.dark .rpt-note-meta{ color:#8a9a8a !important; }
    html.dark .rpt-note-meta b{ color:#b9c6bb !important; }
    html.dark .rpt-ic{ color:#9bb0a0 !important; }
    html.dark .rpt-ic:hover{ background:#2e352e !important; border-color:#343a34 !important; color:#e7ece5 !important; }
    html.dark .rpt-preview-head{ background:#2a302b !important; border-color:#333b34 !important; border-radius:14px 14px 0 0; }
    html.dark .rpt-preview-head span{ color:#e7ece5 !important; }
    html.dark .rpt-preview-viewport{ background:#1e2320 !important; box-shadow:inset 0 2px 8px rgba(0,0,0,.15); }
    html.dark .rpt-label{ color:#9bb0a0 !important; }
    html.dark .rpt-input{ background:#2a302b !important; border-color:#343a34 !important; color:#e7ece5 !important; }
    html.dark .rpt-publish{ background:#1e2320 !important; border-color:#333b34 !important; }
    html.dark [data-menu-list]{ background:#232926 !important; border-color:#333b34 !important; }
    html.dark [data-menu-list] .m-item{ color:#e7ece5 !important; }
    html.dark [data-menu-list] .m-item:hover{ background:#2e352e !important; }
    html.dark .rpt-section-title{ color:#e7ece5 !important; }
    html.dark .bg-\[\#e8f3ec\]{ background:#1e3328 !important; border-color:#1e3d25 !important; color:#4ade80 !important; }
    html.dark .bg-\[\#fef3c7\]{ background:#2e2716 !important; border-color:#3d3416 !important; color:#f0c040 !important; }
    html.dark .bg-\[\#fef2f2\]{ background:#2d1f1f !important; border-color:#3d2626 !important; color:#f08080 !important; }
    html.dark .bg-\[\#f9faf8\]{ background:#232926 !important; border-color:#333b34 !important; color:#9bb0a0 !important; }
    html.dark .bg-\[\#fdfcfa\]{ background:#1e2320 !important; border-color:#333b34 !important; }
    html.dark .border-\[\#eceee9\]{ border-color:#333b34 !important; }
    html.dark details.bg-white{ background:#232926 !important; border-color:#333b34 !important; }
    html.dark #attach-form{ background:#1e2320 !important; border-color:#333b34 !important; }
    html.dark #attach-form label{ background:#2a302b !important; border-color:#333b34 !important; }
    html.dark #attach-form label span{ color:#e7ece5 !important; }

    /* ── بطاقة «لا ملاحظات» + لوحة الإرفاق: أصناف مخصصة لايت/دارك (لا تعتمد بناء Tailwind) ── */
    .epn-card{ background:#fff; border:1px solid #e6e9e1; border-radius:18px; box-shadow:0 1px 3px rgba(26,46,31,.05),0 10px 28px -14px rgba(26,46,31,.18); overflow:hidden; }
    .epn-top{ position:relative; padding:36px 20px 26px; text-align:center; overflow:hidden; }
    .epn-top::before{ content:''; position:absolute; inset:0; background:radial-gradient(440px 190px at 50% -50px, rgba(14,106,56,.10), transparent 70%); pointer-events:none; }
    .epn-medal{ position:relative; width:104px; height:104px; margin:0 auto; }
    .epn-ring{ position:absolute; inset:0; border-radius:999px; border:2px dashed #b6c9bb; animation:epn-spin 28s linear infinite; }
    @keyframes epn-spin{ to{ transform:rotate(360deg); } }
    .epn-core{ position:absolute; inset:13px; border-radius:999px; background:linear-gradient(135deg,#eef4f0,#d9e7dd); display:flex; align-items:center; justify-content:center; box-shadow:inset 0 2px 8px rgba(14,106,56,.12), 0 6px 16px -8px rgba(14,106,56,.35); }
    .epn-core svg{ width:38px; height:38px; color:#0e6a38; }
    .epn-plus{ position:absolute; bottom:0; inset-inline-end:0; min-width:30px; height:30px; padding:0 6px; border-radius:999px; background:#0e6a38; color:#fff; font-size:19px; font-weight:800; line-height:30px; border:3px solid #fff; box-shadow:0 6px 14px -4px rgba(14,106,56,.55); }
    .epn-title{ position:relative; margin-top:18px; font-size:15.5px; font-weight:800; color:#1a2e1f; }
    .epn-hint{ position:relative; margin:6px auto 0; max-width:310px; font-size:12.5px; line-height:1.9; color:#6b7a6e; }
    .epn-cta{ position:relative; margin-top:18px; display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; }
    .epn-btn{ display:inline-flex; align-items:center; gap:7px; min-height:42px; padding:0 22px; border:none; border-radius:12px; background:linear-gradient(180deg,#138149,#0e6a38); color:#fff; font-size:13px; font-weight:800; cursor:pointer; box-shadow:0 8px 18px -8px rgba(14,106,56,.65), inset 0 1px 0 rgba(255,255,255,.20); transition:transform .12s, box-shadow .15s, filter .15s; }
    .epn-btn:hover{ filter:brightness(1.07); box-shadow:0 12px 24px -8px rgba(14,106,56,.65); transform:translateY(-1px); }
    .epn-btn:active{ transform:none; }
    .epn-btn svg{ width:15px; height:15px; }
    .epn-link{ display:inline-flex; align-items:center; min-height:42px; padding:0 16px; border-radius:12px; border:1px solid #e6e9e1; background:#fff; color:#44564d; font-size:12.5px; font-weight:700; transition:border-color .15s, color .15s, background .15s; }
    .epn-link:hover{ border-color:#0e6a38; color:#0e6a38; background:#f6f7f5; }
    .epn-foot{ display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap; padding:12px 14px; background:#f9faf8; border-top:1px solid #eceee9; }
    .epn-chip{ display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:700; color:#6b7a6e; background:#fff; border:1px solid #e6e9e1; border-radius:999px; padding:5px 12px; }
    .epn-chip svg{ width:13px; height:13px; color:#9aa99a; flex:none; }
    html.dark .epn-card{ background:#232926; border-color:#333b34; }
    html.dark .epn-top::before{ background:radial-gradient(440px 190px at 50% -50px, rgba(74,222,128,.12), transparent 70%); }
    html.dark .epn-ring{ border-color:#3a4d3d; }
    html.dark .epn-core{ background:linear-gradient(135deg,#1e3328,#26432f); box-shadow:inset 0 2px 8px rgba(0,0,0,.4); }
    html.dark .epn-core svg{ color:#4ade80; }
    html.dark .epn-plus{ border-color:#232926; background:#4ade80; color:#0b2417; }
    html.dark .epn-title{ color:#e7ece5; }
    html.dark .epn-hint{ color:#9bb0a0; }
    html.dark .epn-link{ background:#2a302b; border-color:#333b34; color:#b9c6bb; }
    html.dark .epn-link:hover{ border-color:#4ade80; color:#4ade80; background:#2e352e; }
    html.dark .epn-foot{ background:#1e2320; border-color:#333b34; }
    html.dark .epn-chip{ background:#2a302b; border-color:#333b34; color:#9bb0a0; }
    html.dark .epn-chip svg{ color:#55645a; }
    @media (prefers-reduced-motion: reduce){ .epn-ring{ animation:none; } }

    .epa{ margin-top:14px; background:#fff; border:1px solid #e6e9e1; border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(26,46,31,.05); }
    .epa-sum{ display:flex; align-items:center; gap:10px; padding:12px 14px; font-size:13px; font-weight:800; color:#0e6a38; cursor:pointer; list-style:none; user-select:none; transition:background .15s; }
    .epa-sum::-webkit-details-marker{ display:none; }
    .epa-sum:hover{ background:#f6f7f5; }
    .epa[open] .epa-sum{ background:#eef4f0; }
    .epa-ic{ width:28px; height:28px; border-radius:9px; background:#0e6a38; color:#fff; display:inline-flex; align-items:center; justify-content:center; flex:none; transition:transform .2s, background .15s; }
    .epa[open] .epa-ic{ transform:rotate(45deg); }
    .epa-ic svg{ width:15px; height:15px; }
    .epa-count{ font-size:11px; font-weight:700; color:#6b7a6e; background:#fff; border:1px solid #e6e9e1; border-radius:999px; padding:2px 10px; white-space:nowrap; }
    .epa-chev{ margin-inline-start:auto; width:16px; height:16px; color:#9aa99a; flex:none; transition:transform .18s; }
    .epa[open] .epa-chev{ transform:rotate(180deg); }
    .epa-body{ padding:12px; background:#fdfcfa; border-top:1px solid #eceee9; }
    .epa-grid{ display:grid; gap:8px; max-height:240px; overflow:auto; border-radius:12px; background:#f6f7f5; border:1px solid #eceee9; padding:10px; }
    @media(min-width:640px){ .epa-grid{ grid-template-columns:1fr 1fr; } }
    .epa-opt{ display:flex; align-items:flex-start; gap:10px; font-size:12.5px; padding:10px 12px; border-radius:12px; background:#fff; border:1px solid #e6e9e1; cursor:pointer; transition:border-color .15s, box-shadow .15s, background .15s; margin:0; }
    .epa-opt:hover{ border-color:rgba(14,106,56,.45); box-shadow:0 3px 10px -5px rgba(14,106,56,.3); }
    .epa-opt:has(:checked){ border-color:#0e6a38; background:#eef4f0; box-shadow:0 0 0 3px rgba(14,106,56,.10); }
    .epa-opt input{ width:17px; height:17px; margin-top:2px; accent-color:#0e6a38; flex:none; cursor:pointer; }
    .epa-opt .t{ color:#1a2e1f; line-height:1.75; min-width:0; overflow-wrap:anywhere; }
    .epa-opt .d{ color:#6b7a6e; }
    .epa-foot{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:12px; }
    .epa-submit{ display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:0 22px; border:none; border-radius:10px; background:#0e6a38; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:background .15s, opacity .15s; }
    .epa-submit:hover:not(:disabled){ background:#0a4d28; }
    .epa-submit:disabled{ opacity:.4; cursor:not-allowed; }
    .epa-countline{ font-size:12px; color:#9aa99a; }
    .epa-err{ margin-top:8px; font-size:12px; font-weight:700; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:8px 12px; }
    .epa-empty{ font-size:12.5px; color:#9aa99a; background:#f9faf8; border-radius:10px; padding:14px; text-align:center; line-height:1.9; }
    html.dark .epa{ background:#232926; border-color:#333b34; }
    html.dark .epa-sum{ color:#4ade80; }
    html.dark .epa-sum:hover{ background:#2a302b; }
    html.dark .epa[open] .epa-sum{ background:#1e3328; }
    html.dark .epa-ic{ background:#4ade80; color:#0b2417; }
    html.dark .epa-count{ background:#2a302b; border-color:#333b34; color:#9bb0a0; }
    html.dark .epa-chev{ color:#6b7a6e; }
    html.dark .epa-body{ background:#1e2320; border-color:#333b34; }
    html.dark .epa-grid{ background:#242b25; border-color:#333b34; }
    html.dark .epa-opt{ background:#2a302b; border-color:#333b34; }
    html.dark .epa-opt:hover{ border-color:rgba(74,222,128,.5); }
    html.dark .epa-opt:has(:checked){ border-color:#4ade80; background:#1e3328; box-shadow:0 0 0 3px rgba(74,222,128,.12); }
    html.dark .epa-opt .t{ color:#e7ece5; }
    html.dark .epa-opt .d{ color:#9bb0a0; }
    html.dark .epa-countline{ color:#6b7a6e; }
    html.dark .epa-err{ color:#f08080; background:#2d1f1f; border-color:#3d2626; }
    html.dark .epa-empty{ color:#6b7a6e; background:#242b25; }
    html.dark .bg-white.border-\[\#e6e9e1\]{ background:#232926 !important; border-color:#333b34 !important; }
    html.dark .border-\[\#cde7d6\]{ border-color:#1e3d25 !important; }
    html.dark .border-\[\#fde68a\]{ border-color:#3d3416 !important; }
    html.dark .border-\[\#fecaca\]{ border-color:#3d2626 !important; }
    html.dark .text-\[\#0e6a38\]{ color:#4ade80 !important; }
    html.dark .text-\[\#92400e\]{ color:#f0c040 !important; }
    html.dark .text-\[\#b91c1c\]{ color:#f08080 !important; }
    html.dark .text-\[\#b45309\]{ color:#f0c040 !important; }
    html.dark .text-\[\#6b7a6e\]{ color:#9bb0a0 !important; }
    html.dark .text-\[\#9aa99a\]{ color:#8a9a8a !important; }
    html.dark #preview-modal-body{ background:#1e2320 !important; }
    /* report paper in dark — dark desk, not stark white sheet */
    html.dark .rpt-preview-viewport .report{
        background:#232926 !important;
        color:#e7ece5 !important;
        --paper:#232926;
        --ink:#e7ece5;
        --ink-soft:#9bb0a0;
        --gold:#3a7a52;
        --gold-soft:#2a4a35;
        --rule:#333b34;
        border-color:#333b34 !important;
        outline-color:#1e3d25 !important;
        box-shadow:0 8px 24px rgba(0,0,0,.35) !important;
    }
    html.dark .rpt-preview-viewport .report .report__ministry-sub,
    html.dark .rpt-preview-viewport .report .report__dayline-label,
    html.dark .rpt-preview-viewport .report .report__infobar-label,
    html.dark .rpt-preview-viewport .report .report__obs-label,
    html.dark .rpt-preview-viewport .report .report__person-role,
    html.dark .rpt-preview-viewport .report .report__signature-caption{ color:#9bb0a0 !important; }
    html.dark .rpt-preview-viewport .report .report__divider::before,
    html.dark .rpt-preview-viewport .report .report__divider::after{ border-color:#2a4a35 !important; }
</style>

<div class="rpt-page">
    <div class="rpt-head">
        <div class="flex items-start gap-2.5 min-w-0 flex-1">
            <a href="{{ route('reports.index') }}" class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e6e9e1] dark:border-[#333b34] bg-white dark:bg-[#232926] text-[#6b7a6e] dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] transition mt-0.5" aria-label="{{ __('ui.back_to_reports') }}">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="rpt-head-title truncate">{{ l10n_text('report', $report->id, 'title', $report->title) }}</h1>
                <div class="rpt-meta mt-1">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="rpt-meta-dot {{ $report->isPublished() ? 'bg-[#0e6a38]' : 'bg-[#d97706]' }}"></span>
                        <span class="font-bold {{ $report->isPublished() ? 'text-[#0e6a38]' : 'text-[#b45309]' }}">{{ $report->isPublished() ? __('ui.published') : __('ui.draft') }}</span>
                    </span>
                    <span class="w-px h-3 bg-[#e6e9e1] hidden sm:inline-block"></span>
                    <span class="tabular-nums">{{ $report->report_date->toDateString() }}</span>
                    <span>·</span>
                    <span>{{ $notesCount }} {{ $notesCount === 1 ? __('ui.note_one') : __('ui.notes_many') }}</span>
                    @if(!$report->visible_to_monitors)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-[#fef3c7] text-[11px] font-bold text-[#92400e] border border-[#fde68a]">{{ __('ui.hidden_from_monitors') }}</span>
                    @endif
                    @if($isOwner && $report->isPublished())
                        <span class="hidden sm:inline text-[#9aa99a]">·</span>
                        <span class="text-[11.5px] text-[#9aa99a]">{{ __('ui.published_since') }} {{ $report->published_at?->format('Y-m-d H:i') }}@if($deadline && !$locked) · {{ __('ui.edit_until') }} {{ $deadline->format('Y-m-d H:i') }}@elseif($locked) · <span class="font-bold text-[#b45309]">{{ __('ui.locked') }}</span>@endif</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-1.5 shrink-0">
            @can('export', $report)
                @if($canPrint ?? false)
                <a href="{{ route('reports.print', $report) }}" class="hidden sm:inline-flex items-center justify-center h-8 px-3 rounded-lg border border-[#e6e9e1] dark:border-[#333b34] bg-white dark:bg-[#232926] text-[12px] font-bold text-[#6b7a6e] dark:text-[#9bb0a0] hover:border-[#0e6a38] hover:text-[#0e6a38] dark:hover:text-[#4ade80] transition">{{ __('ui.print') }}</a>
                <a href="{{ route('reports.print', $report) }}" class="sm:hidden inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e6e9e1] dark:border-[#333b34] bg-white dark:bg-[#232926] text-[#6b7a6e] dark:text-[#9bb0a0] hover:border-[#0e6a38] hover:text-[#0e6a38] dark:hover:text-[#4ade80] transition" aria-label="{{ __('ui.print') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18h12M8 14h8M6 9h12a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4a2 2 0 012-2z"/></svg>
                </a>
                @endif
            @endcan
            @if($pageMenu)
            <div data-menu>
                <button type="button" data-menu-btn aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('ui.report_actions') }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e6e9e1] dark:border-[#333b34] bg-white dark:bg-[#232926] text-[#6b7a6e] dark:text-[#9bb0a0] hover:border-[#0e6a38] dark:hover:text-[#4ade80] hover:text-[#0e6a38] transition">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>
                </button>
                <div data-menu-list class="hidden" role="menu">
                    @if($isOwner && !$locked)
                    <a href="{{ route('reports.edit', $report) }}" class="m-item" role="menuitem">{{ __('ui.edit_data') }}</a>
                    @endif
                    @if($isOwner && !$isDraft && !$locked)
                    <form method="POST" action="{{ route('reports.unpublish', $report) }}" onsubmit="return confirm('{{ __('ui.unpublish_q') }}')">@csrf<button type="submit" class="m-item" role="menuitem">{{ __('ui.unpublish') }}</button></form>
                    @endif
                    @if($isOwner && ($isDraft || !$locked))
                    <form method="POST" action="{{ route('reports.destroy', $report) }}" onsubmit="return confirm('{{ __('ui.delete_final_q') }}')">@csrf @method('DELETE')<button type="submit" class="m-item m-danger" role="menuitem">{{ __('ui.delete_report') }}</button></form>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="flex items-center gap-2 bg-[#e8f3ec] border border-[#cde7d6] text-[#0e6a38] rounded-xl px-3.5 py-2.5 mt-3 text-[13px] font-bold rpt-anim-in">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flex items-center gap-2 bg-[#fef2f2] border border-[#fecaca] text-[#b91c1c] rounded-xl px-3.5 py-2.5 mt-3 text-[13px] font-bold rpt-anim-in">{{ $errors->first() }}</div>@endif
    @if(($pvState ?? 'none') === 'stale')
    <div class="mt-3 flex items-start gap-2 bg-[#fef3c7] border border-[#fde68a] text-[#92400e] rounded-xl px-3.5 py-2.5 text-[12.5px] leading-5 font-bold">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <span>{{ __('ui.stale_approved') }}</span>
    </div>
    @endif

    <div class="rpt-stack mt-4">
        <div class="rpt-card">
            <div class="rpt-preview-head">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-extrabold tracking-wide text-[#6b7a6e]">{{ __('ui.preview') }}</span>
                    @if(($pvState ?? 'none') === 'stale')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-[#fef3c7] text-[#92400e] border border-[#fde68a]">{{ __('ui.stale_badge') }}</span>
                    @elseif($pvState === 'custom')
                        <span id="pv-state" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#e8f3ec] text-[#0e6a38] border border-[#cde7d6]">{{ __('ui.rpt_state_custom') }}</span>
                    @elseif($pvState === 'system')
                        <span id="pv-state" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#e8f3ec] text-[#0e6a38] border border-[#cde7d6]">{{ __('ui.rpt_state_system') }}</span>
                    @else
                        <span id="pv-state" class="hidden"></span>
                    @endif
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" id="preview-expand-btn" class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-[#e6e9e1] dark:border-[#333b34] bg-white dark:bg-[#232926] text-[#6b7a6e] dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] transition" aria-label="{{ __('ui.expand_full') }}" title="{{ __('ui.expand_full') }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                    </button>
                </div>
            </div>
            <link rel="stylesheet" href="{{ asset('report/css/report-engine.css') }}?v=18">
            <div id="paper-preview-wrap" data-paper-fit data-report-id="{{ $report->id }}" class="rpt-preview-viewport">
                <p id="report-l10n-badge" class="hidden text-[11px] font-bold text-[#0e6a38] bg-[#e8f3ec] border border-[#cde7d6] rounded-lg px-2.5 py-1 mb-2 w-fit" role="status"></p>
                @if(!empty($htmlPreview ?? null))
                    <div id="paper-preview-html" class="report-preview" data-paper-fit-inner>
                        {!! $htmlPreview !!}
                    </div>
                @elseif($sheet ?? null)
                    <div class="sheet-mini" id="sheet-mini" style="background-image:url('{{ $shImg }}')">
                        @include('reports.partials.sheet_fields')
                    </div>
                @else
                    @if($notesCount === 0)
                    @include('reports.partials.empty_preview')
                    @elseif(trim((string) l10n_text('report', $report->id, 'content', $preview)) !== '')
                    <div class="text-[13.5px] leading-7 text-[#1a2e1f] dark:text-[#e7ece5] whitespace-pre-wrap bg-white dark:bg-[#232926] border border-[#e6e9e1] dark:border-[#333b34] rounded-xl px-3.5 py-3">{{ l10n_text('report', $report->id, 'content', $preview) }}</div>
                    @else
                    <div class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-[#232926] border border-dashed border-[#d4ddd3] dark:border-[#333b34] rounded-xl">
                        <div class="w-9 h-9 rounded-lg bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#e6e9e1] dark:border-[#333b34] flex items-center justify-center shrink-0">
                            <svg class="w-4.5 h-4.5 text-[#c2cbc1] dark:text-[#4a5a4f]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[12.5px] font-semibold text-[#6b7a6e] dark:text-[#9bb0a0]">{{ __('ui.no_notes_yet') }}</p>
                            <p class="text-[11.5px] text-[#9aa99a] dark:text-[#6b7a6e] mt-0.5">{{ __('ui.report_auto_attach') }}</p>
                        </div>
                    </div>
                    @endif
                @endif
            </div>
            <div id="paper-preview-error" class="hidden px-3 py-2 text-[11.5px] font-bold text-[#b91c1c] bg-[#fef2f2] border-t border-[#fecaca]"></div>
            @if(!empty($renderError ?? null))
            <p class="px-3 py-2 text-[12px] font-bold text-[#b91c1c] bg-[#fef2f2] border-t border-[#fecaca]">{{ $renderError }}</p>
            @endif
            <div class="flex items-center justify-between gap-2 px-3 py-2 bg-[#f9faf8] dark:bg-[#1e2320] border-t border-[#eceee9] dark:border-[#333b34] text-[11px]">
                <span class="text-[#9aa99a] dark:text-[#6b7a6e]">{{ __('ui.a4_print_match') }}</span>
                @if($canPrint ?? false)
                <a href="{{ route('reports.preview', $report) }}" class="font-bold text-[#0e6a38] dark:text-[#4ade80] hover:underline inline-flex items-center gap-1">
                    {{ __('ui.open') }}
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6v6m-11 5L21 3"/></svg>
                </a>
                @endif
            </div>
        </div>
        @include('reports.partials.paper_fit')
        <div id="preview-modal" class="hidden fixed inset-0 z-50">
            <div class="absolute inset-0 bg-[#0f1a13]/70 backdrop-blur-sm" id="preview-modal-backdrop"></div>
            <div class="relative h-full flex flex-col p-3 sm:p-6">
                <div class="flex items-center justify-between gap-3 max-w-[900px] w-full mx-auto mb-3">
                    <div class="text-white">
                        <div class="text-sm font-extrabold">{{ l10n_text('report', $report->id, 'title', $report->title) }}</div>
                        <div class="text-[11px] text-white/70">{{ $report->report_date->toDateString() }} · {{ $notesCount }} {{ __('ui.notes_many') }}</div>
                    </div>
                    <button type="button" id="preview-modal-close" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white/10 hover:bg-white/15 text-white backdrop-blur">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 min-h-0 max-w-[900px] w-full mx-auto bg-[#ece7d9] rounded-xl overflow-auto p-3 sm:p-4" id="preview-modal-body"></div>
            </div>
        </div>

        @if($isOwner && $isDraft)
        <div class="rpt-publish">
            <div class="min-w-0">
                <div class="text-[12px] font-extrabold text-[#1a2e1f] dark:text-[#e7ece5]">{{ __('ui.publish') }}</div>
                @if($canPublish)
                    <div class="text-[12px] leading-5 text-[#6b7a6e] dark:text-[#9bb0a0] mt-0.5">{{ __('ui.publish_ready_hint') }}</div>
                @else
                    <div id="publish-hint" class="text-[12px] leading-5 text-[#6b7a6e] dark:text-[#9bb0a0] mt-0.5">{{ $publishHint }}</div>
                @endif
            </div>
            @if($canPublish)
            <form method="POST" action="{{ route('reports.publish', $report) }}" onsubmit="return confirm('{{ __('ui.publish_q') }}')" class="shrink-0">
                @csrf
                <button id="publish-btn" class="rpt-btn inline-flex items-center justify-center min-h-[36px] px-5 rounded-lg bg-[#0e6a38] text-white text-[13px] font-bold hover:bg-[#0a4d28] shadow-sm">{{ __('ui.publish_report') }}</button>
            </form>
            @else
            <form id="publish-form" method="POST" action="{{ route('reports.publish', $report) }}" onsubmit="return confirm('{{ __('ui.publish_q') }}')" class="hidden shrink-0">
                @csrf
                <button id="publish-btn" disabled class="rpt-btn inline-flex items-center justify-center min-h-[36px] px-5 rounded-lg bg-[#0e6a38] text-white text-[13px] font-bold disabled:opacity-40 disabled:cursor-not-allowed">{{ __('ui.publish_report') }}</button>
            </form>
            @endif
        </div>
        @endif

        
        @if($isDraft || empty($htmlPreview ?? null))
        <section>
            <div class="rpt-section-head">
                <h2 class="rpt-section-title">{{ __('ui.notes_with_count', ['n' => $notesCount]) }}</h2>
                @if($notesCount > 1)<span class="hidden sm:inline text-[11px] text-[#9aa99a] font-semibold">{{ __('ui.sorted_by_approval') }}</span>@endif
            </div>
            @if($notesCount)
            <div class="bg-[#f9faf8] border border-[#eceee9] rounded-xl p-2.5 sm:p-3">
            <ol id="notes-order-list" class="grid gap-2.5 sm:gap-3">
                    @foreach($report->notes as $idx => $n)
                    <li data-note-id="{{ $n->id }}" data-i18n-entity="note" data-i18n-id="{{ $n->id }}" class="rpt-note-card group">
                        <div class="rpt-note-row w-full">
                        <span class="rpt-num order-num tabular-nums" dir="ltr">{{ $idx + 1 }}</span>
                        <div class="rpt-note-body">
                            <p class="rpt-note-text">{{ l10n_text('note', $n->id, 'description', $n->description) }}</p>
                            <div class="rpt-note-meta">
                                <span class="inline-flex items-center gap-1 whitespace-nowrap"><span class="w-1 h-1 rounded-full bg-[#c2cbc1] shrink-0"></span> <span>{{ __('ui.camera') }}</span> <b class="tabular-nums" dir="ltr">{{ $n->camera_number }}</b></span>
                                <span class="hidden sm:inline opacity-60">·</span>
                                <span class="inline-flex items-center gap-1 whitespace-nowrap"><span>{{ __('ui.floor') }}</span> <b class="tabular-nums" dir="ltr">{{ $n->floor_number }}</b></span>
                                <span class="hidden sm:inline opacity-60">·</span>
                                <span class="tabular-nums whitespace-nowrap" dir="ltr">{{ $n->observed_at?->format('H:i') }}</span>
                                @if($n->attachments && $n->attachments->count())
                                    <span class="hidden sm:inline opacity-60">·</span>
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap text-[#6b7a6e]"><svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> <span dir="ltr">{{ $n->attachments->count() }}</span></span>
                                @endif
                                @if($isOwner && $isDraft)
                                <span class="ms-auto inline-flex items-center gap-0.5">
                                    <button type="button" class="reorder-btn rpt-ic" data-dir="-1" aria-label="{{ __('ui.move_up') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" class="reorder-btn rpt-ic" data-dir="1" aria-label="{{ __('ui.move_down') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('reports.detach', [$report, $n->id]) }}" onsubmit="return confirm('{{ __('ui.remove_note_confirm') }}')" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rpt-ic rpt-ic-danger" aria-label="{{ __('ui.remove') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                </span>
                                @endif
                            </div>
                        </div>
                        </div>
                    </li>
                    @endforeach
                </ol>
            </div>
                @if($isOwner && $isDraft && $notesCount > 1)
                <div id="reorder-error" class="hidden mt-2 rounded-lg bg-[#fef2f2] border border-[#fecaca] text-[#b91c1c] px-3 py-2 text-[12px] font-bold"></div>
                @endif
            @else
            <div class="epn-card">
                <div class="epn-top">
                    <div class="epn-medal" aria-hidden="true">
                        <span class="epn-ring"></span>
                        <span class="epn-core">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <span class="epn-plus">+</span>
                    </div>
                    <h3 class="epn-title">{{ __('ui.no_notes_yet') }}</h3>
                    <p class="epn-hint">{{ __('ui.report_auto_attach') }}</p>
                    @if($isOwner && $isDraft)
                    <div class="epn-cta">
                        <button type="button" class="epn-btn" onclick="var d=document.getElementById('attach-details');if(d){d.open=true;d.scrollIntoView({behavior:'smooth',block:'start'});}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            {{ __('ui.add_notes') }}
                        </button>
                        <a href="{{ route('reports.edit', $report) }}" class="epn-link">{{ __('ui.edit_btn') }}</a>
                    </div>
                    @endif
                </div>
                <div class="epn-foot">
                    <span class="epn-chip"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> {{ __('ui.camera') }}</span>
                    <span class="epn-chip"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> {{ __('ui.floor') }}</span>
                    <span class="epn-chip"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> <span class="tabular-nums">{{ $report->report_date->toDateString() }}</span></span>
                </div>
            </div>
            @endif
            @if($isOwner && $isDraft)
            <details id="attach-details" class="epa">
                <summary class="epa-sum">
                    <span class="epa-ic">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    {{ __('ui.add_notes') }} @if(isset($candidates) && $candidates->count())<span class="epa-count">{{ $candidates->count() }} {{ __('ui.available') }}</span>@endif
                    <svg class="epa-chev" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <form method="POST" action="{{ route('reports.attach', $report) }}" id="attach-form" class="epa-body">
                    @csrf
                    @if(isset($candidates) && $candidates->count())
                    <div class="epa-grid">
                        @foreach($candidates as $c)
                        <label class="epa-opt">
                            <input type="checkbox" name="note_ids[]" value="{{ $c->id }}" checked class="attach-check">
                            <span class="t">{{ __('ui.camera') }} {{ $c->camera_number }} · {{ __('ui.floor') }} {{ $c->floor_number }} · <span class="tabular-nums">{{ $c->observed_at?->format('H:i') }}</span><br><span class="d">{{ \Illuminate\Support\Str::limit(l10n_text('note', $c->id, 'description', $c->description), 70) }}</span></span>
                        </label>
                        @endforeach
                    </div>
                    @else
                    <div class="epa-empty">
                        {{ __('ui.no_candidates') }}
                        <div class="text-[11.5px] mt-1">{{ __('ui.no_candidates_hint') }}</div>
                    </div>
                    @endif
                    <div class="epa-foot">
                        <button id="attach-submit" @disabled(!isset($candidates) || $candidates->isEmpty()) class="epa-submit">{{ __('ui.add_selected') }}</button>
                        <span id="attach-count" class="epa-countline"></span>
                        @if(isset($candidatesTruncated) && $candidatesTruncated)<span class="text-[11px] text-[#b45309]">{{ __('ui.candidates_truncated') }}</span>@endif
                    </div>
                    <p id="attach-error" class="epa-err hidden">{{ __('ui.choose_note') }}</p>
                </form>
            </details>
            @endif
        </section>
        @endif

        @if($showFinal)
        <section id="final-data-editor" class="scroll-mt-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-[#eef4f0] border border-[#cde7d6] flex items-center justify-center text-[#0e6a38] hidden sm:flex">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </span>
                    <h2 class="text-[13px] sm:text-[14px] font-extrabold text-[#1a2e1f] tracking-tight">{{ __('ui.final_data') }}</h2>
                    <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full bg-[#f6f7f5] border border-[#e6e9e1] text-[11px] font-bold text-[#6b7a6e]">{{ count($editorData['observations'] ?? []) }} {{ __('ui.notes_many') }}</span>
                </div>
                <button type="button" id="final-fill-system" class="inline-flex items-center gap-1 text-[11.5px] sm:text-[12px] font-bold text-[#6b7a6e] hover:text-[#0e6a38] px-2.5 py-1.5 rounded-lg border border-transparent hover:border-[#e6e9e1] hover:bg-[#f6f7f5] dark:hover:bg-[#2a302b] transition">{{ __('ui.restore_system') }}</button>
            </div>
            <div class="bg-white border border-[#e6e9e1] rounded-2xl shadow-sm overflow-hidden">
                <div class="p-3 sm:p-4 grid gap-4 sm:gap-5">
                    <div class="grid gap-3 sm:gap-4" id="final-obs-list">
                        @foreach(($editorData['observations'] ?? []) as $i => $obsText)
                        <label class="group grid gap-1.5 p-2.5 sm:p-3 bg-[#fdfcfa] border border-[#eceee9] rounded-xl hover:border-[#d4ddd3] hover:bg-white transition">
                            <span class="flex items-center gap-2 text-[11.5px] font-bold text-[#6b7a6e]"><span class="w-6 h-6 rounded-full bg-white border border-[#e6e9e1] flex items-center justify-center text-[11px] font-extrabold text-[#0e6a38] group-hover:border-[#cde7d6] group-hover:bg-[#eef4f0] transition">{{ $i + 1 }}</span> {{ __('ui.observation_n', ['n' => $i + 1]) }}</span>
                            <textarea rows="3" maxlength="2000" data-obs-idx="{{ $i }}" class="final-obs w-full min-h-[88px] sm:min-h-[96px] p-3 rounded-xl border border-[#e6e9e1] bg-white text-[13.5px] sm:text-[14px] leading-7 text-[#1a2e1f] placeholder:text-[#b0bab2] focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition resize-y shadow-sm">{{ $obsText }}</textarea>
                        </label>
                        @endforeach
                    </div>
                    <label class="grid gap-1.5 p-2.5 sm:p-3 bg-[#fdfcfa] border border-[#eceee9] rounded-xl hover:border-[#d4ddd3] hover:bg-white transition">
                        <span class="flex items-center gap-2 text-[11.5px] font-bold text-[#6b7a6e]"><svg class="w-3.5 h-3.5 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l6 6m-6-4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> {{ __('ui.recommendations') }}</span>
                        <textarea id="final-reco" rows="3" maxlength="2000" class="w-full min-h-[88px] sm:min-h-[96px] p-3 rounded-xl border border-[#e6e9e1] bg-white text-[13.5px] sm:text-[14px] leading-7 text-[#1a2e1f] placeholder:text-[#b0bab2] focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition resize-y shadow-sm" placeholder="{{ __('ui.reco_placeholder') }}">{{ $editorData['recommendations'] ?? '' }}</textarea>
                    </label>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 sm:gap-3 p-3 sm:p-4 bg-[#f9faf8] border-t border-[#eceee9]">
                    <button type="button" id="final-approve-btn" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-10 sm:h-9 px-6 rounded-xl bg-[#0e6a38] text-white text-[13.5px] sm:text-[13px] font-bold hover:bg-[#0a4d28] disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow-md transition">{{ __('ui.approve') }}</button>
                    @if($aiDataEnabled ?? false)
                    <button type="button" id="final-ai-btn" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 h-10 sm:h-9 px-4 rounded-xl border border-[#e6e9e1] bg-white text-[#0e6a38] text-[13.5px] sm:text-[13px] font-bold hover:border-[#0e6a38] hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition">
                        <svg id="final-ai-spin" class="hidden spin w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        <span id="final-ai-label">{{ __('ui.smart_gen') }}</span>
                    </button>
                    @elseif($aiMissingKey ?? false)
                    <button type="button" disabled title="{{ __('api.ai_not_configured') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 h-10 sm:h-9 px-4 rounded-xl border border-[#fecaca] bg-[#fef2f2] text-[#b91c1c] text-[13.5px] sm:text-[13px] font-bold opacity-70 cursor-not-allowed transition">
                        <span>{{ __('ui.smart_gen') }}</span>
                    </button>
                    <span class="text-[11.5px] font-bold text-[#b91c1c]">{{ __('api.ai_not_configured') }}</span>
                    @endif
                    <span id="final-status" class="hidden text-[12px] font-bold text-[#0e6a38] sm:ms-auto text-center sm:text-right"></span>
                </div>
                <p id="final-error" class="hidden mx-3 sm:mx-4 mb-3 sm:mb-4 text-[12px] font-bold text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded-xl px-3 py-2.5"></p>
            </div>
        </section>
        @endif

        @if($showLegacy)
        <section>
            <div class="rpt-section-head"><h2 class="rpt-section-title">{{ __('ui.content_title') }}</h2></div>
            <div class="rpt-card p-3 sm:p-4">
                <form method="POST" action="{{ route('reports.update', $report) }}" class="grid gap-4">
                    @csrf @method('PUT')
                    <label class="rpt-field">
                        <span class="rpt-label">{{ __('ui.exec_summary') }}</span>
                        <textarea name="summary" id="field-summary" rows="4" maxlength="20000" class="rpt-input min-h-[96px] resize-y">{{ old('summary', $report->summary) }}</textarea>
                    </label>
                    <label class="rpt-field">
                        <span class="rpt-label">{{ __('ui.recommendations') }}</span>
                        <textarea name="recommendations" id="field-reco" rows="4" maxlength="20000" class="rpt-input min-h-[96px] resize-y">{{ old('recommendations', $report->recommendations) }}</textarea>
                    </label>
                    <div class="flex items-center gap-2">
                        <button class="rpt-btn inline-flex items-center justify-center h-9 px-5 rounded-lg bg-[#1a2e1f] text-white text-[13px] font-bold hover:bg-black">{{ __('ui.save') }}</button>
                        <span class="text-[11.5px] text-[#9aa99a]">{{ __('ui.manual_text_hint') }}</span>
                    </div>
                </form>
                @if($isWriter)
                <div class="mt-4 pt-4 border-t border-[#eceee9] dark:border-[#333b34]">
                    <div class="rpt-section-head mb-2">
                        <span class="rpt-label">{{ __('ui.gen_draft') }}</span>
                        <span class="text-[11px] text-[#9aa99a]">{{ __('ui.ai_draft_gen') }}</span>
                    </div>
                    <div id="ai-draft-box" class="text-[13.5px] leading-6 text-[#1a2e1f] whitespace-pre-wrap break-words bg-[#f9faf8] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#333b34] rounded-xl px-3.5 py-3 min-h-[56px] max-h-[220px] overflow-auto">{{ $report->ai_draft_content ?: __('ui.no_draft_yet') }}</div>
                    <div id="ai-summary-src" class="hidden">{{ $report->ai_summary }}</div>
                    <div id="ai-reco-src" class="hidden">{{ $report->ai_recommendations }}</div>
                    <div id="ai-error" class="hidden mt-2 rounded-lg bg-[#fef2f2] border border-[#fecaca] px-3 py-2 text-[12px]">
                        <p id="ai-error-msg" class="text-[#b91c1c] font-bold"></p>
                    </div>
                    @if($aiEnabled)
                    @if($aiMissingKey ?? false)
                    <div class="mt-3 rounded-lg bg-[#fef2f2] border border-[#fecaca] px-3 py-2 text-[12px] font-bold text-[#b91c1c]">{{ __('api.ai_not_configured') }}</div>
                    @endif
                    <form id="ai-form" method="POST" action="{{ route('reports.generate', $report) }}" data-ajax="1" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        <button id="ai-generate-btn" type="submit" @disabled($notesCount === 0 || ($aiMissingKey ?? false)) title="{{ ($aiMissingKey ?? false) ? __('api.ai_not_configured') : '' }}" class="rpt-btn inline-flex items-center gap-1.5 h-9 px-4 rounded-lg bg-[#0e6a38] text-white text-[13px] font-bold hover:bg-[#0a4d28] disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg id="ai-spin" class="hidden spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span id="ai-btn-label">{{ __('ui.gen_draft') }}</span>
                        </button>
                        <button id="ai-adopt-summary" type="button" @disabled(!$report->ai_summary) class="rpt-btn h-8 px-3 rounded-lg border border-[#e6e9e1] bg-white text-[12px] font-bold text-[#6b7a6e] hover:border-[#0e6a38] hover:text-[#0e6a38] disabled:opacity-40 disabled:cursor-not-allowed">{{ __('ui.adopt_summary') }}</button>
                        <button id="ai-adopt-reco" type="button" @disabled(!$report->ai_recommendations) class="rpt-btn h-8 px-3 rounded-lg border border-[#e6e9e1] bg-white text-[12px] font-bold text-[#6b7a6e] hover:border-[#0e6a38] hover:text-[#0e6a38] disabled:opacity-40 disabled:cursor-not-allowed">{{ __('ui.adopt_reco') }}</button>
                    </form>
                    @endif
                </div>
                @endif
            </div>
        </section>
        @endif

        @if($isWriter && $report->revisions->count())
        <details class="rounded-xl border border-[#e6e9e1] dark:border-[#333b34] bg-[#f9faf8] dark:bg-[#232926] px-3.5 py-3">
            <summary class="text-[12px] font-bold text-[#6b7a6e] dark:text-[#9bb0a0] cursor-pointer list-none [&::-webkit-details-marker]:hidden select-none flex items-center justify-between">
                <span>{{ __('ui.revisions_log', ['n' => $report->revisions->count()]) }}</span>
                <svg class="w-3.5 h-3.5 text-[#9aa99a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="mt-3 grid gap-1.5 border-t border-[#eceee9] dark:border-[#333b34] pt-3">
            @foreach($report->revisions as $rev)
                <div class="flex gap-2 text-[12px] leading-5">
                    <span class="tabular-nums text-[#9aa99a] shrink-0">{{ $rev->created_at?->format('Y-m-d H:i') }}</span>
                    <span class="w-px bg-[#e6e9e1] dark:bg-[#333b34] shrink-0"></span>
                    <span class="font-bold text-[#1a2e1f] dark:text-[#e7ece5] truncate">{{ $rev->editor->localized_name ?? '—' }}</span>
                    <span class="text-[#6b7a6e] dark:text-[#9bb0a0] truncate flex-1">{{ \Illuminate\Support\Str::limit($rev->content_snapshot, 110) }}</span>
                </div>
            @endforeach
            </div>
        </details>
        @endif
    </div>
</div>

<script>
const RPT_T = {
    selected: @json(__('ui.rpt_selected')),
    reorderFailed: @json(__('ui.rpt_reorder_failed')),
    reorderError: @json(__('ui.rpt_reorder_error')),
    stateSystem: @json(__('ui.rpt_state_system')),
    stateCustom: @json(__('ui.rpt_state_custom')),
    stateStale: @json(__('ui.rpt_state_stale')),
    stateNone: @json(__('ui.rpt_state_none')),
    obsN: @json(__('ui.observation_n')),
    noObs: @json(__('ui.rpt_no_obs')),
    approving: @json(__('ui.rpt_approving')),
    approved: @json(__('ui.rpt_approved')),
    approveFailed: @json(__('ui.rpt_approve_failed')),
    timeoutRetry: @json(__('ui.rpt_timeout_retry')),
    offlineApprove: @json(__('ui.rpt_offline_approve')),
    approve: @json(__('ui.approve')),
    systemRestored: @json(__('ui.rpt_system_restored')),
    generating: @json(__('ui.rpt_generating')),
    smartGenerating: @json(__('ui.rpt_smart_generating')),
    generated: @json(__('ui.rpt_generated')),
    smartFailed: @json(__('ui.rpt_smart_failed')),
    offlineGenerate: @json(__('ui.rpt_offline_generate')),
    smartGen: @json(__('ui.smart_gen')),
    draftDone: @json(__('ui.rpt_draft_done')),
    generateFailed: @json(__('ui.rpt_generate_failed')),
    genDraft: @json(__('ui.gen_draft')),
    aiConfigured: @json(!($aiMissingKey ?? false)),
    aiNotConfigured: @json(__('api.ai_not_configured')),
};

(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    // يدوي كخيار إضافي: عدّاد المحدد وتفعيل زر الإضافة
    const attachForm = document.getElementById('attach-form');
    const attachSubmit = document.getElementById('attach-submit');
    function refreshAttachCount() {
        if (!attachForm) return;
        const n = attachForm.querySelectorAll('.attach-check:checked').length;
        const total = attachForm.querySelectorAll('.attach-check').length;
        const el = document.getElementById('attach-count');
        if (el) el.textContent = total ? RPT_T.selected.replace(':n', n).replace(':total', total) : '';
        if (attachSubmit) attachSubmit.disabled = total === 0 || n === 0;
    }
    attachForm?.addEventListener('submit', function (e) {
        const n = attachForm.querySelectorAll('.attach-check:checked').length;
        refreshAttachCount();
        if (!n) { e.preventDefault(); document.getElementById('attach-error')?.classList.remove('hidden'); }
    });
    attachForm?.querySelectorAll('.attach-check').forEach(c => c.addEventListener('change', function () {
        refreshAttachCount();
        if (attachForm.querySelectorAll('.attach-check:checked').length) document.getElementById('attach-error')?.classList.add('hidden');
    }));
    refreshAttachCount();

    const list = document.getElementById('notes-order-list');
    const reorderUrl = @json(route('reports.reorder', $report));
    const reorderErr = document.getElementById('reorder-error');
    let reorderBusy = false;
    function currentOrder() {
        if(!list) return [];
        return Array.from(list.querySelectorAll('li[data-note-id]')).map(li => parseInt(li.dataset.noteId, 10));
    }
    function refreshReorderButtons() {
        if (!list) return;
        const items = Array.from(list.querySelectorAll('li[data-note-id]'));
        items.forEach((li, idx) => {
            const up = li.querySelector('.reorder-btn[data-dir="-1"]');
            const down = li.querySelector('.reorder-btn[data-dir="1"]');
            if (up) up.classList.toggle('hidden', reorderBusy || idx === 0);
            if (down) down.classList.toggle('hidden', reorderBusy || idx === items.length - 1);
        });
    }
    async function sendOrder(order) {
        if (reorderBusy) return;
        reorderBusy = true;
        reorderErr?.classList.add('hidden');
        refreshReorderButtons();
        try {
            const res = await fetch(reorderUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ ordered_ids: order }),
            });
            const d = await res.json().catch(() => ({}));
            if (res.ok && d.success) { window.location.reload(); return; }
            throw new Error(d.message || RPT_T.reorderFailed);
        } catch (e) {
            if (reorderErr) { reorderErr.textContent = (e && e.message) || RPT_T.reorderError; reorderErr.classList.remove('hidden'); reorderErr.classList.add('rpt-anim-in'); }
            reorderBusy = false;
            refreshReorderButtons();
        }
    }
    list?.addEventListener('click', function (e) {
        const btn = e.target.closest('.reorder-btn');
        if (!btn || btn.classList.contains('hidden')) return;
        const li = btn.closest('li[data-note-id]');
        if (!li) return;
        const dir = parseInt(btn.dataset.dir, 10);
        const sib = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
        if (!sib) return;
        if (dir < 0) list.insertBefore(li, sib);
        else list.insertBefore(sib, li);
        list.querySelectorAll('.order-num').forEach((s, i) => s.textContent = (i + 1));
        refreshReorderButtons();
        sendOrder(currentOrder());
    });
    refreshReorderButtons();
    document.querySelectorAll('[data-menu-btn]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.stopPropagation();
            const m = b.closest('[data-menu]')?.querySelector('[data-menu-list]');
            if (!m) return;
            const willOpen = m.classList.contains('hidden');
            document.querySelectorAll('[data-menu-list]').forEach(x => x.classList.add('hidden'));
            document.querySelectorAll('[data-menu-btn]').forEach(x => x.setAttribute('aria-expanded','false'));
            m.classList.toggle('hidden', !willOpen);
            b.setAttribute('aria-expanded', String(willOpen));
        });
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('[data-menu]')) { document.querySelectorAll('[data-menu-list]').forEach(m => m.classList.add('hidden')); document.querySelectorAll('[data-menu-btn]').forEach(x => x.setAttribute('aria-expanded','false')); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { document.querySelectorAll('[data-menu-list]').forEach(m => m.classList.add('hidden')); } });
    (function(){
        const btn = document.getElementById('preview-expand-btn');
        const modal = document.getElementById('preview-modal');
        const body = document.getElementById('preview-modal-body');
        const wrap = document.getElementById('paper-preview-wrap');
        if(!btn || !modal || !wrap) return;
        function open(){
            const clone = wrap.cloneNode(true);
            clone.removeAttribute('id');
            clone.classList.remove('rpt-preview-viewport');
            clone.style.maxHeight='none';
            clone.style.overflow='visible';
            clone.style.background='transparent';
            clone.style.padding='0';
            body.innerHTML='';
            body.appendChild(clone);
            modal.classList.remove('hidden');
            document.body.style.overflow='hidden';
            if(window.fitPaperPreview) setTimeout(()=>window.fitPaperPreview(),120);
        }
        function close(){
            modal.classList.add('hidden');
            document.body.style.overflow='';
            body.innerHTML='';
        }
        btn.addEventListener('click', open);
        document.getElementById('preview-modal-close')?.addEventListener('click', close);
        document.getElementById('preview-modal-backdrop')?.addEventListener('click', close);
        document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !modal.classList.contains('hidden')) close(); });
    })();
})();

(function () {
    const approveBtn = document.getElementById('final-approve-btn');
    if (!approveBtn) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const sysData = @json($systemData ?? ['location' => '', 'observations' => [], 'recommendations' => '']);
    const renderUrl = @json(route('reports.render', $report));
    const genDataUrl = @json(route('reports.generate-data', $report));
    const reportId = @json($report->id);
    const obsList = document.getElementById('final-obs-list');
    const recoEl = document.getElementById('final-reco');
    const statusEl = document.getElementById('final-status');
    const errEl = document.getElementById('final-error');
    const pvState = document.getElementById('pv-state');
    const wrap = document.getElementById('paper-preview-wrap');
    const aiBtn = document.getElementById('final-ai-btn');
    const aiSpin = document.getElementById('final-ai-spin');
    const aiLabel = document.getElementById('final-ai-label');
    const verKey = 'report-pv-ver-' + reportId;
    let busy = false;
    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }
    function setStatus(msg) {
        if (!statusEl) return;
        if (!msg) { statusEl.classList.add('hidden'); statusEl.textContent = ''; }
        else { statusEl.classList.remove('hidden'); statusEl.textContent = msg; }
    }
    function showError(msg) {
        if (!errEl) return;
        errEl.textContent = msg;
        errEl.classList.remove('hidden');
    }
    function clearError() { errEl?.classList.add('hidden'); }
    function paintState(state) {
        if (!pvState) return;
        const map = {
            system: ['bg-[#e8f3ec] text-[#0e6a38] border border-[#cde7d6]', RPT_T.stateSystem],
            custom: ['bg-[#e8f3ec] text-[#0e6a38] border border-[#cde7d6]', RPT_T.stateCustom],
            stale: ['bg-[#fef3c7] text-[#92400e] border border-[#fde68a]', RPT_T.stateStale],
        };
        const entry = map[state];
        if(!entry) return;
        const [cls, txt] = entry;
        pvState.className = 'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold ' + cls;
        pvState.textContent = txt;
        pvState.classList.remove('hidden');
    }
    function showHtml(html) {
        let holder = document.getElementById('paper-preview-html');
        if (holder) { holder.innerHTML = html; holder.classList.remove('rpt-anim-fade'); void holder.offsetWidth; holder.classList.add('rpt-anim-fade'); }
        else if (wrap) {
            wrap.innerHTML = '';
            holder = document.createElement('div');
            holder.id = 'paper-preview-html';
            holder.className = 'report-preview rpt-anim-fade';
            holder.setAttribute('data-paper-fit-inner', '');
            holder.innerHTML = html;
            wrap.appendChild(holder);
        }
        if (window.fitPaperPreview) window.fitPaperPreview();
    }
    function readEditor() {
        const observations = Array.from(document.querySelectorAll('.final-obs'))
            .map(t => (t.value || '').trim()).filter(t => t !== '');
        return { location: sysData.location || '', observations, recommendations: (recoEl?.value || '').trim() };
    }
    function fillEditor(observations, recommendations) {
        if (obsList && Array.isArray(observations)) {
            obsList.innerHTML = '';
            observations.forEach((text, i) => {
                const label = document.createElement('label');
                label.className = 'rpt-field rpt-anim-in';
                const span = document.createElement('span');
                span.className = 'rpt-label';
                span.textContent = RPT_T.obsN.replace(':n', (i + 1));
                const ta = document.createElement('textarea');
                ta.rows = 3; ta.maxLength = 2000;
                ta.dataset.obsIdx = i;
                ta.className = 'final-obs rpt-input min-h-[78px] resize-y';
                ta.value = text || '';
                label.appendChild(span);
                label.appendChild(ta);
                obsList.appendChild(label);
            });
        }
        if (recoEl && recommendations !== undefined) recoEl.value = recommendations || '';
    }
    function markPublishedReady() {
        const pubForm = document.getElementById('publish-form');
        if (pubForm) pubForm.classList.remove('hidden');
        const pub = document.getElementById('publish-btn');
        if (pub) { pub.disabled = false; }
        const hint = document.getElementById('publish-hint');
        if (hint) hint.classList.add('hidden');
        document.querySelector('.rpt-publish')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    approveBtn.addEventListener('click', async function () {
        if (busy) return;
        const data = readEditor();
        if (!data.observations.length) { showError(RPT_T.noObs); return; }
        busy = true;
        approveBtn.disabled = true;
        approveBtn.textContent = RPT_T.approving;
        setStatus(RPT_T.approving);
        clearError();
        let v = parseInt(localStorage.getItem(verKey) || '1', 10) || 1;
        localStorage.setItem(verKey, String(v + 1));
        const controller = new AbortController();
        const killer = setTimeout(() => controller.abort(), 120000);
        try {
            const res = await fetch(renderUrl, {
                method: 'POST', signal: controller.signal,
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ data_version: v, request_uid: uuid(), data }),
            });
            const d = await res.json().catch(() => ({}));
            if (res.ok && d.success) {
                showHtml(d.data.html || '');
                paintState('custom');
                setStatus(RPT_T.approved);
                markPublishedReady();
            } else {
                showError((d && d.message) || RPT_T.approveFailed);
                setStatus('');
            }
        } catch (e) {
            showError(e && e.name === 'AbortError' ? RPT_T.timeoutRetry : RPT_T.offlineApprove);
            setStatus('');
        } finally {
            clearTimeout(killer);
            busy = false;
            approveBtn.disabled = false;
            approveBtn.textContent = RPT_T.approve;
        }
    });
    document.getElementById('final-fill-system')?.addEventListener('click', function () {
        fillEditor(sysData.observations || [], sysData.recommendations || '');
        setStatus(RPT_T.systemRestored);
    });
    aiBtn?.addEventListener('click', async function () {
        // فالديشن واجهة مبكر: بدون مفتاح API لا نرسل الطلب أصلاً.
        if (RPT_T.aiConfigured === false) { showError(RPT_T.aiNotConfigured); setStatus(''); return; }
        aiBtn.disabled = true;
        aiSpin?.classList.remove('hidden');
        if (aiLabel) aiLabel.textContent = RPT_T.generating;
        setStatus(RPT_T.smartGenerating);
        clearError();
        const controller = new AbortController();
        const killer = setTimeout(() => controller.abort(), 170000);
        try {
            const res = await fetch(genDataUrl, {
                method: 'POST', signal: controller.signal,
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
            const d = await res.json().catch(() => ({}));
            if (res.ok && d.success) {
                fillEditor(d.data.observations || [], d.data.recommendations || '');
                setStatus(RPT_T.generated);
            } else {
                showError((d && d.message) || RPT_T.smartFailed);
                setStatus('');
            }
        } catch (e) {
            showError(e && e.name === 'AbortError' ? RPT_T.timeoutRetry : RPT_T.offlineGenerate);
            setStatus('');
        } finally {
            clearTimeout(killer);
            aiBtn.disabled = false;
            aiSpin?.classList.add('hidden');
            if (aiLabel) aiLabel.textContent = RPT_T.smartGen;
        }
    });
})();
</script>

@if(!empty($needsAutoRender ?? false))
<script>
(function () {
    // Fast-open companion: first view of a draft had no render row, so the server
    // returned instantly with a placeholder. Build the preview async now.
    async function autoRender() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const renderUrl = @json(route('reports.render', $report));
        const sysData = @json($systemData ?? ['location' => '', 'observations' => [], 'recommendations' => '']);
        const obs = Array.isArray(sysData.observations) ? sysData.observations.filter(t => (t || '').trim() !== '') : [];
        if (!obs.length) return;
        const wrap = document.getElementById('paper-preview-wrap');
        const badge = document.getElementById('report-l10n-badge');
        if (badge) {
            badge.classList.remove('hidden');
            badge.textContent = document.documentElement.getAttribute('lang') === 'en' ? 'Preparing preview…' : 'جاري تجهيز المعاينة…';
        }
        try {
            const v = parseInt(localStorage.getItem('report-pv-ver-' + @json($report->id)) || '1', 10) || 1;
            const res = await fetch(renderUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ data_version: v, request_uid: 'auto-' + Date.now(), data: { location: sysData.location || '', observations: obs, recommendations: sysData.recommendations || '' } }),
            });
            const d = await res.json().catch(() => ({}));
            if (res.ok && d.success && d.data && d.data.html) {
                let holder = document.getElementById('paper-preview-html');
                if (holder) { holder.innerHTML = d.data.html; }
                else if (wrap) {
                    wrap.innerHTML = '';
                    holder = document.createElement('div');
                    holder.id = 'paper-preview-html';
                    holder.className = 'report-preview';
                    holder.setAttribute('data-paper-fit-inner', '');
                    holder.innerHTML = d.data.html;
                    wrap.appendChild(holder);
                }
                if (window.fitPaperPreview) window.fitPaperPreview();
                const pv = document.getElementById('pv-state');
                if (pv) { pv.className = 'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#e8f3ec] text-[#0e6a38] border border-[#cde7d6]'; pv.textContent = RPT_T.stateCustom; pv.classList.remove('hidden'); }
                if (badge) badge.classList.add('hidden');
                // المعاينة اعتُمدت تلقائياً → أظهر زر النشر فوراً دون انتظار تحديث الصفحة
                const pubFormAuto = document.getElementById('publish-form');
                if (pubFormAuto) pubFormAuto.classList.remove('hidden');
                const pubBtnAuto = document.getElementById('publish-btn');
                if (pubBtnAuto) pubBtnAuto.disabled = false;
                const pubHintAuto = document.getElementById('publish-hint');
                if (pubHintAuto) pubHintAuto.classList.add('hidden');
            } else if (badge) {
                badge.textContent = document.documentElement.getAttribute('lang') === 'en' ? 'Preview will be ready after approval' : 'ستجهز المعاينة بعد الاعتماد';
            }
        } catch (e) {
            if (badge) badge.textContent = document.documentElement.getAttribute('lang') === 'en' ? 'Preview will be ready after approval' : 'ستجهز المعاينة بعد الاعتماد';
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => setTimeout(autoRender, 350));
    else setTimeout(autoRender, 350);
})();
</script>
@endif

<script>
(function () {
    const form = document.getElementById('ai-form');
    if (!form) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const btn = document.getElementById('ai-generate-btn');
    const label = document.getElementById('ai-btn-label');
    const spin = document.getElementById('ai-spin');
    const errBox = document.getElementById('ai-error');
    const errMsg = document.getElementById('ai-error-msg');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errBox.classList.add('hidden');
        // فالديشن واجهة مبكر: بدون مفتاح API نعرض رسالة التحقق فوراً.
        if (RPT_T.aiConfigured === false) {
            errMsg.textContent = RPT_T.aiNotConfigured;
            errBox.classList.remove('hidden');
            return;
        }
        btn.disabled = true;
        spin.classList.remove('hidden');
        label.textContent = RPT_T.generating;
        const controller = new AbortController();
        const killer = setTimeout(() => controller.abort(), 120000);
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                signal: controller.signal,
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ include_images: true }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                document.getElementById('ai-draft-box').innerText = data.data.ai_draft_content || '';
                document.getElementById('ai-summary-src').innerText = data.data.ai_summary || '';
                document.getElementById('ai-reco-src').innerText = data.data.ai_recommendations || '';
                document.getElementById('ai-adopt-summary').disabled = !data.data.ai_summary;
                document.getElementById('ai-adopt-reco').disabled = !data.data.ai_recommendations;
                label.textContent = RPT_T.draftDone;
            } else {
                errMsg.textContent = data.message || RPT_T.generateFailed;
                errBox.classList.remove('hidden');
                label.textContent = RPT_T.genDraft;
            }
        } catch (err) {
            errMsg.textContent = err && err.name === 'AbortError' ? RPT_T.timeoutRetry : RPT_T.offlineGenerate;
            errBox.classList.remove('hidden');
            label.textContent = RPT_T.genDraft;
        } finally {
            clearTimeout(killer);
            btn.disabled = false;
            spin.classList.add('hidden');
        }
    });
    function adopt(btnId, srcId, targetId) {
        document.getElementById(btnId)?.addEventListener('click', function () {
            const text = document.getElementById(srcId)?.innerText.trim() || '';
            const target = document.getElementById(targetId);
            if (!text || !target) return;
            target.value = text;
            target.focus();
        });
    }
    adopt('ai-adopt-summary', 'ai-summary-src', 'field-summary');
    adopt('ai-adopt-reco', 'ai-reco-src', 'field-reco');
})();

(function () {
    const mini = document.getElementById('sheet-mini');
    if (!mini) return;
    function fit() {
        const H = mini.clientHeight;
        if (!H) return;
        mini.querySelectorAll('.sh-field').forEach(function (f) {
            let fs = H * 0.015;
            f.style.fontSize = fs + 'px';
            f.style.lineHeight = (H * 0.022) + 'px';
            let g = 60;
            while ((f.scrollWidth > f.clientWidth + 1 || f.scrollHeight > f.clientHeight + 1) && fs > H * 0.008 && g-- > 0) {
                fs -= 0.5;
                f.style.fontSize = fs + 'px';
            }
        });
        mini.querySelectorAll('.sh-fit').forEach(function (box) {
            let pitch = H * parseFloat(box.dataset.pitch || '3') / 100;
            let fs = H * 0.0158;
            box.style.lineHeight = pitch + 'px';
            box.style.fontSize = fs + 'px';
            let g = 60;
            while (box.scrollHeight > box.clientHeight + 1 && fs > H * 0.0083 && g-- > 0) {
                fs -= 0.5;
                box.style.fontSize = fs + 'px';
            }
            g = 60;
            let lh = pitch;
            while (box.scrollHeight > box.clientHeight + 1 && lh > pitch * 0.6 && g-- > 0) {
                lh -= 0.5;
                box.style.lineHeight = lh + 'px';
            }
        });
    }
    window.addEventListener('load', fit);
    window.addEventListener('resize', fit);
    fit();
})();
</script>

{{-- جهات الاتصال الفورية --}}
@php
    try {
        $footerContacts = \Illuminate\Support\Facades\Cache::remember('report_footer_contacts_'.auth()->id(), 600, fn () => \App\Models\User::select(['id','name','name_en','name_ar','role','avatar_path','personal_number'])->where('role','report_writer')->whereNotNull('personal_number')->where('personal_number','!=','')->where('id','!=',auth()->id())->orderBy('name')->limit(6)->get());
        if (! $footerContacts instanceof \Illuminate\Support\Collection) {
            $footerContacts = collect();
        }
    } catch (\Throwable $e) {
        $footerContacts = collect();
    }
@endphp
@if($footerContacts->isNotEmpty())
<section class="w-full max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 mt-2 sm:mt-4" aria-label="{{ __('ui.instant_contacts') }}">
    <div class="bg-white dark:bg-[#252b26] rounded-2xl border border-[#eceee9] dark:border-[#2e352e] shadow-[0_2px_16px_rgba(0,0,0,0.04)] overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-[#f1f3f0] dark:border-[#2a352f] flex items-center justify-between gap-3">
            <h2 class="text-[14px] sm:text-[15px] font-semibold tracking-tight text-ink-800 dark:text-[#e7ece5] flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-[#eef4f0] dark:bg-[#1e3328] border border-[#eceee9] dark:border-[#2e352e] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#0e6a38] dark:text-[#5cb87a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </span>
                {{ __('ui.instant_contacts') }}
            </h2>
            <span class="text-xs font-medium text-ink-400 dark:text-[#8a9a8e] hidden sm:block">{{ __('ui.instant_contacts_hint') }}</span>
        </div>
        <div class="p-4 sm:p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-3.5">
                @foreach($footerContacts as $c)
                <div class="group relative bg-[#fdfcfa] dark:bg-[#1e2320] rounded-xl border border-[#eceee9] dark:border-[#2e352e] p-3.5 flex items-center gap-3 hover:border-[#e3e8e1] dark:hover:border-[#33423a] hover:shadow-sm transition-all overflow-hidden">
                    <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[44px] h-[3px] pointer-events-none">
                        <div class="w-full h-full bg-[#0e6a38] dark:bg-[#1a8a50] rounded-t-[3px]" style="clip-path: polygon(7% 100%, 93% 100%, 88% 0, 12% 0)"></div>
                    </div>
                    <a href="{{ route('profile.showUser', $c->id) }}" class="shrink-0">
                        @if($c->avatar_url)
                            <img src="{{ $c->avatar_url }}" alt="{{ $c->localized_name }}" class="w-10 h-10 rounded-xl object-cover border border-[#eceee9] dark:border-[#2e352e] shadow-sm">
                        @else
                            <span class="w-10 h-10 rounded-xl bg-[#eef4f0] dark:bg-[#1e3328] border border-[#eceee9] dark:border-[#2e352e] text-[#0e6a38] dark:text-[#5cb87a] flex items-center justify-center text-[13px] font-semibold shadow-sm">{{ $c->initial }}</span>
                        @endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-semibold tracking-tight text-ink-800 dark:text-[#e7ece5] truncate">{{ $c->localized_name }}</div>
                        <div class="text-xs text-ink-500 dark:text-[#8a9a8e] truncate">{{ $c->isReportWriter() ? __('ui.role_writer') : __('ui.role_monitor') }}</div>
                        <div class="mt-0.5 text-[11px] font-mono tabular-nums tracking-widest text-ink-600 dark:text-[#9bb0a0]" dir="ltr">{{ $c->personal_number }}</div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <a href="tel:{{ $c->personal_number }}" class="w-9 h-9 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-[#252b26] border border-[#eceee9] dark:border-[#2e352e] text-ink-600 dark:text-[#9bb0a0] hover:text-[#0e6a38] hover:border-[#e0e7df] flex items-center justify-center transition touch-manipulation" title="{{ __('ui.call') }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </a>
                        @if($c->whatsapp_url)
                        <a href="{{ $c->whatsapp_url }}" target="_blank" rel="noopener" class="w-9 h-9 sm:w-8 sm:h-8 rounded-full bg-[#25D366] hover:bg-[#1da851] active:bg-[#168a3a] text-white flex items-center justify-center shadow-sm transition touch-manipulation" title="{{ __('ui.share_whatsapp') }}">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
@endsection
