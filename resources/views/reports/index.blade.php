@extends('layouts.app')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-[#0e6a38] text-white flex items-center justify-center shadow-sm shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
        </div>
        <div>
            <h1 class="report-page-title text-xl font-extrabold text-[#1a2e1f] leading-tight">{{ __('ui.daily_reports') }}</h1>
            <p class="report-sub text-xs text-[#6b7a6e] mt-0.5">{{ $reports->total() === 1 ? __('ui.report_one') : ($reports->total() === 2 ? __('ui.report_two') : __('ui.reports_many', ['n' => $reports->total()])) }}</p>
        </div>
    </div>
    @if(auth()->user()->isReportWriter())
    <a href="{{ route('reports.create') }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        {{ __('ui.new_report') }}
    </a>
    @endif
</div>

@php
    $isWriterView = auth()->user()->isReportWriter();
    $activeFilters = collect(['report_date' => request('report_date'), 'status' => $isWriterView ? request('status') : null])->filter(fn($v) => $v !== null && $v !== '')->count();
@endphp
<div class="mb-5">
    <button type="button" id="filter-toggle" class="report-filter-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#fdfcfa] border border-[#e6e9e1] text-sm font-bold text-[#4a5a4f] shadow-sm hover:border-[#0e6a38] hover:text-[#0e6a38] transition" aria-expanded="false" aria-controls="filter-panel">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
        @if($activeFilters)<span class="min-w-[20px] h-5 px-1 rounded-full bg-[#0e6a38] text-white text-[11px] font-bold inline-flex items-center justify-center">{{ $activeFilters }}</span>@endif
        <svg id="filter-chevron" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <form method="GET" id="filter-panel" class="hidden mt-2 bg-[#fdfcfa] border border-[#e6e9e1] rounded-2xl p-3.5 flex flex-wrap items-end gap-2.5 shadow-sm">
        <label class="report-filter-label grid gap-1 text-xs font-medium text-[#6b7a6e]">{{ __('ui.report_date_filter') }}<input type="date" name="report_date" value="{{ request('report_date') }}" class="border border-[#e6e9e1] rounded-xl px-3 py-2 text-sm text-[#1a2e1f] focus:outline-none focus:border-[#0e6a38]"></label>
        @if($isWriterView)
        <label class="report-filter-label grid gap-1 text-xs font-medium text-[#6b7a6e]">{{ __('ui.status') }}
            <select name="status" class="border border-[#e6e9e1] rounded-xl px-3 py-2 text-sm text-[#1a2e1f] focus:outline-none focus:border-[#0e6a38]">
                <option value="">{{ __('ui.all') }}</option>
                <option value="draft" @selected(request('status')==='draft')>{{ __('ui.draft') }}</option>
                <option value="published" @selected(request('status')==='published')>{{ __('ui.published') }}</option>
            </select>
        </label>
        @endif
        @if($activeFilters)<a href="{{ route('reports.index') }}" class="text-xs text-[#6b7a6e] hover:text-red-600 pb-2">{{ __('ui.clear_filter') }}</a>@endif
    </form>
</div>
<script>
(function () {
    const toggle = document.getElementById('filter-toggle');
    const panel = document.getElementById('filter-panel');
    const chevron = document.getElementById('filter-chevron');
    toggle?.addEventListener('click', function () {
        const open = panel.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        chevron.style.transform = open ? 'rotate(180deg)' : '';
    });
    panel?.querySelectorAll('input, select').forEach(el => el.addEventListener('change', () => panel.submit()));
})();
</script>

@if($reports->isEmpty())
<div class="report-empty bg-[#fdfcfa] border border-dashed border-[#e6e9e1] rounded-[18px] p-10 text-center shadow-sm">
    <div class="report-empty-ic w-14 h-14 mx-auto rounded-2xl bg-[#f1f3f0] text-[#9aa99a] flex items-center justify-center">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
    </div>
    <p class="report-empty-title font-extrabold text-[#4a5a4f] mt-3">{{ __('ui.no_reports_short') }}</p>
</div>
@else
<div class="grid md:grid-cols-2 gap-4">
@foreach($reports as $r)
@php
    $isPub = $r->status === 'published';
    $canExportMenu = $isPub && auth()->user()->isReportWriter();
    $waText = l10n_text('report', $r->id, 'title', $r->title).' — '.$r->report_date->toDateString();
    $notesN = $r->notes_count ?? $r->notes->count();
    $sheetMax = (int) config('report_sheets.max_notes', 7);
    $sheetN = ($notesN >= 1 && $notesN <= $sheetMax) ? (int) $notesN : null;
@endphp
    <article class="report-card group relative bg-[#fdfcfa] border border-[#e6e9e1] rounded-[18px] shadow-sm hover:shadow-md hover:border-[#0e6a38]/40 hover:-translate-y-0.5 transition" data-i18n-entity="report" data-i18n-id="{{ $r->id }}">
        <div class="report-sheet-banner relative h-32 sm:h-36 overflow-hidden rounded-t-[17px] border-b border-[#e6e9e1] bg-[#f1f3f0]">
            @if($sheetN)
                <img src="{{ route('report-sheets.image', $sheetN) }}" alt="{{ __('ui.sheet_alt', ['n' => $sheetN]) }}" loading="lazy" class="h-full w-full object-cover object-top">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
                <span class="absolute bottom-2.5 right-3 inline-flex items-center justify-center min-w-[36px] h-9 px-2 rounded-full bg-[#0e6a38] text-white text-base font-extrabold tabular-nums shadow-lg ring-2 ring-white/90" title="{{ __('ui.sheet_no', ['n' => $sheetN]) }}">{{ $sheetN }}</span>
            @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-l from-[#0e6a38] to-[#083a20]">
                    <svg class="w-10 h-10 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
                </div>
            @endif
            @if($isWriterView)
            <span class="report-pill absolute top-2.5 left-3 inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full bg-white/95 text-[#4a5a4f] border border-[#e6e9e1] shadow-sm"><span class="w-1.5 h-1.5 rounded-full {{ $isPub ? 'bg-[#0e6a38]' : 'bg-amber-400' }}"></span><span data-status="{{ $r->status }}">{{ $isPub ? __('ui.published') : __('ui.draft') }}</span></span>
            @endif
        </div>
        <div class="flex gap-3 p-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('reports.show', $r) }}" class="report-title font-extrabold text-[15px] text-[#1a2e1f] truncate group-hover:text-[#0e6a38] transition">{{ l10n_text('report', $r->id, 'title', $r->title) }}</a>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($isPub)
                        <details class="relative">
                            <summary class="report-menu-btn w-10 h-10 sm:w-8 sm:h-8 inline-flex items-center justify-center rounded-lg text-[#4a5a4f] hover:bg-[#f6f7f5] hover:text-[#0e6a38] cursor-pointer list-none select-none text-lg sm:text-base" aria-label="{{ __('ui.more_options') }}">⋮</summary>
                            <div class="report-menu absolute left-0 top-11 sm:top-9 z-30 min-w-[190px] max-w-[calc(100vw-2rem)] bg-[#fdfcfa] border border-[#e6e9e1] rounded-xl shadow-xl py-1.5 text-sm">
                                <a href="{{ route('reports.preview', $r) }}" class="flex items-center px-4 py-2.5 min-h-[44px] text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.view_report') }}</a>
                                @if($canExportMenu)
                                <a href="{{ route('reports.export.pdf', $r) }}" class="flex items-center px-4 py-2.5 min-h-[44px] text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.export_pdf') }}</a>
                                <a href="{{ route('reports.export.image', $r) }}" class="flex items-center px-4 py-2.5 min-h-[44px] text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.export_image') }}</a>
                                <button type="button" data-wa="{{ e($waText) }}" onclick="window.open('https://wa.me/?text='+encodeURIComponent(this.getAttribute('data-wa')),'_blank','noopener')" class="w-full text-right flex items-center px-4 py-2.5 min-h-[44px] text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.share_via_whatsapp') }}</button>
                                @endif
                            </div>
                        </details>
                        @else
                        
                        <span class="w-10 h-10 sm:w-8 sm:h-8 shrink-0" aria-hidden="true"></span>
                        @endif
                    </div>
                </div>
                <div class="report-meta flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-[#6b7a6e]">
                    <span class="inline-flex items-center gap-1 tabular-nums">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $r->report_date->toDateString() }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
                        {{ $notesN }} {{ $notesN === 1 ? __('ui.note_one') : __('ui.notes_many') }}
                    </span>
                    <span class="inline-flex items-center gap-1 min-w-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="truncate">{{ $r->author->name ?? '' }}</span>
                    </span>
                </div>
                <a href="{{ route('reports.show', $r) }}" class="inline-flex items-center gap-1 mt-2.5 text-[13px] font-bold text-[#0e6a38] hover:gap-2 transition-all">
                    {{ __('ui.open_report') }}
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5 5-5M18 12H6"/></svg>
                </a>
            </div>
        </div>
    </article>
@endforeach
</div>
<div class="mt-5">{{ $reports->links() }}</div>
@endif
<style>
details summary::-webkit-details-marker { display: none; }
/* دارك مود — صفحة التقارير: تباين هادئ بلا صناديق ساطعة */
html.dark .report-card { background-color: #232926 !important; border-color: #333b34 !important; box-shadow: 0 1px 2px rgba(0,0,0,.35) !important; }
html.dark .report-card:hover { border-color: rgba(74,222,128,.35) !important; box-shadow: 0 6px 18px rgba(0,0,0,.4) !important; }
html.dark .report-title { color: #e7ece5 !important; }
html.dark .group:hover .report-title { color: #4ade80 !important; }
html.dark .report-page-title { color: #e7ece5 !important; }
html.dark .report-meta, html.dark .report-sub, html.dark .report-filter-label { color: #9bb0a0 !important; }
html.dark .report-filter-btn { background-color: #232926 !important; border-color: #333b34 !important; color: #e7ece5 !important; }
html.dark .report-filter-btn:hover { border-color: rgba(74,222,128,.4) !important; color: #4ade80 !important; }
html.dark .report-date, html.dark .report-pill { background-color: #2a302b !important; color: #e7ece5 !important; border-color: #343a34 !important; }
html.dark .report-sheet-banner { border-color: #333b34 !important; }
html.dark .report-menu-btn { color: #9bb0a0 !important; }
html.dark .report-menu-btn:hover { background-color: #2e352e !important; color: #4ade80 !important; }
html.dark .report-menu { background-color: #232926 !important; border-color: #333b34 !important; }
html.dark .report-menu a, html.dark .report-menu button { color: #e7ece5 !important; }
html.dark .report-menu a:hover, html.dark .report-menu button:hover { background-color: #2e352e !important; }
html.dark .report-empty { background-color: #232926 !important; border-color: #333b34 !important; }
html.dark .report-empty-ic { background-color: #1e2320 !important; color: #8a9a8a !important; }
html.dark .report-empty-title { color: #e7ece5 !important; }
html.dark .report-empty-sub { color: #9bb0a0 !important; }
</style>
<script>
(function () {
    // قائمة ⋮ واحدة مفتوحة فقط + رفع البطاقة المفتوحة فوق البطاقات التالية (z-index)
    // حتى لا تُغطى القائمة على الجوال.
    function closeAll(except) {
        document.querySelectorAll('.report-card details[open]').forEach(function (d) {
            if (d !== except) d.removeAttribute('open');
        });
    }
    document.querySelectorAll('.report-card details').forEach(function (d) {
        d.addEventListener('toggle', function () {
            const card = d.closest('.report-card');
            if (d.open) {
                closeAll(d);
                card?.classList.add('z-20');
            } else {
                card?.classList.remove('z-20');
            }
        });
    });
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.report-card details[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll(null);
    });
})();
</script>
@endsection

