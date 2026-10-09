@extends('layouts.app')

@section('content')
@php
    $isMonitor = auth()->user()->isMonitor();
    $isWriter = auth()->user()->isReportWriter();
    $isGM = auth()->user()->isGeneralManager();
    $userId = auth()->id();

    $currentStatus = request('status');
    $headerCounts = \Illuminate\Support\Facades\Cache::remember('notes-header-counts:'.$userId, 30, function () use ($userId) {
        $counts = \App\Models\Note::query()->whereNull('general_submission_id')
            ->where(function($q) use ($userId){ $q->where('user_id',$userId)->orWhere('status','!=','draft'); })
            ->where('status', '!=', 'draft')
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) as accepted, SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected")
            ->first();
        return [
            'total' => $counts->total ?? 0,
            'pending' => $counts->pending ?? 0,
            'accepted' => $counts->accepted ?? 0,
            'rejected' => $counts->rejected ?? 0,
            'draft' => \App\Models\Note::where('user_id',$userId)->whereNull('general_submission_id')->where('status','draft')->count(),
        ];
    });
    $totalCount = $headerCounts['total'];
    $pendingCount = $headerCounts['pending'];
    $acceptedCount = $headerCounts['accepted'];
    $rejectedCount = $headerCounts['rejected'];
    $draftCount = $headerCounts['draft'];
@endphp


<div class="notes-hero mb-6 sm:mb-7">
    <div class="notes-hero-main">
        <div class="notes-hero-icon" aria-hidden="true">
            <x-phosphor-binoculars-duotone />
        </div>
        <div class="min-w-0">
            <span class="notes-eyebrow">{{ __('ui.notes_eyebrow') }}</span>
            <h1 class="notes-title">{{ __('ui.observations_section') }}</h1>
            <p class="notes-subtitle">{{ __('ui.notes_subtitle') }}</p>
        </div>
    </div>
    @if(($isMonitor || $isWriter) && !$isGM)
        <a href="{{ route('notes.create') }}" class="notes-hero-cta">
            <x-phosphor-plus-duotone />
            {{ __('ui.new_note') }}
        </a>
    @endif
</div>


    @if(isset($observerUser) && $observerUser)
        <div class="mb-5 sm:mb-6 p-3.5 sm:p-4 bg-white dark:bg-[#252b26] border border-[#eceee9] dark:border-[#2e352e] rounded-2xl flex items-center gap-3.5 shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
            <x-user-link :user="$observerUser" size="xl" shape="rounded-xl" skin="soft" wrapper-class="mt-0.5" />
            <div class="min-w-0 flex-1">
                <div class="text-[13.5px] font-semibold text-ink-800 dark:text-[#e7ece5] leading-none">
                    <a href="{{ route('profile.showUser', $observerUser->id) }}" class="hover:text-[#0e6a38] hover:underline transition">{{ $observerUser->localized_name }}</a>
                </div>
            </div>
            <a href="{{ route('notes.index', request()->except(['observer','user','page'])) }}" class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#eceee9] dark:border-[#2e352e] text-ink-600 dark:text-[#9bb0a0] text-xs font-semibold hover:bg-white dark:hover:bg-[#2e352e] transition">{{ __('ui.clear_filter') }} <span class="text-[11px]">✕</span></a>
        </div>
    @endif


<div class="mb-5 sm:mb-7">
    <nav class="notes-tabs" aria-label="{{ __('ui.note_statuses') }}">
        @php
            $tabs = [
                ['key'=>null, 'label'=>__('ui.all'), 'count'=>$totalCount],
                ['key'=>'draft', 'label'=>__('ui.drafts'), 'count'=>$draftCount],
                ['key'=>'pending', 'label'=>__('ui.pending'), 'count'=>$pendingCount],
                ['key'=>'accepted', 'label'=>__('ui.accepted'), 'count'=>$acceptedCount],
                ['key'=>'rejected', 'label'=>__('ui.rejected'), 'count'=>$rejectedCount],
            ];
        @endphp
        @foreach($tabs as $tab)
            @php
                $isActive = $currentStatus === $tab['key'];
                $url = $tab['key'] === null
                    ? route('notes.index')
                    : route('notes.index', array_merge(request()->except('status','page'), ['status' => $tab['key']]));
            @endphp
            <a href="{{ $url }}" data-ajax-tab class="notes-tab {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                <span>{{ $tab['label'] }}</span>
                <span class="notes-tab-count">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </nav>
</div>


<div class="mb-5 sm:mb-6">
    <details class="group">
        <summary class="inline-flex items-center gap-1.5 sm:gap-2.5 px-3 sm:px-4 py-1.5 sm:py-2.5 min-h-[32px] sm:min-h-[44px] touch-manipulation rounded-full bg-white dark:bg-[#252b26] border border-[#eceee9] dark:border-[#2e352e] shadow-sm text-[12px] sm:text-[13px] font-medium text-ink-600 dark:text-[#9bb0a0] hover:text-ink-800 dark:hover:text-[#e7ece5] hover:border-[#e0e7df] dark:hover:border-[#343a34] hover:bg-[#fdfcfa] dark:hover:bg-[#2a302b] cursor-pointer transition list-none select-none [&::-webkit-details-marker]:hidden">
            <span class="w-5 h-5 sm:w-7 sm:h-7 rounded-full bg-[#f5f7f5] dark:bg-[#2a302b] border border-[#eceee9] dark:border-[#2e352e] flex items-center justify-center shrink-0">
                <x-phosphor-sliders-horizontal-duotone class="w-3 h-3 sm:w-3.5 sm:h-3.5" aria-hidden="true" />
            </span>
            @if(request()->hasAny(['date','floor_number','camera_number','observer','sort']))
                <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-[#0e6a38] animate-pulse"></span>
            @endif
            <x-phosphor-caret-down-duotone class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-ink-300 dark:text-[#8a9a8e] transition group-open:rotate-180 ml-0.5 sm:ml-1 shrink-0" aria-hidden="true" />
        </summary>
        <div class="mt-4 p-4 sm:p-5 bg-white dark:bg-[#252b26] rounded-2xl border border-[#eceee9] dark:border-[#2e352e] shadow-[0_8px_30px_rgba(0,0,0,0.06)] touch-manipulation">
            <form method="GET" action="{{ route('notes.index') }}">
                @if($currentStatus)
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold tracking-wide text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.observer_label') }}</label>
                        <select name="observer" class="w-full rounded-xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] py-3 px-3.5 min-h-[48px] touch-manipulation text-[16px] sm:text-[14px] text-ink-800 dark:text-[#e7ece5] focus:border-[#0e6a38] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition">
                            <option value="">{{ __('ui.all') }}</option>
                            @foreach($observers as $obs)
                                @if(is_object($obs) && isset($obs->id))
                                    <option value="{{ $obs->id }}" {{ request('observer') == $obs->id ? 'selected' : '' }}>{{ $obs->localized_name }}</option>
                                @elseif(is_array($obs) && isset($obs['id']))
                                    <option value="{{ $obs['id'] }}" {{ request('observer') == $obs['id'] ? 'selected' : '' }}>{{ $obs['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold tracking-wide text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.sort_by') }}</label>
                        <select name="sort" class="w-full rounded-xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] py-3 px-3.5 min-h-[48px] touch-manipulation text-[16px] sm:text-[14px] text-ink-800 dark:text-[#e7ece5] focus:border-[#0e6a38] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition">
                            <option value="" {{ !request('sort') ? 'selected' : '' }}>{{ __('ui.newest_opt') }}</option>
                            <option value="observer" {{ request('sort')=='observer' ? 'selected' : '' }}>{{ __('ui.observer_az') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold tracking-wide text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.filter_date') }}</label>
                        <input type="date" name="date" value="{{ request('date') }}" class="w-full rounded-xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] py-3 px-3.5 min-h-[48px] touch-manipulation text-[16px] sm:text-[14px] text-ink-800 dark:text-[#e7ece5] focus:border-[#0e6a38] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4 mt-4">
                    <div>
                        <label class="block text-[11px] font-semibold tracking-wide text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.floor') }}</label>
                        <input type="number" name="floor_number" value="{{ request('floor_number') }}" min="1" placeholder="—" inputmode="numeric" class="w-full rounded-xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] py-3 px-3.5 min-h-[48px] touch-manipulation text-[16px] sm:text-[14px] text-ink-800 dark:text-[#e7ece5] placeholder:text-ink-300 dark:placeholder:text-[#6b7a6e] focus:border-[#0e6a38] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold tracking-wide text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.camera') }}</label>
                        <input type="number" name="camera_number" value="{{ request('camera_number') }}" min="1" placeholder="—" inputmode="numeric" class="w-full rounded-xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] py-3 px-3.5 min-h-[48px] touch-manipulation text-[16px] sm:text-[14px] text-ink-800 dark:text-[#e7ece5] placeholder:text-ink-300 dark:placeholder:text-[#6b7a6e] focus:border-[#0e6a38] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition">
                    </div>
                    <div class="flex gap-2.5 items-end">
                        <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] active:bg-[#083d20] text-white font-semibold text-[15px] sm:text-[13.5px] py-3 min-h-[48px] touch-manipulation shadow-[0_2px_10px_rgba(14,106,56,0.18)] active:scale-[0.98] transition">
                            <x-phosphor-magnifying-glass-duotone class="w-4 h-4" aria-hidden="true" />
                            {{ __('ui.apply') }}
                        </button>
                        <a href="{{ route('notes.index', request()->has('status') ? ['status' => request('status')] : []) }}" class="inline-flex items-center justify-center px-4 py-3 min-h-[48px] touch-manipulation rounded-xl border border-[#eceee9] dark:border-[#2e352e] bg-white dark:bg-[#1e2320] text-ink-600 dark:text-[#9bb0a0] font-medium text-[15px] sm:text-[13.5px] hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] active:scale-[0.98] transition">{{ __('ui.clear') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </details>
</div>


<div id="notes-list">
@if($notes->count() === 0)
    <div class="bg-white dark:bg-[#252b26] rounded-2xl border border-[#eceee9] dark:border-[#2e352e] shadow-[0_2px_16px_rgba(0,0,0,0.04)] p-8 sm:p-10 text-center max-w-[560px] mx-auto">
        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] flex items-center justify-center mx-auto shadow-sm">
            <x-phosphor-note-blank-duotone class="w-7 h-7 sm:w-8 sm:h-8 text-ink-300 dark:text-[#6b7a6e]" aria-hidden="true" />
        </div>
        <h3 class="mt-5 text-[15px] sm:text-base font-semibold tracking-tight text-ink-700 dark:text-[#e7ece5]">{{ __('ui.no_notes') }}</h3>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-2.5">
            @if(request()->hasAny(['date','floor_number','camera_number']))
                <a href="{{ route('notes.index', request()->has('status') ? ['status' => request('status')] : []) }}" class="px-4 py-2 rounded-full border border-[#eceee9] dark:border-[#2e352e] bg-white dark:bg-[#1e2320] text-ink-600 dark:text-[#9bb0a0] font-medium text-[13px] hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] transition">{{ __('ui.clear_filters') }}</a>
            @endif
            @if($isMonitor)
                <a href="{{ route('notes.create') }}" class="px-5 py-2.5 rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-semibold text-[13px] shadow-[0_2px_10px_rgba(14,106,56,0.18)] transition">+ {{ __('ui.new_note') }}</a>
            @endif
        </div>
    </div>
@else

    <div class="hidden md:grid md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
        @foreach($notes as $note)
            <div class="group relative bg-white dark:bg-[#252b26] rounded-2xl border border-[#eceee9] dark:border-[#2e352e] shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_28px_rgba(0,0,0,0.07)] hover:border-[#e3e8e1] dark:hover:border-[#33423a] transition-all duration-200 overflow-hidden cursor-pointer" onclick="openModal('detail-{{ $note->id }}')" data-note-card data-i18n-entity="note" data-i18n-id="{{ $note->id }}">
                <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[54px] h-[5px] pointer-events-none">
                    <div class="w-full h-full @if($note->isDraft()) bg-[#c8cfc6] dark:bg-[#4a5e50] @elseif($note->isPending()) bg-[#e8c24a] dark:bg-[#c9a020] @elseif($note->isAccepted()) bg-[#0e6a38] dark:bg-[#1a8a50] @elseif($note->isRejected()) bg-[#c44040] dark:bg-[#a83535] @endif rounded-t-[5px]" style="clip-path: polygon(7% 100%, 93% 100%, 88% 0, 12% 0)"></div>
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-3.5">
                        <div class="flex items-center gap-3 min-w-0">
                            <x-user-link :user="$note->owner" size="xl" shape="rounded-xl" skin="note" :stop="true" />
                            <div class="min-w-0">
                                <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="note-owner-name text-ink-800 dark:text-[#e7ece5] truncate hover:text-[#0e6a38] hover:underline transition block">{{ $note->owner->localized_name }}</a>
                                <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                    @if($note->isDraft())
                                        <span class="note-status-pill is-draft"><span class="dot"></span>{{ __('ui.draft') }}</span>
                                    @elseif($note->isPending())
                                        <span class="note-status-pill is-pending"><span class="dot"></span>{{ __('ui.pending') }}</span>
                                    @elseif($note->isAccepted())
                                        <span class="note-status-pill is-accepted"><span class="dot"></span>{{ __('ui.accepted') }}</span>
                                    @elseif($note->isRejected())
                                        <span class="note-status-pill is-rejected"><span class="dot"></span>{{ __('ui.rejected') }}</span>
                                    @endif
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[11px] font-bold text-ink-500 dark:text-[#8a9a8e]"><span dir="ltr" class="tnum">{{ $note->camera_number }} · {{ $note->floor_number }}</span></span>
                                </div>
                            </div>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[11px] font-medium text-ink-500 dark:text-[#9bb0a0] tabular-nums whitespace-nowrap">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? ' → '.$note->observed_end_at->toTime12() : '' }}</span>
                    </div>
                    <p class="note-desc note-desc-panel text-ink-700 dark:text-[#d5ded6] line-clamp-2 min-h-[48px] mb-4" data-i18n-field="description">{{ \Illuminate\Support\Str::limit(l10n_text('note', $note->id, 'description', $note->description), 120) }}</p>
                    <div class="flex items-center justify-between gap-3 pt-3.5 border-t border-[#f1f3f0] dark:border-[#2a352f]">
                        <span class="text-xs text-ink-400 dark:text-[#8a9a8e] flex items-center gap-1.5"><x-phosphor-clock-duotone class="w-3 h-3 opacity-60" aria-hidden="true" /> {{ $note->created_at->diffForHumans() }}@if($note->attachments->count() > 0) <span class="hidden sm:inline">·</span> <span class="inline-flex items-center gap-1"><x-phosphor-paperclip-duotone class="w-3 h-3" aria-hidden="true" /> {{ __('ui.attachment_count', ['count' => $note->attachments->count()]) }}</span>@endif</span>
                        <div class="flex items-center gap-1.5" onclick="event.stopPropagation()">
                            @if(!$note->isRejected() && $note->owner->whatsapp_number)
                                @php
                                    $statusLabel = status_label($note->status);
                                    $shareText = __('ui.floor').": {$note->floor_number} — ".__('ui.camera').": {$note->camera_number}\n";
                                    $shareText .= __('ui.note_time').": ".$note->observed_at->toDatetime12();
                                    if($note->observed_end_at) $shareText .= ' — '.$note->observed_end_at->toTime12();
                                    $shareText .= "\n".l10n_text('note', $note->id, 'description', $note->description);
                                    $shareText .= "\n".__('ui.status').": ".$statusLabel;
                                    $shareAttachments = $note->attachments->map(fn($a) => ['id'=>$a->id, 'name'=>$a->original_name, 'mime'=>$a->mime_type, 'url'=>'/s/attachments/'.$a->id])->toArray();
                                @endphp
                                <button type="button" onclick='openShareModal(@json($shareText), @json($shareAttachments), "{{ $note->owner->whatsapp_number }}")' class="w-7 h-7 rounded-full bg-[#25D366] hover:bg-[#1da851] text-white shadow-sm flex items-center justify-center transition" title="{{ __('ui.share') }}">
                                    <x-phosphor-whatsapp-logo-duotone class="w-3.5 h-3.5" aria-hidden="true" />
                                </button>
                            @endif
                            @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                                <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-ink-700 dark:text-[#9bb0a0] text-xs font-semibold hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] transition">{{ __('ui.edit_btn') }}</a>
                                <form method="POST" action="{{ route('notes.send', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf<button type="submit" class="px-3.5 py-1.5 rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-xs font-semibold shadow-sm transition">{{ __('ui.send_btn') }}</button></form>
                            @elseif($isMonitor && $note->user_id === $userId && $note->isPending())
                                <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-ink-700 dark:text-[#9bb0a0] text-xs font-semibold hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] transition">{{ __('ui.edit_btn') }}</a>
                            @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                                <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-ink-800 dark:bg-[#2a302b] text-white dark:text-[#e7ece5] text-xs font-semibold hover:bg-ink-900 transition">{{ __('ui.fix_btn') }}</a>
                                <form method="POST" action="{{ route('notes.resend', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf<button type="submit" class="px-3.5 py-1.5 rounded-full bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold shadow-sm transition">{{ __('ui.resend_btn') }}</button></form>
                            @elseif($isWriter && $note->isPending())
                                <form method="POST" action="{{ route('notes.accept', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf<button type="submit" class="px-3.5 py-1.5 rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-xs font-semibold shadow-sm transition">{{ __('ui.accept_btn') }}</button></form>
                                <button type="button" onclick="openModal('reject-{{ $note->id }}')" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-red-200 dark:border-[#3d2626] text-red-600 dark:text-[#f08080] text-xs font-semibold hover:bg-red-50 dark:hover:bg-[#2d1f1f] transition">{{ __('ui.reject_btn') }}</button>
                            @elseif($note->isAccepted())
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#0e6a38] dark:text-[#5cb87a] bg-[#eef4f0] dark:bg-[#1e3328] border border-[#d6e8dc] dark:border-[#24402e] px-2.5 py-1 rounded-full">
                                    <x-phosphor-check-circle-duotone class="w-3 h-3" aria-hidden="true" />
                                    {{ __('ui.approved_badge') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    <div class="md:hidden space-y-3.5">
        @foreach($notes as $note)
            <div class="group relative bg-white dark:bg-[#252b26] rounded-2xl border border-[#eceee9] dark:border-[#2e352e] shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden active:shadow-[0_4px_16px_rgba(0,0,0,0.06)] transition-all duration-200" onclick="openModal('detail-{{ $note->id }}')" role="button" data-note-card data-i18n-entity="note" data-i18n-id="{{ $note->id }}">
                <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[46px] h-[4px] pointer-events-none">
                    <div class="w-full h-full @if($note->isDraft()) bg-[#c8cfc6] dark:bg-[#4a5e50] @elseif($note->isPending()) bg-[#e8c24a] dark:bg-[#c9a020] @elseif($note->isAccepted()) bg-[#0e6a38] dark:bg-[#1a8a50] @elseif($note->isRejected()) bg-[#c44040] dark:bg-[#a83535] @endif rounded-t-[4px]" style="clip-path: polygon(7% 100%, 93% 100%, 88% 0, 12% 0)"></div>
                </div>
                <div class="p-4">
                    <div class="flex gap-3">
                        <x-user-link :user="$note->owner" size="xl" shape="rounded-xl" skin="note" wrapper-class="mt-0.5" :stop="true" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 mb-1.5">
                                <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="text-[12px] font-bold text-ink-700 dark:text-[#d5ded6] truncate hover:text-[#0e6a38] hover:underline transition">{{ $note->owner->localized_name }}</a>
                                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[10px] font-bold text-ink-500 dark:text-[#8a9a8e]" dir="ltr"><span class="tnum">{{ $note->camera_number }} · {{ $note->floor_number }}</span></span>
                            </div>
                            <div class="mb-2.5">
                                @if($note->isDraft())
                                    <span class="note-status-pill is-draft"><span class="dot"></span>{{ __('ui.draft') }}</span>
                                @elseif($note->isPending())
                                    <span class="note-status-pill is-pending"><span class="dot"></span>{{ __('ui.pending') }}</span>
                                @elseif($note->isAccepted())
                                    <span class="note-status-pill is-accepted"><span class="dot"></span>{{ __('ui.accepted') }}</span>
                                @elseif($note->isRejected())
                                    <span class="note-status-pill is-rejected"><span class="dot"></span>{{ __('ui.rejected') }}</span>
                                @endif
                            </div>
                            <p class="note-desc note-desc-panel text-ink-700 dark:text-[#d5ded6] line-clamp-2 mb-3 note-card-body" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;word-break:break-word" data-i18n-field="description">{{ \Illuminate\Support\Str::limit(l10n_text('note', $note->id, 'description', $note->description), 100) }}</p>
                            <div class="flex items-center gap-2 flex-wrap touch-manipulation" onclick="event.stopPropagation()" ontouchstart="event.stopPropagation()">
                                @if(!$note->isRejected() && $note->owner->whatsapp_number)
                                    @php
                                        $statusLabel = status_label($note->status);
                                        $shareText = __('ui.floor').": {$note->floor_number} — ".__('ui.camera').": {$note->camera_number}\n";
                                        $shareText .= __('ui.note_time').": ".$note->observed_at->toDatetime12();
                                        if($note->observed_end_at) $shareText .= ' — '.$note->observed_end_at->toTime12();
                                        $shareText .= "\n".l10n_text('note', $note->id, 'description', $note->description);
                                        $shareText .= "\n".__('ui.status').": ".$statusLabel;
                                        $shareAttachments = $note->attachments->map(fn($a) => ['id'=>$a->id, 'name'=>$a->original_name, 'mime'=>$a->mime_type, 'url'=>'/s/attachments/'.$a->id])->toArray();
                                    @endphp
                                    <button type="button" onclick='openShareModal(@json($shareText), @json($shareAttachments), "{{ $note->owner->whatsapp_number }}")' class="w-10 h-10 rounded-full bg-[#25D366] hover:bg-[#1da851] active:bg-[#168a3a] text-white shadow-sm flex items-center justify-center transition touch-manipulation" title="{{ __('ui.share') }}">
                                        <x-phosphor-whatsapp-logo-duotone class="w-3.5 h-3.5" aria-hidden="true" />
                                    </button>
                                @endif
                                @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2.5 min-h-[42px] touch-manipulation rounded-full bg-white dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-ink-700 dark:text-[#9bb0a0] text-[13px] font-semibold hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] active:scale-[0.97] transition inline-flex items-center justify-center">{{ __('ui.edit_btn') }}</a>
                                    <form method="POST" action="{{ route('notes.send', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf<button type="submit" class="px-5 py-2.5 min-h-[42px] touch-manipulation rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] active:bg-[#083d20] text-white text-[13px] font-semibold shadow-sm active:scale-[0.97] transition">{{ __('ui.send_btn') }}</button></form>
                                @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2.5 min-h-[42px] touch-manipulation rounded-full bg-ink-800 dark:bg-[#2a302b] text-white text-[13px] font-semibold hover:bg-ink-900 active:scale-[0.97] transition inline-flex items-center justify-center">{{ __('ui.fix_btn') }}</a>
                                    <form method="POST" action="{{ route('notes.resend', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf<button type="submit" class="px-5 py-2.5 min-h-[42px] touch-manipulation rounded-full bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white text-[13px] font-semibold shadow-sm active:scale-[0.97] transition">{{ __('ui.resend_btn') }}</button></form>
                                @elseif($isWriter && $note->isPending())
                                    <form method="POST" action="{{ route('notes.accept', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf<button type="submit" class="px-5 py-2.5 min-h-[42px] touch-manipulation rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] active:bg-[#083d20] text-white text-[13px] font-semibold shadow-sm active:scale-[0.97] transition">{{ __('ui.accept_btn') }}</button></form>
                                    <button type="button" onclick="openModal('reject-{{ $note->id }}')" class="px-4 py-2.5 min-h-[42px] touch-manipulation rounded-full bg-white dark:bg-[#1e2320] border border-red-200 dark:border-[#3d2626] text-red-600 dark:text-[#f08080] text-[13px] font-semibold hover:bg-red-50 dark:hover:bg-[#2d1f1f] active:scale-[0.97] transition">{{ __('ui.reject_btn') }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    @if($notes->hasPages())
        <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-[#252b26] border border-[#eceee9] dark:border-[#2e352e] rounded-2xl px-4 sm:px-5 py-3.5 shadow-sm">
            <div class="text-[13px] text-ink-500 dark:text-[#9bb0a0] inline-flex items-center gap-1.5 flex-wrap font-medium">
                <span>{{ __('ui.showing') }}</span> <span dir="ltr" class="font-semibold text-ink-700 dark:text-[#e7ece5] tabular-nums bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] px-2 py-0.5 rounded-full text-xs">{{ $notes->firstItem() ?? 0 }}–{{ $notes->lastItem() ?? 0 }}</span> <span>{{ __('ui.of') }}</span> <span dir="ltr" class="font-semibold text-ink-700 dark:text-[#e7ece5] tabular-nums">{{ $notes->total() }}</span>
            </div>
            <div class="w-full sm:w-auto flex justify-center">{{ $notes->withQueryString()->links() }}</div>
        </div>
    @endif
@endif
</div>


@foreach($notes as $note)
    @php
        $notePrintData = [
            'id' => $note->id,
            'floor_number' => $note->floor_number,
            'camera_number' => $note->camera_number,
            'observed_date' => $note->observed_at->format('Y-m-d'),
            'observed_time_start' => $note->observed_at->toTime12(),
            'observed_time_end' => $note->observed_end_at ? $note->observed_end_at->toTime12() : '—',
            'description' => l10n_text('note', $note->id, 'description', $note->description),
            'status' => $note->status,
            'status_label' => status_label($note->status),
            'owner_name' => $note->owner->localized_name,
            'attachments' => $note->attachments->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'mime' => $a->mime_type,
                'file_size' => $a->file_size,
                'url' => route('notes.attachments.view', $a)
            ])->toArray(),
        ];
    @endphp
    <div id="detail-{{ $note->id }}" data-modal class="hidden fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4" data-i18n-entity="note" data-i18n-id="{{ $note->id }}">
        <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" onclick="closeModal('detail-{{ $note->id }}')"></div>
        <div class="relative bg-white rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] sm:max-h-[90vh] flex flex-col overflow-hidden mx-1 sm:mx-auto">

            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-[#e6e9e1] flex items-start justify-between gap-3 sm:gap-4 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <x-user-link :user="$note->owner" size="xl" shape="rounded-xl" skin="soft" img-extra-class="border border-[#e6e9e1] shadow-sm" :stop="true" />
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base font-bold text-ink-800">{{ __('ui.camera_floor_format', ['camera' => $note->camera_number, 'floor' => $note->floor_number]) }}</h2>
                            <div class="mt-1">@include('notes.partials.translation_status', ['note' => $note])</div>
                            @if($note->isDraft())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-ink-100 text-ink-600"><span data-status="draft">{{ __('ui.draft') }}</span></span>
                            @elseif($note->isPending())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><span data-status="pending">{{ __('ui.pending') }}</span></span>
                            @elseif($note->isAccepted())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-sage-50 text-sage-700 border border-sage-200"><span data-status="accepted">{{ __('ui.accepted') }}</span></span>
                            @elseif($note->isRejected())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 border border-red-200"><span data-status="rejected">{{ __('ui.rejected') }}</span></span>
                            @endif
                        </div>
                        <div class="text-xs text-[#737373] mt-0.5"><a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="hover:text-[#0e6a38] hover:underline transition">{{ $note->owner->localized_name }}</a> — {{ __('ui.created_label') }} {{ $note->created_at->toDatetime12() }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('notes.show', $note) }}" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-400 hover:text-ink-700 transition" title="{{ __('ui.view_details_aria') }}">
                        <x-phosphor-arrow-square-out-duotone class="w-4 h-4" aria-hidden="true" />
                    </a>
                    <button type="button" onclick="closeModal('detail-{{ $note->id }}')" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-300 hover:text-ink-700 transition shrink-0" aria-label="{{ __('ui.close') }}">
                        <x-phosphor-x-duotone class="w-5 h-5" aria-hidden="true" />
                    </button>
                </div>
            </div>


            <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-4 sm:space-y-5">

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.camera') }}</div>
                        <div class="text-lg font-bold text-ink-800">{{ $note->camera_number }}</div>
                    </div>
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.floor') }}</div>
                        <div class="text-lg font-bold text-ink-800">{{ $note->floor_number }}</div>
                    </div>
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.time_label') }}</div>
                        <div class="text-sm font-bold text-ink-800">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? ' — '.$note->observed_end_at->toTime12() : '' }}</div>
                        <div class="text-[11px] text-[#737373]">{{ $note->observed_at->format('Y-m-d') }}</div>
                    </div>
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.attachments') }}</div>
                        <div class="text-lg font-bold text-ink-800">{{ $note->attachments->count() }}</div>
                        <div class="text-[11px] text-[#737373]">{{ __('ui.file_label') }}</div>
                    </div>
                </div>


                <div class="flex items-center gap-3 p-3 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1]">
                    <x-user-link :user="$note->owner" size="lg" shape="rounded-lg" skin="soft" img-extra-class="border border-[#e6e9e1] shadow-sm" :stop="true" />
                    <div>
                        <div class="text-[11px] font-bold text-ink-300">{{ __('ui.observer') }}</div>
                        <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="text-sm font-bold text-ink-800 hover:text-[#0e6a38] transition">{{ $note->owner->localized_name }}</a>
                    </div>
                    <div class="mr-auto text-left">
                        <div class="text-[11px] font-bold text-ink-300">{{ __('ui.created_at') }}</div>
                        <div class="text-xs font-medium text-[#525252]">{{ $note->created_at->toDatetime12() }}</div>
                    </div>
                </div>


                <div>
                    <h3 class="text-sm font-bold text-ink-700 mb-2 flex items-center gap-2">
                        <x-phosphor-note-pencil-duotone class="w-4 h-4 text-[#0e6a38]" aria-hidden="true" />
                        {{ __('ui.description') }}
                    </h3>
                    <div class="note-modal-desc p-4 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] text-ink-700 whitespace-pre-wrap break-words" data-i18n-field="description">{{ l10n_text('note', $note->id, 'description', $note->description) }}</div>
                </div>


                @if($note->isRejected() && $note->rejection_reason)
                    <div class="p-4 rounded-xl bg-red-50 border border-red-200">
                        <h3 class="text-sm font-bold text-red-700 mb-2 flex items-center gap-2">
                            <x-phosphor-warning-duotone class="w-4 h-4" aria-hidden="true" />
                            {{ __('ui.reject_reason') }}
                        </h3>
                        <p class="text-sm leading-7 text-red-600" data-i18n-field="rejection_reason">{{ l10n_text('note', $note->id, 'rejection_reason', $note->rejection_reason) }}</p>
                        @if($note->processor)
                            <div class="mt-2 text-xs font-bold text-red-500">{{ __('ui.by_user') }} <a href="{{ route('profile.showUser', $note->processor->id) }}" class="hover:underline transition">{{ $note->processor->localized_name }}</a> — {{ $note->processed_at?->toDatetime12() }}</div>
                        @endif
                    </div>
                @endif


                @if($note->attachments->count() > 0)
                    <div>
                        <h3 class="text-sm font-bold text-ink-700 mb-2 flex items-center gap-2">
                            <x-phosphor-paperclip-duotone class="w-4 h-4 text-[#0e6a38]" aria-hidden="true" />
                            {{ __('ui.attachments_section', ['count' => $note->attachments->count()]) }}
                        </h3>
                        <div class="space-y-2">
                            @foreach($note->attachments as $attachment)
                                <div class="flex items-center gap-3 p-3 rounded-xl border border-[#e6e9e1] hover:border-[#cde7d6] hover:bg-[#eef4f0]/30 transition group">
                                    <div class="w-9 h-9 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center shrink-0">
                                        @if(str_contains($attachment->mime_type, 'video'))
                                            <x-phosphor-video-camera-duotone class="w-4 h-4 text-[#737373]" aria-hidden="true" />
                                        @elseif(str_contains($attachment->mime_type, 'audio'))
                                            <x-phosphor-microphone-duotone class="w-4 h-4 text-red-500" aria-hidden="true" />
                                        @else
                                            <x-phosphor-image-duotone class="w-4 h-4 text-[#0e6a38]" aria-hidden="true" />
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-bold text-ink-700 truncate">{{ $attachment->original_name }}</div>
                                        <div class="text-xs text-[#737373]">{{ $attachment->mime_type }} — {{ number_format($attachment->file_size/1024, 1) }} KB</div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button type="button" onclick="openAttachmentView('{{ route('notes.attachments.view', $attachment) }}', '{{ $attachment->mime_type }}', '{{ addslashes($attachment->original_name) }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 text-xs font-bold hover:bg-[#f5f7f5] hover:border-[#d4ddd3] transition">
                                            <x-phosphor-eye-duotone class="w-3.5 h-3.5" aria-hidden="true" />
                                            {{ __('ui.view_attachment') }}
                                        </button>
                                        @if(auth()->user()->isReportWriter() || auth()->user()->isGeneralManager())
                                            <a href="{{ route('notes.attachments.download', $attachment) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition" title="{{ __('ui.download_manager_only') }}">
                                                <x-phosphor-download-simple-duotone class="w-3.5 h-3.5" aria-hidden="true" />
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif


                @if($note->processor)
                    <div class="p-3 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] flex items-center gap-3">
                        <x-user-link :user="$note->processor" size="md" shape="rounded-lg" skin="gray" weight="font-bold" :stop="true" />
                        <div>
                            <div class="text-[11px] font-bold text-ink-300">{{ $note->isAccepted() ? __('ui.approved_by') : __('ui.rejected_by') }}</div>
                            <div class="text-sm font-bold text-ink-700"><a href="{{ route('profile.showUser', $note->processor->id) }}" onclick="event.stopPropagation()" class="hover:text-[#0e6a38] hover:underline transition">{{ $note->processor->localized_name }}</a> — {{ $note->processed_at?->toDatetime12() }}</div>
                        </div>
                    </div>
                @endif
            </div>


            <div class="px-6 py-4 border-t border-[#e6e9e1] flex flex-wrap items-center gap-2 shrink-0 bg-[#f5f7f5]">
                @if(!$note->isRejected() && $note->owner->whatsapp_number)
                    @php
                        $statusLabel = status_label($note->status);
                        $shareText = "";
                        $shareText .= __('ui.floor').": {$note->floor_number} — ".__('ui.camera').": {$note->camera_number}\n";
                        $shareText .= __('ui.note_time').": ".$note->observed_at->toDatetime12();
                        if($note->observed_end_at) $shareText .= ' — '.$note->observed_end_at->toTime12();
                        $shareText .= "\n".l10n_text('note', $note->id, 'description', $note->description);
                        $shareText .= "\n".__('ui.status').": ".$statusLabel;
                        $shareAttachments = $note->attachments->map(fn($a) => ['id'=>$a->id, 'name'=>$a->original_name, 'mime'=>$a->mime_type, 'url'=>'/s/attachments/'.$a->id])->toArray();
                    @endphp
                    <button type="button" onclick='openShareModal(@json($shareText), @json($shareAttachments), "{{ $note->owner->whatsapp_number }}")' class="px-4 py-2 rounded-lg bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition inline-flex items-center gap-1.5">
                        <x-phosphor-whatsapp-logo-duotone class="w-4 h-4" aria-hidden="true" />
                        {{ __('ui.share_whatsapp') }}
                    </button>
                @endif
                <button type="button" onclick='openPrintModal(@json($notePrintData))' class="px-3.5 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition inline-flex items-center gap-1.5 shadow-sm" title="{{ __('ui.print') }}">
                    <x-phosphor-printer-duotone class="w-4 h-4" aria-hidden="true" />
                    <span>{{ __('ui.print') }}</span>
                </button>
                @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">{{ __('ui.edit_btn') }}</a>
                    <form method="POST" action="{{ route('notes.send', $note) }}" class="inline">@csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">{{ __('ui.send_btn') }}</button>
                    </form>
                    <form method="POST" action="{{ route('notes.destroy', $note) }}" class="inline mr-auto" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 text-sm font-bold hover:bg-red-50 transition">{{ __('ui.delete') }}</button>
                    </form>
                @elseif($isMonitor && $note->user_id === $userId && $note->isPending())
                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">{{ __('ui.edit_btn') }}</a>
                    <span class="mr-auto text-xs font-bold text-amber-600 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>{{ __('ui.awaiting_decision') }}</span>
                @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg bg-ink-800 text-white text-sm font-bold hover:bg-ink-900 transition">{{ __('ui.fix_btn') }}</a>
                    <form method="POST" action="{{ route('notes.resend', $note) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition">{{ __('ui.resend_btn') }}</button>
                    </form>
                @elseif($isWriter && $note->isPending())
                    <form method="POST" action="{{ route('notes.accept', $note) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">{{ __('ui.accept_btn') }}</button>
                    </form>
                    <button type="button" onclick="closeModal('detail-{{ $note->id }}');setTimeout(()=>openModal('reject-{{ $note->id }}'),200)" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">{{ __('ui.reject_btn') }}</button>
                @elseif($note->isAccepted())
                    <span class="inline-flex items-center gap-1.5 text-sm font-bold text-[#0e6a38]">
                        <x-phosphor-check-circle-duotone class="w-4 h-4" aria-hidden="true" />
                        {{ __('ui.approved_badge') }}
                    </span>
                @endif
                <button type="button" onclick="closeModal('detail-{{ $note->id }}')" class="mr-auto px-4 py-2 rounded-lg border border-[#e6e9e1] text-[#737373] text-sm font-medium hover:bg-white transition">{{ __('ui.close') }}</button>
            </div>
        </div>
    </div>
@endforeach


@foreach($notes as $note)
    @if($isWriter && $note->isPending())
        <div id="reject-{{ $note->id }}" data-modal class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" onclick="closeModal('reject-{{ $note->id }}')"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e6e9e1]">
                    <h3 class="text-base font-bold text-ink-800">{{ __('ui.reject_note_title') }}</h3>
                </div>
                <form method="POST" action="{{ route('notes.reject', $note) }}" class="p-6 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-bold text-ink-700 mb-2">{{ __('ui.reject_reason') }} <span class="text-red-500">*</span></label>
                        <textarea name="rejection_reason" rows="4" required maxlength="2000" class="w-full rounded-xl border border-[#e6e9e1] bg-white py-3 px-4 text-sm leading-6 text-ink-800 placeholder:text-ink-300 focus:border-red-400 focus:ring-2 focus:ring-red-400/10 outline-none transition resize-none" placeholder="{{ __('ui.reject_reason_ph') }}"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('reject-{{ $note->id }}')" class="px-4 py-2.5 rounded-xl border border-[#e6e9e1] text-[#525252] font-bold text-sm hover:bg-[#f5f7f5] transition">{{ __('ui.cancel') }}</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white font-bold text-sm transition">{{ __('ui.confirm_reject') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach


<div id="attachment-view-modal" data-modal class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" onclick="closeModal('attachment-view-modal')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-surface-300 flex items-center justify-between gap-3 shrink-0">
            <h3 id="attachment-view-title" class="text-sm font-bold text-ink-800 truncate"></h3>
            <button type="button" onclick="closeModal('attachment-view-modal')" class="w-8 h-8 rounded-lg hover:bg-surface-100 flex items-center justify-center text-ink-400 hover:text-ink-700 transition">
                <x-phosphor-x-duotone class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>
        <div class="flex-1 min-h-0 bg-ink-900 flex items-center justify-center p-4 overflow-auto">
            <img id="attachment-view-image" class="hidden max-w-full max-h-[70vh] rounded-lg object-contain" oncontextmenu="return false;" draggable="false" alt="{{ __('ui.preview_image_alt') }}">
            <video id="attachment-view-video" class="hidden max-w-full max-h-[70vh] rounded-lg" controls controlsList="nodownload" oncontextmenu="return false;" disablePictureInPicture></video>
            <audio id="attachment-view-audio" class="hidden w-full max-w-md" controls controlsList="nodownload" oncontextmenu="return false;"></audio>
            <div id="attachment-view-fallback" class="hidden text-center text-white/70 text-sm">{{ __('ui.preview_unsupported') }}</div>
        </div>
        <div class="px-4 py-3 border-t border-surface-300 bg-surface-50 flex items-center justify-between gap-3 shrink-0">
            <p class="text-xs text-ink-400">{{ __('ui.view_only') }}</p>
            <button type="button" onclick="closeModal('attachment-view-modal')" class="px-4 py-2 rounded-lg bg-white border border-surface-300 text-ink-600 text-sm font-bold hover:bg-surface-100 transition">{{ __('ui.close') }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openAttachmentView(url, mime, name){
    const modal = document.getElementById('attachment-view-modal');
    const img = document.getElementById('attachment-view-image');
    const video = document.getElementById('attachment-view-video');
    const audio = document.getElementById('attachment-view-audio');
    const fallback = document.getElementById('attachment-view-fallback');
    const title = document.getElementById('attachment-view-title');
    if(title) title.textContent = name;
    img.classList.add('hidden'); img.src='';
    video.classList.add('hidden'); video.pause(); video.src=''; video.load();
    audio.classList.add('hidden'); audio.pause(); audio.src=''; audio.load();
    fallback.classList.add('hidden');
    if(mime.startsWith('image/')){
        img.src = url;
        img.classList.remove('hidden');
    } else if(mime.startsWith('video/')){
        video.src = url;
        video.classList.remove('hidden');
        video.load();
    } else if(mime.startsWith('audio/')){
        audio.src = url;
        audio.classList.remove('hidden');
        audio.load();
    } else {
        fallback.classList.remove('hidden');
        fallback.textContent = @json(__('ui.preview_unsupported')) + ' — ' + mime;
    }
    modal.classList.remove('hidden');
    document.body.style.overflow='hidden';
    img.oncontextmenu = () => false;
    video.oncontextmenu = () => false;
}

document.addEventListener('contextmenu', e=>{
    const modal = document.getElementById('attachment-view-modal');
    if(modal && !modal.classList.contains('hidden') && (e.target.tagName==='IMG' || e.target.tagName==='VIDEO')){
        e.preventDefault();
    }
});


const SHARE_T = {
    share: @json(__('ui.share')),
    close: @json(__('ui.close')),
    preparing: @json(__('ui.preparing')),
    copying: @json(__('ui.copying')),
    pickContent: @json(__('ui.share_pick_content')),
    shareFailed: @json(__('ui.share_failed')),
    audio: @json(__('ui.audio_clip')),
    video: @json(__('ui.video_clip')),
    image: @json(__('ui.image_single')),
    other: @json(__('ui.attachment_alt')),
    attachedFiles: @json(__('ui.attached_files')),
    openWa: @json(__('ui.open_whatsapp_text')),
    textLabel: @json(__('ui.text_label')),
    copyText: @json(__('ui.copy_text')),
    watchLinks: @json(__('ui.watch_links')),
    copyLink: @json(__('ui.copy_link')),
    copied: @json(__('ui.copied')),
    copyFailed: @json(__('ui.copy_failed')),
    copy: @json(__('ui.copy')),
    of: @json(__('ui.of')),
};
function openShareModal(text, attachments, phone) {
    const modal = document.getElementById('share-modal');
    const textEl = document.getElementById('share-text');
    const listEl = document.getElementById('share-attachments');
    const sectionEl = document.getElementById('share-attachments-section');
    textEl.value = text;
    if (!attachments || attachments.length === 0) {
        sectionEl.style.display = 'none';
        listEl.innerHTML = '';
    } else {
        sectionEl.style.display = '';
        let html = '';
        attachments.forEach(a => {
            const isImage = a.mime.includes('image');
            const isVideo = a.mime.includes('video');
            const icon = isVideo
                ? '<x-phosphor-video-camera-duotone class="w-4 h-4 text-red-400 shrink-0" />'
                : '<x-phosphor-image-duotone class="w-4 h-4 text-sage-600 shrink-0" />';
            html += '<label class="flex items-center gap-3 p-2.5 rounded-xl border border-[#e6e9e1] hover:bg-[#f5f7f5] cursor-pointer transition">' +
                '<input type="checkbox" class="share-file-cb rounded border-[#c2cbc1] text-[#0e6a38] focus:ring-[#0e6a38]/20 w-4 h-4" data-url="' + a.url + '" data-name="' + a.name + '" data-mime="' + a.mime + '" checked onchange="updateShareSelectedCount()">' +
                '<span class="shrink-0">' + icon + '</span>' +
                '<span class="text-sm text-ink-700 truncate">' + a.name + '</span>' +
                '</label>';
        });
        listEl.innerHTML = html;
    }
    modal.dataset.phone = phone;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeShareModal() {
    document.getElementById('share-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
function updateShareSelectedCount() {
    const cbs = document.querySelectorAll('.share-file-cb:checked');
    const allCbs = document.querySelectorAll('.share-file-cb');
    const countEl = document.getElementById('share-selected-count');
    const selectAllEl = document.getElementById('share-select-all');
    if (countEl) countEl.textContent = cbs.length + ' ' + SHARE_T.of + ' ' + allCbs.length;
    if (selectAllEl) selectAllEl.checked = allCbs.length > 0 && cbs.length === allCbs.length;
}
function toggleShareSelectAll(cb) {
    document.querySelectorAll('.share-file-cb').forEach(c => c.checked = cb.checked);
    updateShareSelectedCount();
}
async function doShare() {
    const btn = document.getElementById('share-send-btn');
    const btnText = document.getElementById('share-send-text');
    const btnLoader = document.getElementById('share-send-loader');
    const textEl = document.getElementById('share-text');
    const text = textEl ? textEl.value.trim() : '';
    const hasWebShare = !!navigator.share;
    btn.disabled = true;
    btnText.textContent = hasWebShare ? SHARE_T.preparing : SHARE_T.copying;
    btnLoader.classList.remove('hidden');
    const finish = () => { btn.disabled = false; btnText.textContent = SHARE_T.share; btnLoader.classList.add('hidden'); };
    const cbs = document.querySelectorAll('.share-file-cb:checked');
    const items = [];
    for (const cb of cbs) {
        try {
            const res = await fetch(cb.dataset.url);
            if (!res.ok) continue;
            const blob = await res.blob();
            items.push({file: new File([blob], cb.dataset.name, {type: cb.dataset.mime}), url: cb.dataset.url, name: cb.dataset.name});
        } catch(e) {}
    }
    if (!text && items.length === 0) {
        finish();
        window.toast(SHARE_T.pickContent);
        return;
    }
    if (hasWebShare) {
        const shareData = {};
        if (text) shareData.text = text;
        const webFiles = items.map(i => i.file);
        const canShareFiles = webFiles.length > 0 && !!navigator.canShare && navigator.canShare({files: webFiles});
        if (canShareFiles) shareData.files = webFiles;
        if (webFiles.length > 0 && !canShareFiles) {
            try { if (text) await navigator.share({text}); } catch(e) {}
            finish();
            showShareFallback(items, text);
            return;
        }
        try {
            await navigator.share(shareData);
            closeShareModal();
        } catch(e) {
            if (e.name !== 'AbortError') window.toast(SHARE_T.shareFailed);
        } finally {
            finish();
        }
    } else {
        const phone = document.getElementById('share-modal').dataset.phone || '';
        const cleanPhone = phone.replace(/^\+/,'');
        var counters={img:0,vid:0,aud:0,other:0};
        var shortName=function(it){
            var m=it.mime||'';
            if(m.indexOf('audio/')===0) return SHARE_T.audio + ' ' + (++counters.aud);
            if(m.indexOf('video/')===0) return SHARE_T.video + ' ' + (++counters.vid);
            if(m.indexOf('image/')===0) return SHARE_T.image + ' ' + (++counters.img);
            return SHARE_T.other + ' ' + (++counters.other);
        };
        const filesText = items.length ? '\n\n*' + SHARE_T.attachedFiles + ' ('+items.length+')*\n' + items.map(function(it){ return shortName(it)+':\n\u200E'+shareBase()+it.url; }).join('\n\n') : '';
        const fullText = text + filesText;
        const waUrl = 'https://wa.me/?text=' + encodeURIComponent(fullText);
        if (waUrl) {
            window.open(waUrl, '_blank');
            finish();
            if (items.length) setTimeout(function(){ showShareFallback(items, text); }, 800);
            else closeShareModal();
        } else {
            try { if (fullText) await navigator.clipboard.writeText(fullText); } catch(e){}
            finish();
            showShareFallback(items, text);
        }
    }
}
function escShareHtml(s) {
    return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showShareFallback(items, text) {
    items = items || [];
    text = text || '';
    const modal = document.getElementById('share-modal');
    const body = modal.querySelector('.flex-1');
    if (!body) return;
    const phone = modal.dataset.phone || '';
    const cleanPhone = phone.replace(/^\+/,'');
    const waUrl = text ? 'https://wa.me/?text=' + encodeURIComponent(text) : null;
    let html = '<div class="p-4 bg-[#f5f7f5] rounded-xl border border-[#e6e9e1] space-y-3">';
    if (waUrl) {
        html += '<a href="' + waUrl + '" target="_blank" rel="noopener" class="block w-full text-center px-4 py-3 rounded-xl bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition">' + SHARE_T.openWa + '</a>';
    }
    if (text) {
        html += '<div class="space-y-2"><label class="block text-xs font-bold text-ink-500">' + SHARE_T.textLabel + '</label>';
        html += '<textarea id="share-fallback-text" readonly class="w-full h-24 p-3 rounded-lg border border-[#e6e9e1] text-sm text-ink-700 bg-white" onclick="this.select()">' + escShareHtml(text) + '</textarea>';
        html += '<button onclick="copyShareFallbackText()" id="share-fallback-copy" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">' + SHARE_T.copyText + '</button>';
        html += '</div>';
    }
    if (items.length > 0) {
        html += '<div class="space-y-2 mt-3"><label class="block text-xs font-bold text-ink-500">' + SHARE_T.watchLinks + ' (' + items.length + ')</label>';
        items.forEach(it => {
            var absUrl = shareBase() + it.url;
            html += '<div class="p-2.5 rounded-xl border border-[#e6e9e1] bg-white space-y-2">';
            html += '<div class="text-sm text-ink-700 font-bold truncate"><x-phosphor-paperclip-duotone class="w-3.5 h-3.5 inline-block -mt-0.5 ml-1" />' + escShareHtml(it.name) + '</div>';
            html += '<div class="flex items-center gap-2"><input readonly onclick="this.select()" value="' + escShareHtml(absUrl) + '" dir="ltr" class="flex-1 min-w-0 text-xs font-mono text-ink-500 bg-[#f5f7f5] border border-[#e6e9e1] rounded-lg px-2 py-1.5">';
            html += '<button data-url="' + escShareHtml(absUrl) + '" onclick="copyShareLink(this)" class="shrink-0 px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition">' + SHARE_T.copyLink + '</button></div></div>';
        });
        html += '</div>';
    }
    html += '</div><div class="mt-4 text-center"><button onclick="closeShareModal()" class="px-6 py-2.5 rounded-lg bg-white border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-[#f5f7f5] transition">' + SHARE_T.close + '</button></div>';
    body.innerHTML = html;
}
function copyShareFallbackText() {
    const ta = document.getElementById('share-fallback-text');
    if (!ta) return;
    copyShareValue(ta.value, 'share-fallback-copy', SHARE_T.copyText);
}
function copyShareLink(btn) {
    if (!btn || !btn.dataset.url) return;
    copyShareValue(btn.dataset.url, null, null, btn);
}
function copyShareValue(value, btnId, btnIdleText, btnEl) {
    const done = (ok) => {
        const b = btnEl || (btnId ? document.getElementById(btnId) : null);
        if (!b) return;
        const orig = btnIdleText || b.textContent;
        b.textContent = ok ? SHARE_T.copied : SHARE_T.copyFailed;
        setTimeout(() => { b.textContent = orig; }, 1500);
    };
    window.copyTextToClipboard(value).then(done);
}
function copyShareText() {
    const text = document.getElementById('share-text');
    window.copyTextToClipboard(text.value).then((ok) => {
        const btn = document.getElementById('share-copy-btn');
        if (btn) btn.textContent = ok ? SHARE_T.copied : SHARE_T.copyFailed;
        setTimeout(() => { if (btn) btn.textContent = SHARE_T.copy; }, 1500);
    });
}

</script>


<div id="share-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" onclick="closeShareModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[85vh] flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-[#e6e9e1] flex items-center justify-between shrink-0">
            <h3 class="text-base font-bold text-ink-800">{{ __('ui.share') }}</h3>
            <button onclick="closeShareModal()" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-300 hover:text-ink-700 transition" aria-label="{{ __('ui.close') }}">
                <x-phosphor-x-duotone class="w-5 h-5" aria-hidden="true" />
            </button>
        </div>
        <div class="p-5 overflow-y-auto flex-1 space-y-4">
            <div>
                <label for="share-text" class="block text-xs font-bold text-ink-500 mb-1.5">{{ __('ui.msg_label') }}</label>
                <textarea id="share-text" rows="4" class="w-full rounded-xl border border-[#e6e9e1] bg-[#f5f7f5] py-3 px-4 text-sm leading-7 text-ink-800 resize-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition" placeholder="{{ __('ui.msg_placeholder') }}"></textarea>
            </div>
            <div id="share-attachments-section">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-ink-500">{{ __('ui.media_label') }}</label>
                    <div class="flex items-center gap-2">
                        <span id="share-selected-count" class="text-xs text-ink-400"></span>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" id="share-select-all" onchange="toggleShareSelectAll(this)" class="rounded border-[#c2cbc1] text-[#0e6a38] focus:ring-[#0e6a38]/20 w-3.5 h-3.5">
                            <span class="text-xs text-ink-500">{{ __('ui.all') }}</span>
                        </label>
                    </div>
                </div>
                <div id="share-attachments" class="space-y-2"></div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-[#e6e9e1] flex items-center gap-2 shrink-0 bg-[#f5f7f5]">
            <button id="share-copy-btn" onclick="copyShareText()" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">{{ __('ui.copy') }}</button>
            <button id="share-send-btn" onclick="doShare()" class="flex-1 px-4 py-2.5 rounded-xl bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] active:scale-[0.98] transition flex items-center justify-center gap-2">
                <svg id="share-send-loader" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span id="share-send-text">{{ __('ui.share') }}</span>
            </button>
        </div>
    </div>
</div>

<script>

document.querySelectorAll('form[data-ajax]').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>'; }
        try {
            const fd = new FormData(this);
            const res = await fetch(this.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: fd
            });
            const data = await res.json();
            if (res.ok && data.success) {

                if(window.fetchNotifications) window.fetchNotifications(false);

                const newStatus = data.status || (data.data && data.data.status);
                const noteId = this.dataset.noteId;

                const actionUrl = this.action || '';
                const actionKind = /\/resend\/?$/.test(actionUrl) ? 'resend' : (/\/accept\/?$/.test(actionUrl) ? 'accept' : 'send');
                const successMsg = actionKind === 'resend' ? @json(__('ui.note_resent_success')) : (actionKind === 'accept' ? @json(__('ui.note_accepted_success')) : @json(__('ui.note_sent_success')));
                const movedTpl = @json(__('ui.note_moved_to_tab', ['tab' => ':tab']));
                const tabLabel = { draft:@json(__('ui.drafts')), pending:@json(__('ui.pending')), accepted:@json(__('ui.accepted')), rejected:@json(__('ui.rejected')) };
                const bumpTabCount = function(key, delta){
                    document.querySelectorAll('a[data-ajax-tab]').forEach(function(a){
                        var href = a.getAttribute('href') || '';
                        var m = href.match(/[?&]status=([a-z]+)/);
                        var k = m ? m[1] : null;
                        if (k === key) {
                            var spans = a.querySelectorAll('span');
                            var el = spans[spans.length - 1];
                            if (!el) return;
                            var n = parseInt(String(el.textContent || '').replace(/[^0-9]/g, ''), 10);
                            if (!isNaN(n)) el.textContent = String(Math.max(0, n + delta));
                        }
                    });
                };
                const applyCounts = function(){
                    if (actionKind === 'send') { bumpTabCount('draft', -1); bumpTabCount('pending', 1); bumpTabCount(null, 1); }
                    else if (actionKind === 'resend') { bumpTabCount('rejected', -1); bumpTabCount('pending', 1); }
                    else if (actionKind === 'accept') { bumpTabCount('pending', -1); bumpTabCount('accepted', 1); }
                };

                let card = this.closest('[data-note-card]');
                if (!card && noteId) {
                    const candidates = Array.from(document.querySelectorAll('[data-note-id="'+noteId+'"]'));
                    card = candidates.find(el => el !== this && (el.querySelector('.note-badge') || el.querySelector('.note-actions'))) || null;
                }
                // البطاقة لم تعد تنتمي للتبويب الحالي (مثال: قبول ملاحظة من تبويب قيد المراجعة)
                // — نزيلها من القائمة ونوجّه المستخدم لمكانها الجديد بدل إبقائها بشارة
                // مخالفة للفلتر ثم اختفائها عند التحديث.
                var currentFilter = null;
                try { currentFilter = new URLSearchParams(window.location.search).get('status'); } catch(_) {}
                var stillBelongs = !currentFilter || !newStatus || currentFilter === newStatus;
                if (card && newStatus && !stillBelongs) {
                    applyCounts();
                    var movedLabel = tabLabel[newStatus] || newStatus;
                    window.toast(successMsg + ' — ' + movedTpl.split(':tab').join(movedLabel));
                    card.style.transition = 'opacity .25s, transform .25s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    setTimeout(function(){
                        card.remove();
                        if (!document.querySelector('#notes-list [data-note-card]')) location.reload();
                    }, 260);
                    return;
                }
                let updated = false;
                if (card && newStatus) {
                    const statusMap = { draft:@json(status_label('draft')), pending:@json(status_label('pending')), accepted:@json(status_label('accepted')), rejected:@json(status_label('rejected')) };
                    const clsMap = { draft:'bg-ink-100 text-ink-500 border-[#e6e9e1]', pending:'bg-amber-50 text-amber-700 border-amber-200', accepted:'bg-[#eef4f0] text-[#0e6a38] border-[#cde7d6]', rejected:'bg-red-50 text-red-700 border-red-200' };
                    const dotMap = { draft:'bg-ink-300', pending:'bg-amber-400', accepted:'bg-[#0e6a38]', rejected:'bg-red-400' };
                    const badge = card.querySelector('.note-badge');
                    if (badge && clsMap[newStatus]) {
                        badge.className = 'note-badge inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border ' + clsMap[newStatus];
                        badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full ' + dotMap[newStatus] + (newStatus==='pending'?' animate-pulse':'') + '"></span>' + statusMap[newStatus];
                        updated = true;
                    }

                    const actionsEl = card.querySelector('.note-actions');
                    if (actionsEl) {
                        let newActions = '';
                        const tEdit = @json(__('ui.edit_btn'));
                        const tFix = @json(__('ui.fix_btn'));
                        const tAccepted = @json(__('ui.accepted_status'));
                        if (newStatus === 'pending') {
                            newActions = '<a href="/notes/'+noteId+'/edit" class="px-2.5 py-1 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#525252] text-xs font-bold">'+tEdit+'</a>';
                        } else if (newStatus === 'accepted') {
                            newActions = '<span class="text-[11px] text-[#0e6a38] font-bold">'+tAccepted+'</span>';
                        } else if (newStatus === 'rejected') {
                            newActions = '<a href="/notes/'+noteId+'/edit" class="px-2.5 py-1 rounded-lg bg-ink-800 text-white text-xs font-bold">'+tFix+'</a>';
                        }
                        actionsEl.innerHTML = newActions;
                        updated = true;
                    }
                }
                if (data.deleted && card) {
                    card.style.transition = 'opacity .2s, transform .2s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    setTimeout(() => card.remove(), 250);
                    updated = true;
                }

                if (!updated) { location.reload(); return; }
                applyCounts();
                window.toast(successMsg);
            } else {
                window.toast(data.message || @json(__('ui.notif_update_failed')));
                if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
            }
        } catch(err) {
            this.submit();
        }
    });
});


document.querySelectorAll('[data-ajax-tab]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.href;
        const listEl = document.getElementById('notes-list');
        const tabsNav = this.closest('nav');
        if (!listEl) { window.location.href = url; return; }
        listEl.style.opacity = '0.4';
        listEl.style.transition = 'opacity .15s';
        tabsNav.querySelectorAll('a').forEach(a => { a.classList.remove('border-[#0e6a38]','text-ink-800','font-bold'); a.classList.add('border-transparent','text-[#737373]'); });
        this.classList.add('border-[#0e6a38]','text-ink-800','font-bold');
        this.classList.remove('border-transparent','text-[#737373]');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newList = doc.getElementById('notes-list');
                if (newList) listEl.innerHTML = newList.innerHTML;
                listEl.style.opacity = '1';
                history.pushState(null, '', url);
            })
            .catch(() => { window.location.href = url; });
    });
});
</script>
@endpush
@push('scripts')
<script>

(function(){
    if (!document.getElementById('notes-list') || typeof window.ajaxFilter !== 'function') return;
    var lastSig = null, busy = false;
    function buildCheckUrl(){
        var q = new URLSearchParams(window.location.search);
        q.set('live', '1');
        return window.location.pathname + '?' + q.toString();
    }
    function userBusy(){
        var a = document.activeElement;
        if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT' || a.isContentEditable)) return true;
        if (document.querySelector('[data-modal]:not(.hidden),#share-modal:not(.hidden),#camera-modal:not(.hidden),#camera-modal-edit:not(.hidden),#print-selection-modal:not(.hidden),#print-preview-modal:not(.hidden)')) return true;
        return false;
    }
    function tick(){
        if (document.hidden || busy || userBusy()) return;
        busy = true;
        fetch(buildCheckUrl(), {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}})
            .then(function(r){ if (!r.ok) throw 0; return r.json(); })
            .then(function(d){
                if (lastSig === null) { lastSig = d.sig; return; }
                if (d.sig !== lastSig) {
                    lastSig = d.sig;
                    window.ajaxFilter(window.location.pathname + window.location.search);
                }
            })
            .catch(function(){ })
            .finally(function(){ busy = false; });
    }
    document.addEventListener('visibilitychange', function(){ if (!document.hidden) tick(); });
    // Reduced from 1s -> 15s: php artisan serve is single-threaded + sqlite locks on every poll (session+cache+data on one file).
    // Polling every second queued navigation behind polls and caused the 30s spinner.
    setInterval(tick, 15000);
})();
</script>
@endpush
@include('notes.partials.print_modal')
@endsection
