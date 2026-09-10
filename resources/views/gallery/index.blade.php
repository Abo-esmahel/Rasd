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

    {{-- عنوان هادئ متمركز — لايت: حبر دافئ، دارك: عبر dark: --}}
    <div class="text-center mb-4">
        <h1 class="text-[17px] sm:text-lg font-extrabold text-ink-800 dark:text-[#e7ece5] leading-tight">معرض المرفقات</h1>
        <p class="text-xs text-[#737373] dark:text-[#9bb0a0] mt-1"><span class="tabular-nums font-bold text-ink-600 dark:text-[#e7ece5]">{{ $attachments->total() }}</span> مرفق</p>
    </div>

    {{-- شريط العرض: خافت، متمركز — لايت أولًا + dark: صريح --}}
    <div class="flex justify-center mb-5">
        <div class="inline-flex items-center gap-0.5 bg-white dark:bg-[#252b26] border border-[#e6e9e1] dark:border-[#343a34] rounded-full pl-1.5 pr-1 py-1 shadow-[0_1px_2px_rgba(26,46,31,0.05)] dark:shadow-none max-w-full overflow-x-auto" role="group" aria-label="خيارات العرض">
            <button type="button" id="gal-grid-btn" aria-pressed="true" title="شبكة"
                class="gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span class="hidden sm:inline">شبكة</span>
            </button>
            <button type="button" id="gal-compact-btn" aria-pressed="false" title="مدمج"
                class="gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4zM4 9h16M4 15h16M9 4v16M15 4v16"/></svg>
                <span class="hidden sm:inline">مدمج</span>
            </button>
            <button type="button" id="gal-list-btn" aria-pressed="false" title="قائمة"
                class="gal-view-btn shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                <span class="hidden sm:inline">قائمة</span>
            </button>
            <span class="w-px h-5 bg-[#e6e9e1] dark:bg-[#343a34] mx-1.5 shrink-0" aria-hidden="true"></span>
            <button type="button" id="gal-filter-btn"
                class="shrink-0 inline-flex items-center gap-1.5 px-3 h-8 rounded-full text-xs font-bold transition text-ink-500 dark:text-[#9bb0a0] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] hover:text-ink-700 dark:hover:text-[#e7ece5]"
                aria-expanded="false" aria-controls="gal-filters">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                فلترة
                <span id="gal-filter-dot" class="{{ $activeFilters ? '' : 'hidden' }} min-w-[18px] h-[18px] px-1 rounded-full bg-[#0e6a38]/10 dark:bg-[#4ade80]/15 text-[#0e6a38] dark:text-[#4ade80] text-[10px] font-extrabold inline-flex items-center justify-center tabular-nums">{{ $activeFilters }}</span>
            </button>
        </div>
    </div>

    <form id="gal-filters" method="GET" action="{{ route('gallery.index') }}" data-auto="1"
        class="hidden max-w-3xl mx-auto bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] p-4 mb-6 grid grid-cols-2 sm:grid-cols-3 gap-3 shadow-[0_1px_2px_rgba(26,46,31,0.04)] dark:shadow-none">
        <div class="col-span-2 sm:col-span-1">
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-observer">المراقب</label>
            <select id="f-observer" name="observer" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="">كل المراقبين</option>
                @foreach($observers ?? [] as $obs)
                    <option value="{{ $obs->id }}" @selected((string) request('observer') === (string) $obs->id)>{{ $obs->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-camera">الكاميرا</label>
            <select id="f-camera" name="camera_number" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="">الكل</option>
                @foreach($cameras as $cam)
                    <option value="{{ $cam }}" @selected(request('camera_number') == (string) $cam)>كاميرا {{ $cam }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-floor">الطابق</label>
            <select id="f-floor" name="floor_number" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="">الكل</option>
                @foreach($floors as $fl)
                    <option value="{{ $fl }}" @selected(request('floor_number') == (string) $fl)>طابق {{ $fl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-type">النوع</label>
            <select id="f-type" name="type" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="">الكل</option>
                <option value="image" @selected(request('type') === 'image')>صور</option>
                <option value="video" @selected(request('type') === 'video')>فيديو</option>
                <option value="audio" @selected(request('type') === 'audio')>صوت</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-date">التاريخ</label>
            <input id="f-date" type="date" name="date" value="{{ request('date') }}" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-[7px] px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none [color-scheme:light] dark:[color-scheme:dark]">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0] mb-1.5" for="f-sort">الترتيب</label>
            <select id="f-sort" name="sort" class="w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-2 px-3 text-[13px] text-ink-700 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] outline-none">
                <option value="latest" @selected(request('sort', 'latest') === 'latest')>الأحدث أولًا</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>الأقدم أولًا</option>
            </select>
        </div>
        <div class="flex items-center justify-between gap-2 col-span-2 sm:col-span-3 pt-1 border-t border-[#e6e9e1] dark:border-[#343a34] mt-1">
            <span class="text-[11px] text-ink-400 dark:text-[#9bb0a0]">يُطبَّق تلقائيًا عند الاختيار</span>
            @if($activeFilters)
                <a href="{{ route('gallery.index') }}" class="px-3 py-1.5 rounded-lg text-ink-400 dark:text-[#9bb0a0] hover:text-ink-700 dark:hover:text-[#e7ece5] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] font-bold text-xs transition">مسح الكل ({{ $activeFilters }})</a>
            @endif
        </div>
    </form>

    @if($observerUser)
        <div class="flex justify-center mb-5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white dark:bg-[#252b26] border border-[#e6e9e1] dark:border-[#343a34] text-xs">
                <span class="text-ink-400 dark:text-[#9bb0a0]">مراقب:</span>
                <span class="font-bold text-ink-700 dark:text-[#e7ece5]">{{ $observerUser->name }}</span>
                <a href="{{ route('gallery.index', request()->except(['observer', 'page'])) }}" class="text-ink-300 dark:text-[#8a9a8a] hover:text-red-600 dark:hover:text-red-400 font-bold transition" title="مسح فلتر المراقب" aria-label="مسح فلتر المراقب">✕</a>
            </span>
        </div>
    @endif

    @if($attachments->isEmpty())
        <div class="max-w-md mx-auto bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] p-10 text-center">
            <div class="w-12 h-12 rounded-full bg-[#f5f7f5] dark:bg-[#2a302b] flex items-center justify-center mx-auto mb-3 text-ink-300 dark:text-[#8a9a8a]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-ink-700 dark:text-[#e7ece5]">لا توجد مرفقات مطابقة</h3>
            <p class="text-xs text-ink-400 dark:text-[#9bb0a0] mt-1.5 leading-6">جرّب تغيير الفلاتر أو <a href="{{ route('gallery.index') }}" class="text-[#0e6a38] dark:text-[#4ade80] font-bold hover:underline">عرض الكل</a></p>
        </div>
    @else
        {{-- 1) شبكة هادئة — لايت: أبيض دافئ، دارك: كارت غامق هادئ --}}
        <div id="gal-grid" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
            @foreach($attachments as $attachment)
                @php $note = $attachment->note; @endphp
                @continue(!$note)
                @php
                    $isImg = str_starts_with($attachment->mime_type, 'image/');
                    $isVid = str_starts_with($attachment->mime_type, 'video/');
                    $isAud = str_starts_with($attachment->mime_type, 'audio/');
                @endphp
                <article class="group bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] overflow-hidden hover:border-[#d4ddd3] dark:hover:border-[#404840] hover:shadow-[0_4px_14px_-6px_rgba(26,46,31,0.15)] dark:hover:shadow-[0_8px_16px_-8px_rgba(0,0,0,0.5)] transition-all duration-200">
                    <a href="{{ route('notes.show', $note) }}" class="block relative aspect-[4/3] bg-[#f5f7f5] dark:bg-[#1e2320] overflow-hidden" title="فتح الملاحظة #{{ $note->id }}">
                        @if($isImg)
                            <img src="{{ route('notes.attachments.view', $attachment) }}" alt="مرفق الملاحظة #{{ $note->id }}" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" loading="lazy">
                        @elseif($isVid)
                            <video src="{{ route('notes.attachments.view', $attachment) }}" class="w-full h-full object-cover" preload="metadata" muted playsinline></video>
                            <span class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <span class="w-10 h-10 rounded-full bg-white/90 dark:bg-black/50 dark:backdrop-blur text-ink-700 dark:text-white flex items-center justify-center shadow-sm border border-white/40 group-hover:scale-105 transition-transform">
                                    <svg class="w-4 h-4 -mr-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </span>
                        @elseif($isAud)
                            <span class="flex flex-col items-center justify-center w-full h-full bg-[#eef4f0] dark:bg-[#1e3328] gap-2">
                                <span class="w-10 h-10 rounded-full bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] flex items-center justify-center text-ink-400 dark:text-[#4ade80]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </span>
                                <span class="text-[11px] font-bold text-ink-400 dark:text-[#9bb0a0]">مقطع صوتي</span>
                            </span>
                        @else
                            <span class="flex items-center justify-center w-full h-full text-ink-300 dark:text-[#8a9a8a] text-[11px] font-bold">مرفق</span>
                        @endif
                        {{-- شارات فوق الصورة تبقى فاتحة دائمًا لتباين مضمون في اللايت والدارك --}}
                        <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-white/90 backdrop-blur text-ink-600 text-[10px] font-bold tabular-nums shadow-sm">#{{ str_pad($note->id, 4, '0', STR_PAD_LEFT) }}</span>
                        @if($isVid || $isAud)
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-white/90 backdrop-blur text-ink-500 text-[10px] font-bold shadow-sm">{{ $isVid ? 'فيديو' : 'صوت' }}</span>
                        @endif
                    </a>
                    <div class="px-3 pt-2.5 pb-3">
                        <a href="{{ route('notes.show', $note) }}" class="block text-[13px] font-bold text-ink-700 dark:text-[#e7ece5] hover:text-[#0e6a38] dark:hover:text-[#4ade80] transition truncate">{{ $note->owner->name ?? '—' }}</a>
                        <div class="mt-1 flex items-center gap-2 text-[11px] text-ink-400 dark:text-[#9bb0a0]">
                            <span class="truncate">كاميرا {{ $note->camera_number }} · طابق {{ $note->floor_number }}</span>
                            <span class="font-mono tabular-nums shrink-0" dir="ltr">{{ $attachment->created_at->format('Y-m-d') }}</span>
                            @if(auth()->user()->isReportWriter())
                                <a href="{{ route('notes.attachments.download', $attachment) }}" title="تنزيل" aria-label="تنزيل المرفق" class="mr-auto shrink-0 w-7 h-7 rounded-lg bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-ink-400 dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] flex items-center justify-center transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- 2) مدمج: مصغرات كثيفة هادئة --}}
        <div id="gal-compact" class="hidden grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-2.5">
            @foreach($attachments as $attachment)
                @php $note = $attachment->note; @endphp
                @continue(!$note)
                <a href="{{ route('notes.show', $note) }}" title="ملاحظة #{{ $note->id }} — كاميرا {{ $note->camera_number }}"
                   class="group relative aspect-square rounded-xl overflow-hidden bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#e6e9e1] dark:border-[#343a34] hover:border-[#d4ddd3] dark:hover:border-[#404840] hover:shadow-sm transition">
                    @if(str_starts_with($attachment->mime_type, 'image/'))
                        <img src="{{ route('notes.attachments.view', $attachment) }}" alt="" class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-500" loading="lazy">
                    @elseif(str_starts_with($attachment->mime_type, 'video/'))
                        <video src="{{ route('notes.attachments.view', $attachment) }}" class="w-full h-full object-cover" preload="metadata" muted playsinline></video>
                        <span class="absolute inset-0 flex items-center justify-center">
                            <span class="w-7 h-7 rounded-full bg-white/90 dark:bg-black/50 flex items-center justify-center shadow-sm border border-white/40">
                                <svg class="w-3 h-3 text-ink-700 dark:text-white -mr-px" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </span>
                    @elseif(str_starts_with($attachment->mime_type, 'audio/'))
                        <span class="flex items-center justify-center w-full h-full bg-[#eef4f0] dark:bg-[#1e3328] text-ink-300 dark:text-[#4ade80]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                    @else
                        <span class="flex items-center justify-center w-full h-full text-ink-300 dark:text-[#8a9a8a] text-xs">مرفق</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- 3) قائمة هادئة --}}
        <div id="gal-list" class="hidden max-w-3xl mx-auto bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] divide-y divide-[#eceee9] dark:divide-[#2e352e] overflow-hidden">
            @foreach($attachments as $attachment)
                @php $note = $attachment->note; @endphp
                @continue(!$note)
                <div class="flex items-center gap-3 px-3 sm:px-4 py-3 hover:bg-[#f8faf8] dark:hover:bg-[#2e352e] transition group">
                    <a href="{{ route('notes.show', $note) }}" class="flex items-center gap-3 flex-1 min-w-0">
                    <span class="shrink-0 w-12 h-12 rounded-xl overflow-hidden bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#e6e9e1] dark:border-[#343a34]">
                        @if(str_starts_with($attachment->mime_type, 'image/'))
                            <img src="{{ route('notes.attachments.view', $attachment) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                        @elseif(str_starts_with($attachment->mime_type, 'video/'))
                            <video src="{{ route('notes.attachments.view', $attachment) }}" class="w-full h-full object-cover" preload="metadata" muted playsinline></video>
                        @else
                            <span class="flex items-center justify-center w-full h-full text-ink-300 dark:text-[#8a9a8a]">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                        @endif
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[13px] font-bold text-ink-700 dark:text-[#e7ece5] group-hover:text-[#0e6a38] dark:group-hover:text-[#4ade80] transition truncate">ملاحظة #{{ str_pad($note->id, 4, '0', STR_PAD_LEFT) }} <span class="font-medium text-ink-400 dark:text-[#9bb0a0]">·</span> <span class="font-semibold">{{ $note->owner->name ?? '—' }}</span></span>
                        <span class="mt-0.5 block text-[11px] text-ink-400 dark:text-[#9bb0a0] truncate">كاميرا {{ $note->camera_number }} · طابق {{ $note->floor_number }} · <span class="font-mono tabular-nums" dir="ltr">{{ $attachment->created_at->format('Y-m-d H:i') }}</span></span>
                    </span>
                    </a>
                    @if(auth()->user()->isReportWriter())
                        <a href="{{ route('notes.attachments.download', $attachment) }}" title="تنزيل" aria-label="تنزيل المرفق" class="shrink-0 w-9 h-9 rounded-lg bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-ink-400 dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38] flex items-center justify-center transition">
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
    // لايت أولًا + dark: — هادئ في الوضعين
    var onCls = 'bg-[#0e6a38]/10 dark:bg-[#4ade80]/15 text-[#0e6a38] dark:text-[#4ade80]',
        offCls = 'text-ink-400 dark:text-[#8a9a8a] hover:bg-[#f5f7f5] dark:hover:bg-[#2e352e] hover:text-ink-700 dark:hover:text-[#e7ece5]';
    function paint(mode){
        Object.keys(views).forEach(function(k){
            if (!views[k]) return;
            var active = k === mode;
            views[k].classList.toggle('hidden', !active);
            // compact + grid يحتاجان display:grid عند التفعيل فقط
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
    if (fb && fp) fb.addEventListener('click', function(e){
        e.stopPropagation();
        var willOpen = fp.classList.contains('hidden');
        fp.classList.toggle('hidden', !willOpen);
        // الفلاتر grid أيضًا — أعد إظهاره كـ grid عند الفتح
        if (willOpen) { fp.classList.add('grid'); }
        fb.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    // إغلاق عند الضغط خارج اللوحة أو زر Escape
    document.addEventListener('click', function(e){
        if (!fp || fp.classList.contains('hidden')) return;
        if (e.target.closest('#gal-filters') || e.target.closest('#gal-filter-btn')) return;
        fp.classList.add('hidden');
        if (fb) fb.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && fp && !fp.classList.contains('hidden')) {
            fp.classList.add('hidden');
            if (fb) fb.setAttribute('aria-expanded', 'false');
        }
    });
    // فلترة فورية: أي اختيار يُطبَّق مباشرة وتُغلق اللوحة من نفسها
    if (fp) {
        var autoTimer = null;
        fp.querySelectorAll('select').forEach(function(el){
            el.addEventListener('change', function(){
                fp.classList.add('hidden');
                if (fb) fb.setAttribute('aria-expanded', 'false');
                fp.submit();
            });
        });
        var dateEl = fp.querySelector('input[type="date"]');
        if (dateEl) dateEl.addEventListener('change', function(){
            if (autoTimer) clearTimeout(autoTimer);
            autoTimer = setTimeout(function(){
                fp.classList.add('hidden');
                if (fb) fb.setAttribute('aria-expanded', 'false');
                fp.submit();
            }, 350);
        });
    }
})();
</script>
@endsection
