@extends('layouts.app')

@section('content')
@php
    $activeFilters = collect(request()->only(['observer', 'camera_number', 'floor_number', 'type', 'date', 'sort']))->filter(fn($v) => $v !== null && $v !== '')->count();
    $observerUser = null;
    if (request()->filled('observer') && isset($observers)) {
        $observerUser = $observers->firstWhere('id', (int) request('observer'));
    }
@endphp
<style>
    /* ── Gallery single-render layout switching ── */
    #gal-items{display:grid;gap:0.75rem;grid-template-columns:repeat(2,1fr)}
    @media(min-width:640px){#gal-items{gap:1rem}}
    @media(min-width:1024px){#gal-items{grid-template-columns:repeat(3,1fr)}}
    @media(min-width:1280px){#gal-items{grid-template-columns:repeat(4,1fr)}}

    #gal-items .gal-item{position:relative}
    #gal-items .gal-thumb-link{position:relative}
    #gal-items .gal-info{display:flex}
    #gal-items .gal-compact-only{display:none}
    #gal-items .gal-list-only{display:none}
    #gal-items .gal-thumb-link .gal-kind-badge{top:0.5rem;left:0.5rem;bottom:auto;right:auto}
    #gal-items .gal-list-link{display:none}

    #gal-items[data-view="compact"]{grid-template-columns:repeat(3,1fr);gap:0.5rem}
    @media(min-width:640px){#gal-items[data-view="compact"]{grid-template-columns:repeat(4,1fr)}}
    @media(min-width:1024px){#gal-items[data-view="compact"]{grid-template-columns:repeat(6,1fr)}}
    #gal-items[data-view="compact"] .gal-info{display:none!important}
    #gal-items[data-view="compact"] .gal-compact-only{display:flex!important}
    #gal-items[data-view="compact"] .gal-thumb-link .gal-kind-badge{top:auto;left:auto;bottom:0.25rem;right:0.25rem;font-size:9px;padding:1px 6px}
    #gal-items[data-view="compact"] .gal-thumb-link img{transform:scale(1);transition:transform .5s}
    #gal-items[data-view="compact"] .gal-thumb-link:hover img{transform:scale(1.04)}

    #gal-items[data-view="list"]{display:flex;flex-direction:column;gap:0;max-width:48rem;margin-left:auto;margin-right:auto;background:var(--c-surface);border:1px solid var(--c-border);border-radius:1rem;overflow:hidden}
    #gal-items[data-view="list"] .gal-item{display:flex!important;align-items:center;gap:0.75rem;padding:0.75rem 1rem;background:transparent!important;border:none!important;border-bottom:1px solid var(--c-divider)!important;border-radius:0!important;overflow:visible!important;transition:background .15s}
    #gal-items[data-view="list"] .gal-item:last-child{border-bottom:none}
    #gal-items[data-view="list"] .gal-item:hover{background:var(--c-hover)}
    #gal-items[data-view="list"] .gal-thumb-link{width:3rem;height:3rem;min-width:3rem;border-radius:0.75rem;overflow:hidden;position:relative;border:1px solid var(--c-border);aspect-ratio:auto}
    #gal-items[data-view="list"] .gal-thumb-link img{border-radius:0}
    #gal-items[data-view="list"] .gal-info{display:flex!important;flex:1;min-width:0;gap:0.75rem;align-items:center;padding:0;border-radius:0;background:none;border:none}
    #gal-items[data-view="list"] .gal-info .gal-owner{font-size:13px}
    #gal-items[data-view="list"] .gal-info .gal-meta{font-size:11px;margin-top:2px}
    #gal-items[data-view="list"] .gal-download{display:flex!important;width:2.25rem;height:2.25rem;border-radius:0.5rem;font-size:1rem}
    #gal-items[data-view="list"] .gal-list-only{display:flex!important}
    #gal-items[data-view="list"] .gal-compact-only{display:none!important}
    #gal-items[data-view="list"] .gal-thumb-link .gal-kind-badge{display:none}

    #gal-items .gal-download{display:none}
    @media(min-width:640px){#gal-items:not([data-view="list"]) .gal-download{display:flex}}

    :root{
        --c-surface:#fff;--c-surface-alt:#f5f7f5;--c-border:#e6e9e1;--c-border-hover:#d4ddd3;--c-divider:#eceee9;
        --c-text:#1a2e1f;--c-text2:#6b7a6e;--c-text3:#737373;--c-text4:#9aa99a;
        --c-primary:#0e6a38;--c-primary-text:#fff;--c-hover:#f8faf8;
        --c-badge-note:rgba(14,106,56,.9);--c-badge-sub:rgba(180,83,9,.9);
    }
    html.dark{
        --c-surface:#252b26;--c-surface-alt:#1e2320;--c-border:#343a34;--c-border-hover:#404840;--c-divider:#2e352e;
        --c-text:#e7ece5;--c-text2:#9bb0a0;--c-text3:#9bb0a0;--c-text4:#8a9a8a;
        --c-primary:#4ade80;--c-hover:#2e352e;
        --c-badge-note:rgba(74,222,128,.9);--c-badge-sub:rgba(240,192,64,.9);
    }
</style>

<div class="max-w-6xl mx-auto">

    <div class="text-center mb-4">
        <h1 class="text-[17px] sm:text-lg font-extrabold text-ink-800 dark:text-[#e7ece5] leading-tight">{{ __('ui.gallery_title') }}</h1>
        <p class="text-xs text-[#737373] dark:text-[#9bb0a0] mt-1">{{ __('ui.attachments_count', ['count' => $attachments->total()]) }}</p>
    </div>

    <div class="flex justify-center mb-5">
        <div class="inline-flex items-center gap-0.5 bg-white dark:bg-[#252b26] border border-[#e6e9e1] dark:border-[#343a34] rounded-full pl-1.5 pr-1 py-1 shadow-[0_1px_2px_rgba(26,46,31,0.05)] dark:shadow-none max-w-full overflow-x-auto" role="group" aria-label="{{ __('ui.view_options_aria') }}">
            <button type="button" id="gal-grid-btn" aria-pressed="true" title="{{ __('ui.grid_view') }}"
                class="gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span class="hidden sm:inline">{{ __('ui.grid_view') }}</span>
            </button>
            <button type="button" id="gal-compact-btn" aria-pressed="false" title="{{ __('ui.compact_view') }}"
                class="gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4zM4 9h16M4 15h16M9 4v16M15 4v16"/></svg>
                <span class="hidden sm:inline">{{ __('ui.compact_view') }}</span>
            </button>
            <button type="button" id="gal-list-btn" aria-pressed="false" title="{{ __('ui.list_view') }}"
                class="gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                <span class="hidden sm:inline">{{ __('ui.list_view') }}</span>
            </button>
            <span class="w-px h-5 bg-[#e6e9e1] dark:bg-[#343a34] mx-1.5 shrink-0" aria-hidden="true"></span>
            <button type="button" id="gal-filter-btn"
                class="shrink-0 inline-flex items-center gap-1 sm:gap-1.5 px-2.5 sm:px-3 h-7 sm:h-8 rounded-full text-[11px] sm:text-xs font-bold transition text-ink-500 dark:text-[#9bb0a0] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] hover:text-ink-700 dark:hover:text-[#e7ece5]"
                aria-expanded="false" aria-controls="gal-filters" aria-label="{{ __('ui.filter_aria') }}">
                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                <span id="gal-filter-dot" class="{{ $activeFilters ? '' : 'hidden' }} min-w-[16px] sm:min-w-[18px] h-[16px] sm:h-[18px] px-1 rounded-full bg-[#0e6a38]/10 dark:bg-[#4ade80]/15 text-[#0e6a38] dark:text-[#4ade80] text-[9px] sm:text-[10px] font-extrabold inline-flex items-center justify-center tabular-nums">{{ $activeFilters }}</span>
            </button>
        </div>
    </div>

    <form id="gal-filters" method="GET" action="{{ route('gallery.index') }}"
        class="hidden max-w-3xl mx-auto bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] p-4 mb-6 grid grid-cols-2 sm:grid-cols-3 gap-3 shadow-[0_1px_2px_rgba(26,46,31,0.04)] dark:shadow-none">
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-camera">{{ __('ui.filter_camera') }}</label>
            <select id="f-camera" name="camera_number" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="">{{ __('ui.filter_all') }}</option>
                @foreach($cameras as $cam)
                    <option value="{{ $cam }}" @selected(request('camera_number') == (string) $cam)>{{ __('ui.camera_floor_format', ['camera' => $cam, 'floor' => '']) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-floor">{{ __('ui.filter_floor') }}</label>
            <select id="f-floor" name="floor_number" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="">{{ __('ui.filter_all') }}</option>
                @foreach($floors as $fl)
                    <option value="{{ $fl }}" @selected(request('floor_number') == (string) $fl)>{{ __('ui.filter_floor') }} {{ $fl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-type">{{ __('ui.filter_type') }}</label>
            <select id="f-type" name="type" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="">{{ __('ui.filter_all') }}</option>
                <option value="image" @selected(request('type') === 'image')>{{ __('ui.filter_images') }}</option>
                <option value="video" @selected(request('type') === 'video')>{{ __('ui.filter_video') }}</option>
                <option value="audio" @selected(request('type') === 'audio')>{{ __('ui.filter_audio') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-date">{{ __('ui.filter_date') }}</label>
            <input id="f-date" type="date" name="date" value="{{ request('date') }}" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-[7px] px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none [color-scheme:light] dark:[color-scheme:dark]">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-sort">{{ __('ui.filter_sort') }}</label>
            <select id="f-sort" name="sort" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('ui.sort_newest') }}</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('ui.sort_oldest') }}</option>
            </select>
        </div>
        <div class="flex items-end gap-2 col-span-2 sm:col-span-1">
            <a href="{{ route('gallery.index') }}" class="flex-1 text-center px-4 py-2 rounded-xl text-ink-400 dark:text-[#9bb0a0] hover:text-ink-700 dark:hover:text-[#e7ece5] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] font-bold text-[13px] transition border border-dashed border-[#e6e9e1] dark:border-[#343a34]">{{ __('ui.clear_filters') }}</a>
        </div>
    </form>

    @if($attachments->isEmpty())
        <div class="max-w-md mx-auto bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] p-10 text-center">
            <div class="w-12 h-12 rounded-full bg-[#f5f7f5] dark:bg-[#2a302b] flex items-center justify-center mx-auto mb-3 text-ink-300 dark:text-[#8a9a8a]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-ink-700 dark:text-[#e7ece5]">{{ __('ui.no_attachments') }}</h3>
        </div>
    @else

        <div id="gal-items" data-view="grid">
            @foreach($attachments as $item)
                @php
                    $isImg = str_starts_with($item['mime'], 'image/');
                    $isVid = str_starts_with($item['mime'], 'video/');
                    $isAud = str_starts_with($item['mime'], 'audio/');
                    $isNote = $item['kind'] === 'note';
                    $kindLabel = $isNote ? __('ui.attachment_kind_note') : __('ui.attachment_kind_submission');
                    $thumbSrc = $item['thumbUrl'] ?? $item['viewUrl'];
                    $camFloor = __('ui.camera_floor_format', ['camera' => $item['camera'], 'floor' => $item['floor']]);
                    $dateStr = $item['created']->format('Y-m-d');
                    $dateTimeStr = $item['created']->format('Y-m-d H:i');
                    $canDownload = auth()->user()->isReportWriter();
                @endphp
                <div class="gal-item group bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] overflow-hidden hover:border-[#d4ddd3] dark:hover:border-[#404840] hover:shadow-[0_4px_14px_-6px_rgba(26,46,31,0.15)] dark:hover:shadow-[0_8px_16px_-8px_rgba(0,0,0,0.5)] transition-all duration-200">

                    {{-- Thumbnail: shared by grid + compact --}}
                    <a href="{{ $item['parentUrl'] }}" class="gal-thumb-link block relative aspect-[4/3] w-full shrink-0 bg-[#f5f7f5] dark:bg-[#1e2320] overflow-hidden" title="{{ __('ui.open_parent') }}">
                        @if($isImg)
                            <img src="{{ $thumbSrc }}" alt="{{ __('ui.attachment_alt') }}" class="h-full w-full object-cover group-hover:scale-[1.03] transition-transform duration-500" loading="lazy" decoding="async">
                        @elseif($isVid)
                            <span class="flex h-full w-full flex-col items-center justify-center bg-[#f5f7f5] dark:bg-[#1e2320] gap-2">
                                <span class="w-14 h-14 rounded-full bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] flex items-center justify-center text-[#0e6a38] dark:text-[#4ade80] shadow-sm">
                                    <svg class="w-6 h-6 -mr-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                                <span class="text-xs font-bold text-ink-400 dark:text-[#9bb0a0]">{{ __('ui.video_clip') }}</span>
                            </span>
                        @elseif($isAud)
                            <span class="flex h-full w-full flex-col items-center justify-center bg-[#f5f7f5] dark:bg-[#1e2320] gap-2">
                                <span class="w-14 h-14 rounded-full bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] flex items-center justify-center text-[#0e6a38] dark:text-[#4ade80]">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </span>
                                <span class="text-xs font-bold text-ink-400 dark:text-[#9bb0a0]">{{ __('ui.audio_clip') }}</span>
                            </span>
                        @else
                            <span class="flex h-full w-full items-center justify-center text-ink-300 dark:text-[#8a9a8a] text-[11px] font-bold">{{ __('ui.attachment_alt') }}</span>
                        @endif

                        <span class="gal-kind-badge absolute px-2 py-0.5 rounded-full text-[10px] font-bold shadow-sm {{ $isNote ? 'bg-[#0e6a38]/90 text-white' : 'bg-[#b45309]/90 text-white' }}">{{ $kindLabel }}</span>
                    </a>

                    {{-- Grid/compact info panel --}}
                    <div class="gal-info px-3 pt-2.5 pb-3 flex-1 flex flex-col justify-center gap-1 min-h-[68px]">
                        <a href="{{ $item['parentUrl'] }}" class="gal-owner block h-5 leading-5 text-[13px] font-bold text-ink-700 dark:text-[#e7ece5] hover:text-[#0e6a38] dark:hover:text-[#4ade80] transition truncate">{{ $item['ownerName'] }}</a>
                        <div class="gal-meta h-7 flex items-center gap-2 text-[11px] text-ink-400 dark:text-[#9bb0a0] overflow-hidden">
                            <span class="truncate">{{ $camFloor }}</span>
                            <span class="font-mono tabular-nums shrink-0" dir="ltr">{{ $dateStr }}</span>
                            @if($canDownload)
                                <a href="{{ $item['downloadUrl'] }}" title="{{ __('ui.download_aria') }}" aria-label="{{ __('ui.download_aria') }}" class="gal-download mr-auto shrink-0 w-7 h-7 rounded-lg bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-ink-400 dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] flex items-center justify-center transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Compact-only badge (shown via CSS) --}}
                    <span class="gal-compact-only absolute bottom-1 right-1 px-1.5 py-px rounded-full text-[9px] font-bold shadow-sm {{ $isNote ? 'bg-[#0e6a38]/90 text-white' : 'bg-[#b45309]/90 text-white' }}">{{ $kindLabel }}</span>

                    {{-- List-only link --}}
                    <a href="{{ $item['parentUrl'] }}" class="gal-list-only shrink-0 w-9 h-9 rounded-lg bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-ink-400 dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] items-center justify-center transition" title="{{ __('ui.open_parent') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex justify-center">{{ $attachments->links() }}</div>
    @endif
</div>
<script>
(function(){
    var container = document.getElementById('gal-items');
    if (!container) return;
    var btns = {
        grid: document.getElementById('gal-grid-btn'),
        compact: document.getElementById('gal-compact-btn'),
        list: document.getElementById('gal-list-btn')
    };
    var fb = document.getElementById('gal-filter-btn'), fp = document.getElementById('gal-filters');

    var onCls = 'bg-[#0e6a38]/10 dark:bg-[#4ade80]/15 text-[#0e6a38] dark:text-[#4ade80]',
        offCls = 'text-ink-400 dark:text-[#8a9a8a] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] hover:text-ink-700 dark:hover:text-[#e7ece5]';
    function paint(mode){
        container.setAttribute('data-view', mode);
        Object.keys(btns).forEach(function(k){
            var b = btns[k]; if (!b) return;
            var active = k === mode;
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
            b.className = 'gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition ' + (active ? onCls : offCls);
        });
    }
    var mode = 'grid';
    try { mode = localStorage.getItem('gallery_view') || 'grid'; } catch(e){}
    if (!{grid:1,compact:1,list:1}[mode]) mode = 'grid';
    paint(mode);
    Object.keys(btns).forEach(function(k){
        if (btns[k]) btns[k].addEventListener('click', function(){ mode = k; try{ localStorage.setItem('gallery_view', mode); }catch(e){} paint(mode); });
    });
    if (fb && fp) fb.addEventListener('click', function(){
        var willOpen = fp.classList.contains('hidden');
        fp.classList.toggle('hidden', !willOpen);
        if (willOpen) { fp.classList.add('grid'); }
        fb.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    var gf = document.getElementById('gal-filters');
    if (gf) gf.querySelectorAll('select, input[type="date"]').forEach(function(el){
        el.addEventListener('change', function(){ gf.submit(); });
    });
})();
</script>
@endsection
