@extends('layouts.app')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-[#0e6a38] text-white flex items-center justify-center shadow-sm shrink-0">
            <x-phosphor-files-duotone class="w-5 h-5" />
        </div>
        <div>
            <h1 class="report-page-title text-xl font-extrabold text-[#1a2e1f] leading-tight">{{ __('ui.daily_reports') }}</h1>
            <p class="report-sub text-xs text-[#6b7a6e] mt-0.5">{{ $reports->total() === 1 ? __('ui.report_one') : ($reports->total() === 2 ? __('ui.report_two') : __('ui.reports_many', ['n' => $reports->total()])) }}</p>
        </div>
    </div>
    @if(auth()->user()->isReportWriter() || auth()->user()->isGeneralManager())
    <div class="flex items-center gap-2 shrink-0">
        @if(auth()->user()->can('viewInsights', \App\Models\Report::class))
        <a href="{{ route('reports.insights') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#fdfcfa] border border-[#e6e9e1] hover:border-[#0e6a38] text-[#0e6a38] font-bold text-sm shadow-sm transition">
            <x-phosphor-chart-line-up-duotone class="w-4 h-4" />
            {{ __('ui.view_insights') }}
        </a>
        @endif
        @if(auth()->user()->can('create', \App\Models\Report::class))
        <a href="{{ route('reports.create') }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition">
            <x-phosphor-plus-duotone class="w-4 h-4" />
            {{ __('ui.new_report') }}
        </a>
        @endif
    </div>
    @endif
</div>

@php
    $isWriterView = auth()->user()->isReportWriter() || auth()->user()->isGeneralManager();
    $canSeeDrafts = auth()->user()->isReportWriter();
    $activeFilters = collect(['report_date' => request('report_date'), 'status' => $canSeeDrafts ? request('status') : null])->filter(fn($v) => $v !== null && $v !== '')->count();
    $rs = $reportStats ?? ['total' => $reports->total(), 'published' => 0, 'draft' => 0, 'today' => 0];
@endphp

<div class="m-stats md:hidden mb-4" aria-label="{{ __('ui.daily_reports') }}">
    <div class="m-stat green">
        <div class="m-stat-top">
            <span class="m-stat-ic"><x-phosphor-files-duotone class="w-5 h-5" /></span>
            <span class="m-stat-delta up">{{ __('ui.total') }}</span>
        </div>
        <div class="m-stat-v tabular-nums">{{ $rs['total'] }}</div>
        <div class="m-stat-k">{{ __('ui.daily_reports') }}</div>
    </div>
    <div class="m-stat slate">
        <div class="m-stat-top">
            <span class="m-stat-ic"><x-phosphor-seal-check-duotone class="w-5 h-5" /></span>
            <span class="m-stat-delta">{{ __('ui.published') }}</span>
        </div>
        <div class="m-stat-v tabular-nums">{{ $rs['published'] }}</div>
        <div class="m-stat-k">{{ __('ui.published') }}</div>
    </div>
    @if($canSeeDrafts)<div class="m-stat amber">
        <div class="m-stat-top">
            <span class="m-stat-ic"><x-phosphor-pencil-line-duotone class="w-5 h-5" /></span>
            <span class="m-stat-delta down">{{ __('ui.draft') }}</span>
        </div>
        <div class="m-stat-v tabular-nums">{{ $rs['draft'] }}</div>
        <div class="m-stat-k">{{ __('ui.draft') }}</div>
    </div>@endif
    <div class="m-stat green">
        <div class="m-stat-top">
            <span class="m-stat-ic"><x-phosphor-calendar-check-duotone class="w-5 h-5" /></span>
            <span class="m-stat-delta up">{{ __('ui.today') ?? 'اليوم' }}</span>
        </div>
        <div class="m-stat-v tabular-nums">{{ $rs['today'] }}</div>
        <div class="m-stat-k">{{ __('ui.report_date_filter') }}</div>
    </div>
</div>
<div class="mb-5">
    <button type="button" id="filter-toggle" class="report-filter-btn inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full sm:rounded-xl bg-[#fdfcfa] border border-[#e6e9e1] text-xs sm:text-sm font-bold text-[#4a5a4f] shadow-sm hover:border-[#0e6a38] hover:text-[#0e6a38] transition" aria-expanded="false" aria-controls="filter-panel">
        <x-phosphor-funnel-simple-duotone class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
        @if($activeFilters)<span class="min-w-[18px] sm:min-w-[20px] h-[18px] sm:h-5 px-1 rounded-full bg-[#0e6a38] text-white text-[10px] sm:text-[11px] font-bold inline-flex items-center justify-center">{{ $activeFilters }}</span>@endif
        <x-phosphor-caret-down-duotone id="filter-chevron" class="w-3 h-3 sm:w-3.5 sm:h-3.5 transition-transform" />
    </button>
    <form method="GET" id="filter-panel" class="hidden mt-2 bg-[#fdfcfa] border border-[#e6e9e1] rounded-2xl p-3.5 flex flex-wrap items-end gap-2.5 shadow-sm">
        <label class="report-filter-label grid gap-1 text-xs font-medium text-[#6b7a6e]">{{ __('ui.report_date_filter') }}<input type="date" name="report_date" value="{{ request('report_date') }}" class="border border-[#e6e9e1] rounded-xl px-3 py-2 text-sm text-[#1a2e1f] focus:outline-none focus:border-[#0e6a38]"></label>
        @if($canSeeDrafts)
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
        <x-phosphor-files-duotone class="w-6 h-6" />
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
                    <x-phosphor-files-duotone class="w-10 h-10 text-white/70" />
                </div>
            @endif
        </div>
        <div class="flex gap-3 p-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('reports.show', $r) }}" class="report-title font-extrabold text-[15px] text-[#1a2e1f] truncate group-hover:text-[#0e6a38] transition">{{ l10n_text('report', $r->id, 'title', $r->title) }}</a>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($isWriterView)
                        <span class="report-pill inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#f1f3f0] text-[#4a5a4f] border border-[#e6e9e1]"><span class="w-1.5 h-1.5 rounded-full {{ $isPub ? 'bg-[#0e6a38]' : 'bg-amber-400' }}"></span><span data-status="{{ $r->status }}">{{ $isPub ? __('ui.published') : __('ui.draft') }}</span></span>
                        @endif
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
                        <x-phosphor-calendar-duotone class="w-3.5 h-3.5" />
                        {{ $r->report_date->toDateString() }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <x-phosphor-file-text-duotone class="w-3.5 h-3.5" />
                        {{ $notesN }} {{ $notesN === 1 ? __('ui.note_one') : __('ui.notes_many') }}
                    </span>
                    <span class="inline-flex items-center gap-1 min-w-0">
                        <x-phosphor-user-duotone class="w-3.5 h-3.5 shrink-0" />
                        <a href="{{ $r->author ? route('profile.showUser', $r->author->id) : '#' }}" @if(!$r->author) class="pointer-events-none opacity-60" @endif class="truncate hover:text-[#0e6a38] hover:underline transition">{{ $r->author->localized_name ?? '' }}</a>
                    </span>
                </div>
                <a href="{{ route('reports.show', $r) }}" class="inline-flex items-center gap-1 mt-2.5 text-[13px] font-bold text-[#0e6a38] hover:gap-2 transition-all">
                    {{ __('ui.open_report') }}
                    <x-phosphor-arrow-left-duotone class="w-3.5 h-3.5" />
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
                    <x-phosphor-phone-duotone class="w-4 h-4 text-[#0e6a38] dark:text-[#5cb87a]" />
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
                    <x-user-link :user="$c" size="xl" shape="rounded-xl" skin="soft" />
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('profile.showUser', $c->id) }}" class="block text-[13.5px] font-semibold tracking-tight text-ink-800 dark:text-[#e7ece5] truncate hover:text-[#0e6a38] hover:underline transition">{{ $c->localized_name }}</a>
                        <div class="text-xs text-ink-500 dark:text-[#8a9a8e] truncate">{{ $c->isReportWriter() ? __('ui.role_writer') : __('ui.role_monitor') }}</div>
                        <div class="mt-0.5 text-[11px] font-mono tabular-nums tracking-widest text-ink-600 dark:text-[#9bb0a0]" dir="ltr">{{ $c->personal_number }}</div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <a href="tel:{{ $c->personal_number }}" class="w-9 h-9 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-[#252b26] border border-[#eceee9] dark:border-[#2e352e] text-ink-600 dark:text-[#9bb0a0] hover:text-[#0e6a38] hover:border-[#e0e7df] flex items-center justify-center transition touch-manipulation" title="{{ __('ui.call') }}">
                            <x-phosphor-phone-duotone class="w-3.5 h-3.5" />
                        </a>
                        @if($c->whatsapp_url)
                        <a href="{{ $c->whatsapp_url }}" target="_blank" rel="noopener" class="w-9 h-9 sm:w-8 sm:h-8 rounded-full bg-[#25D366] hover:bg-[#1da851] active:bg-[#168a3a] text-white flex items-center justify-center shadow-sm transition touch-manipulation" title="{{ __('ui.share_whatsapp') }}">
                            <x-phosphor-whatsapp-logo-duotone class="w-3.5 h-3.5" />
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
