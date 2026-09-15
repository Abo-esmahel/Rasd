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
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('reports.insights') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#fdfcfa] border border-[#e6e9e1] hover:border-[#0e6a38] text-[#0e6a38] font-bold text-sm shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l4-6 4 3 4-7"/></svg>
            {{ __('ui.view_insights') }}
        </a>
        <a href="{{ route('reports.create') }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            {{ __('ui.new_report') }}
        </a>
    </div>
    @endif
</div>

@php
    $isWriterView = auth()->user()->isReportWriter();
    $activeFilters = collect(['report_date' => request('report_date'), 'status' => $isWriterView ? request('status') : null])->filter(fn($v) => $v !== null && $v !== '')->count();
@endphp
<div class="mb-5">
    <button type="button" id="filter-toggle" class="report-filter-btn inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full sm:rounded-xl bg-[#fdfcfa] border border-[#e6e9e1] text-xs sm:text-sm font-bold text-[#4a5a4f] shadow-sm hover:border-[#0e6a38] hover:text-[#0e6a38] transition" aria-expanded="false" aria-controls="filter-panel">
        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
        @if($activeFilters)<span class="min-w-[18px] sm:min-w-[20px] h-[18px] sm:h-5 px-1 rounded-full bg-[#0e6a38] text-white text-[10px] sm:text-[11px] font-bold inline-flex items-center justify-center">{{ $activeFilters }}</span>@endif
        <svg id="filter-chevron" class="w-3 h-3 sm:w-3.5 sm:h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
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
    $waText = l10n_text('report', $r->id, 'title', $r->title).' — '.$r->report_date->toDateString();
    $notesN = $r->notes_count ?? $r->notes->count();
    $sheetMax = (int) config('report_sheets.max_notes', 7);
    $sheetN = ($notesN >= 1 && $notesN <= $sheetMax) ? (int) $notesN : null;
    $u = auth()->user();
    $canUpdateM = $u->can('update', $r);
    $canDeleteM = $u->can('delete', $r);
    $canPublishM = $u->can('publish', $r);
    $canUnpublishM = $u->can('unpublish', $r);
    $canExportM = $u->can('export', $r);
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
                        <div class="relative shrink-0" data-report-menu>
                            <button type="button" data-report-menu-btn class="report-menu-btn w-10 h-10 sm:w-8 sm:h-8 inline-flex items-center justify-center rounded-lg text-[#4a5a4f] hover:bg-[#f6f7f5] hover:text-[#0e6a38] cursor-pointer select-none text-lg sm:text-base" aria-label="{{ __('ui.more_options') }}" aria-haspopup="menu" aria-expanded="false">⋮</button>
                            <div data-report-menu-list hidden class="report-menu absolute end-0 start-auto top-11 sm:top-9 z-30 w-max min-w-[160px] sm:min-w-[190px] max-w-[calc(100vw-2rem)] overflow-hidden bg-[#fdfcfa] border border-[#e6e9e1] rounded-xl shadow-xl py-1.5 text-sm origin-top" role="menu">
                                @if($isPub)
                                    <a href="{{ route('reports.preview', $r) }}" role="menuitem" class="flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.view_report') }}</a>
                                    <a href="{{ route('reports.show', $r) }}" role="menuitem" class="flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.open_report') }}</a>
                                    @if($canExportM)
                                    <a href="{{ route('reports.export.pdf', $r) }}" role="menuitem" class="flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.export_pdf') }}</a>
                                    <a href="{{ route('reports.export.image', $r) }}" role="menuitem" class="flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.export_image') }}</a>
                                    <button type="button" role="menuitem" data-wa="{{ e($waText) }}" onclick="window.open('https://wa.me/?text='+encodeURIComponent(this.getAttribute('data-wa')),'_blank','noopener')" class="w-full text-start flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.share_via_whatsapp') }}</button>
                                    @endif
                                    @if($canUnpublishM)
                                    <form method="POST" action="{{ route('reports.unpublish', $r) }}">
                                        @csrf
                                        <button type="submit" role="menuitem" class="w-full text-start flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.unpublish') ?? 'سحب النشر' }}</button>
                                    </form>
                                    @endif
                                    @if($canDeleteM)
                                    <form method="POST" action="{{ route('reports.destroy', $r) }}" onsubmit="return confirm('{{ __('ui.confirm_delete') ?? 'تأكيد الحذف؟' }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" role="menuitem" class="w-full text-start flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-red-600 hover:bg-red-50">{{ __('ui.delete') ?? 'حذف' }}</button>
                                    </form>
                                    @endif
                                @else
                                    <a href="{{ route('reports.show', $r) }}" role="menuitem" class="flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.open_report') }}</a>
                                    @if($canUpdateM)
                                    <a href="{{ route('reports.edit', $r) }}" role="menuitem" class="flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#1a2e1f] hover:bg-[#f6f7f5]">{{ __('ui.edit_btn') ?? 'تعديل' }}</a>
                                    @endif
                                    @if($canPublishM)
                                    <form method="POST" action="{{ route('reports.publish', $r) }}">
                                        @csrf
                                        <button type="submit" role="menuitem" class="w-full text-start flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-[#0e6a38] font-bold hover:bg-[#f6f7f5]">{{ __('ui.publish') ?? 'نشر' }}</button>
                                    </form>
                                    @endif
                                    @if($canDeleteM)
                                    <form method="POST" action="{{ route('reports.destroy', $r) }}" onsubmit="return confirm('{{ __('ui.confirm_delete') ?? 'تأكيد الحذف؟' }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" role="menuitem" class="w-full text-start flex items-center px-4 py-2.5 min-h-[44px] whitespace-nowrap text-red-600 hover:bg-red-50">{{ __('ui.delete') ?? 'حذف' }}</button>
                                    </form>
                                    @endif
                                @endif
                            </div>
                        </div>
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
                        <span class="truncate">{{ $r->author->localized_name ?? '' }}</span>
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
    // قائمة ⋮ زر موثوق (بدل <details> الهش): واحدة مفتوحة فقط + إغلاق خارجي + Escape
    function allMenus() {
        return document.querySelectorAll('[data-report-menu]');
    }
    function closeAll(except) {
        allMenus().forEach(function (wrap) {
            if (wrap !== except) {
                wrap.querySelector('[data-report-menu-list]')?.setAttribute('hidden', '');
                wrap.querySelector('[data-report-menu-btn]')?.setAttribute('aria-expanded', 'false');
                wrap.closest('.report-card')?.classList.remove('z-20');
            }
        });
    }
    allMenus().forEach(function (wrap) {
        const btn = wrap.querySelector('[data-report-menu-btn]');
        const list = wrap.querySelector('[data-report-menu-list]');
        if (!btn || !list) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const isHidden = list.hasAttribute('hidden');
            closeAll(isHidden ? wrap : null);
            if (isHidden) {
                list.removeAttribute('hidden');
                btn.setAttribute('aria-expanded', 'true');
                wrap.closest('.report-card')?.classList.add('z-20');
            } else {
                list.setAttribute('hidden', '');
                btn.setAttribute('aria-expanded', 'false');
                wrap.closest('.report-card')?.classList.remove('z-20');
            }
        });
        // نقرة داخل القائمة لا تغلقها قبل تنفيذ الإجراء (روابط/نماذج)
        list.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    });
    document.addEventListener('click', function () {
        closeAll(null);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll(null);
    });
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

