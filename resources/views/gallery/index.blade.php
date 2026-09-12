@extends('layouts.app')

@section('content')
@php
    $activeFilters = collect(request()->only(['observer', 'camera_number', 'floor_number', 'type', 'date', 'sort']))->filter(fn($v) => $v !== null && $v !== '')->count();
    $observerUser = null;
    if (request()->filled('observer') && isset($observers)) {
        $observerUser = $observers->firstWhere('id', (int) request('observer'));
    }
@endphp
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
                class="shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition text-ink-500 dark:text-[#9bb0a0] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] hover:text-ink-700 dark:hover:text-[#e7ece5]"
                aria-expanded="false" aria-controls="gal-filters" aria-label="{{ __('ui.filter_aria') }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                <span id="gal-filter-dot" class="{{ $activeFilters ? '' : 'hidden' }} min-w-[18px] h-[18px] px-1 rounded-full bg-[#0e6a38]/10 dark:bg-[#4ade80]/15 text-[#0e6a38] dark:text-[#4ade80] text-[10px] font-extrabold inline-flex items-center justify-center tabular-nums">{{ $activeFilters }}</span>
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

        <div id="gal-grid" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4 auto-rows-fr items-stretch">
            @foreach($attachments as $item)
                @php
                    $isImg = str_starts_with($item['mime'], 'image/');
                    $isVid = str_starts_with($item['mime'], 'video/');
                    $isAud = str_starts_with($item['mime'], 'audio/');
                @endphp
                <article class="group flex flex-col h-full bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] overflow-hidden hover:border-[#d4ddd3] dark:hover:border-[#404840] hover:shadow-[0_4px_14px_-6px_rgba(26,46,31,0.15)] dark:hover:shadow-[0_8px_16px_-8px_rgba(0,0,0,0.5)] transition-all duration-200">
                    <a href="{{ $item['parentUrl'] }}" class="block relative aspect-[4/3] w-full shrink-0 bg-[#f5f7f5] dark:bg-[#1e2320] overflow-hidden" title="{{ __('ui.open_parent') }}">
                        @if($isImg)
                            <img src="{{ $item['viewUrl'] }}" alt="{{ __('ui.attachment_alt') }}" class="h-full w-full object-cover group-hover:scale-[1.03] transition-transform duration-500" loading="lazy">
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

                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold shadow-sm {{ $item['kind'] === 'note' ? 'bg-[#0e6a38]/90 text-white' : 'bg-[#b45309]/90 text-white' }}">{{ $item['parentKind'] }}</span>
                    </a>
                    <div class="px-3 pt-2.5 pb-3 flex-1 flex flex-col justify-center gap-1 min-h-[68px]">
                        <a href="{{ $item['parentUrl'] }}" class="block h-5 leading-5 text-[13px] font-bold text-ink-700 dark:text-[#e7ece5] hover:text-[#0e6a38] dark:hover:text-[#4ade80] transition truncate">{{ $item['ownerName'] }}</a>
                        <div class="h-7 flex items-center gap-2 text-[11px] text-ink-400 dark:text-[#9bb0a0] overflow-hidden">
                            <span class="truncate">{{ __('ui.camera_floor_format', ['camera' => $item['camera'], 'floor' => $item['floor']]) }}</span>
                            <span class="font-mono tabular-nums shrink-0" dir="ltr">{{ $item['created']->format('Y-m-d') }}</span>
                            @if(auth()->user()->isReportWriter())
                                <a href="{{ $item['downloadUrl'] }}" title="{{ __('ui.download_aria') }}" aria-label="{{ __('ui.download_aria') }}" class="mr-auto shrink-0 w-7 h-7 rounded-lg bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-ink-400 dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] flex items-center justify-center transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>


        <div id="gal-compact" class="hidden grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-2.5 auto-rows-fr items-stretch">
            @foreach($attachments as $item)
                <a href="{{ $item['parentUrl'] }}" title="{{ __('ui.camera_floor_format', ['camera' => $item['camera'], 'floor' => '']) }}"
                   class="group relative block aspect-square w-full shrink-0 rounded-xl overflow-hidden bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#e6e9e1] dark:border-[#343a34] hover:border-[#d4ddd3] dark:hover:border-[#404840] hover:shadow-sm transition">
                    @if(str_starts_with($item['mime'], 'image/'))
                        <img src="{{ $item['viewUrl'] }}" alt="" class="h-full w-full object-cover group-hover:scale-[1.04] transition-transform duration-500" loading="lazy">
                    @elseif(str_starts_with($item['mime'], 'video/'))
                        <span class="flex h-full w-full items-center justify-center bg-[#f5f7f5] dark:bg-[#1e2320] text-[#0e6a38] dark:text-[#4ade80]">
                            <span class="w-10 h-10 rounded-full bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 -mr-px" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </span>
                    @elseif(str_starts_with($item['mime'], 'audio/'))
                        <span class="flex h-full w-full items-center justify-center bg-[#f5f7f5] dark:bg-[#1e2320] text-[#0e6a38] dark:text-[#4ade80]">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                    @else
                        <span class="flex h-full w-full items-center justify-center text-ink-300 dark:text-[#8a9a8a] text-xs">{{ __('ui.attachment_alt') }}</span>
                    @endif
                    <span class="absolute bottom-1 right-1 px-1.5 py-px rounded-full text-[9px] font-bold shadow-sm {{ $item['kind'] === 'note' ? 'bg-[#0e6a38]/90 text-white' : 'bg-[#b45309]/90 text-white' }}">{{ $item['kind'] === 'note' ? __('ui.attachment_kind_note') : __('ui.attachment_kind_submission') }}</span>
                </a>
            @endforeach
        </div>


        <div id="gal-list" class="hidden max-w-3xl mx-auto bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] divide-y divide-[#eceee9] dark:divide-[#2e352e] overflow-hidden">
            @foreach($attachments as $item)
                <div class="flex items-center gap-3 px-3 sm:px-4 py-3 hover:bg-[#f8faf8] dark:hover:bg-[#2e352e] transition group">
                    <a href="{{ $item['parentUrl'] }}" class="flex items-center gap-3 flex-1 min-w-0">
                    <span class="relative block shrink-0 w-12 h-12 rounded-xl overflow-hidden bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#e6e9e1] dark:border-[#343a34]">
                        @if(str_starts_with($item['mime'], 'image/'))
                            <img src="{{ $item['viewUrl'] }}" alt="" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <span class="flex h-full w-full items-center justify-center bg-[#f5f7f5] dark:bg-[#1e2320] text-[#0e6a38] dark:text-[#4ade80]">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                        @endif
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[13px] font-bold text-ink-700 dark:text-[#e7ece5] group-hover:text-[#0e6a38] dark:group-hover:text-[#4ade80] transition truncate">{{ $item['ownerName'] }}</span>
                        <span class="mt-0.5 block text-[11px] text-ink-400 dark:text-[#9bb0a0] truncate">{{ __('ui.camera_floor_format', ['camera' => $item['camera'], 'floor' => $item['floor']]) }} · <span class="font-mono tabular-nums" dir="ltr">{{ $item['created']->format('Y-m-d H:i') }}</span></span>
                    </span>
                    </a>
                    @if(auth()->user()->isReportWriter())
                        <a href="{{ $item['downloadUrl'] }}" title="{{ __('ui.download_aria') }}" aria-label="{{ __('ui.download_aria') }}" class="shrink-0 w-9 h-9 rounded-lg bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-ink-400 dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] flex items-center justify-center transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                    @endif
                    <svg class="w-4 h-4 text-ink-200 dark:text-[#4a5a4f] group-hover:text-ink-400 dark:group-hover:text-[#9bb0a0] group-hover:-translate-x-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex justify-center">{{ $attachments->links() }}</div>
    @endif
</div>
<script>
(function(){
    var views = {
        grid: document.getElementById('gal-grid'),
        compact: document.getElementById('gal-compact'),
        list: document.getElementById('gal-list')
    };
    var btns = {
        grid: document.getElementById('gal-grid-btn'),
        compact: document.getElementById('gal-compact-btn'),
        list: document.getElementById('gal-list-btn')
    };
    var fb = document.getElementById('gal-filter-btn'), fp = document.getElementById('gal-filters');

    var onCls = 'bg-[#0e6a38]/10 dark:bg-[#4ade80]/15 text-[#0e6a38] dark:text-[#4ade80]',
        offCls = 'text-ink-400 dark:text-[#8a9a8a] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] hover:text-ink-700 dark:hover:text-[#e7ece5]';
    function paint(mode){
        Object.keys(views).forEach(function(k){
            if (!views[k]) return;
            var active = k === mode;
            views[k].classList.toggle('hidden', !active);

            if (k === 'compact' || k === 'grid') views[k].classList.toggle('grid', active);
        });
        Object.keys(btns).forEach(function(k){
            var b = btns[k]; if (!b) return;
            var active = k === mode;
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
            b.className = 'gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition ' + (active ? onCls : offCls);
        });
    }
    var mode = 'grid';
    try { mode = localStorage.getItem('gallery_view') || 'grid'; } catch(e){}
    if (!views[mode]) mode = 'grid';
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
