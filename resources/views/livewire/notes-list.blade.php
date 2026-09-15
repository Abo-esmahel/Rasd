@php
    $userId = auth()->id();
    $isMonitor = auth()->user()->isMonitor();
    $isWriter = !$isMonitor;
@endphp


<div class="w-full max-w-full overflow-x-auto scrollbar-hide border-b border-[#e6e9e1] mb-6 mx-0 px-0 tabs-scroll-shadow">
    <nav class="flex gap-6 min-w-max w-max" aria-label="{{ __('ui.note_statuses') }}">
        @php
            $tabs = [
                ['key'=>null, 'label'=>__('ui.all'), 'count'=>$counts['total']],
                ['key'=>'draft', 'label'=>__('ui.drafts'), 'count'=>$counts['draft']],
                ['key'=>'pending', 'label'=>__('ui.pending'), 'count'=>$counts['pending']],
                ['key'=>'accepted', 'label'=>__('ui.accepted'), 'count'=>$counts['accepted']],
                ['key'=>'rejected', 'label'=>__('ui.rejected'), 'count'=>$counts['rejected']],
            ];
            $currentStatus = $this->status;
        @endphp
        @foreach($tabs as $tab)
            @php
                $isActive = $currentStatus === $tab['key'];
            @endphp
            <button wire:click="$set('status', @js($tab['key']))" class="relative flex items-center gap-1.5 py-3 text-[13px] whitespace-nowrap border-b-2 transition {{ $isActive ? 'border-[#0e6a38] text-ink-800 font-bold' : 'border-transparent text-[#737373] hover:text-ink-600 font-medium' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="text-[11px] font-mono tabular-nums {{ $isActive ? 'text-[#0e6a38]' : 'text-ink-300' }}">{{ $tab['count'] }}</span>
            </button>
        @endforeach
    </nav>
</div>


@if($mode === 'all')
<div class="mb-5">
    <details class="group">
        <summary class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-[#737373] hover:text-ink-700 hover:bg-[#eceee9] cursor-pointer transition list-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            @if($observer || $date || $floor_number || $camera_number || $sort)
                <span class="w-1.5 h-1.5 rounded-full bg-[#0e6a38]"></span>
            @endif
            <svg class="w-3.5 h-3.5 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="mt-3 p-4 bg-surface-50 rounded-xl border border-surface-300">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">{{ __('ui.observer_label') }}</label>
                    <select wire:model.live="observer" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                        <option value="">{{ __('ui.all') }}</option>
                        @foreach($observers as $obs)
                            @if(is_object($obs) && isset($obs->id))
                                <option value="{{ $obs->id }}">{{ $obs->localized_name }}</option>
                            @elseif(is_array($obs) && isset($obs['id']))
                                <option value="{{ $obs['id'] }}">{{ app()->getLocale() === 'en' ? ($obs['name_en'] ?? $obs['name']) : ($obs['name_ar'] ?? $obs['name']) }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">{{ __('ui.sort_by') }}</label>
                    <select wire:model.live="sort" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                        <option value="">{{ __('ui.newest_opt') }}</option>
                        <option value="observer">{{ __('ui.observer_az') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">{{ __('ui.filter_date') }}</label>
                    <input type="date" wire:model.live="date" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">{{ __('ui.floor') }}</label>
                    <input type="number" wire:model.live.debounce.500ms="floor_number" min="1" placeholder="3" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">{{ __('ui.camera') }}</label>
                    <input type="number" wire:model.live.debounce.500ms="camera_number" min="1" placeholder="12" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
                <div class="flex gap-2 items-end">
                    <button wire:click="clearFilters" class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-[#e6e9e1] text-[#737373] font-medium text-sm hover:bg-[#f5f7f5] transition">{{ __('ui.clear_filters') }}</button>
                </div>
            </div>
        </div>
    </details>
</div>
@endif


@if($notes->count() === 0)
    <div class="bg-surface-50 rounded-xl border border-surface-300 p-10 text-center">
        <div class="w-14 h-14 rounded-2xl bg-surface-100 flex items-center justify-center mx-auto">
            <svg class="w-7 h-7 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="mt-4 text-base font-bold text-ink-700">{{ __('ui.no_notes') }}</h3>
    </div>
@else

    <div wire:poll.60s class="hidden md:grid md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
        @foreach($notes as $note)
            <div class="group relative bg-white dark:bg-[#252b26] rounded-2xl border border-[#eceee9] dark:border-[#2e352e] shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_28px_rgba(0,0,0,0.07)] hover:border-[#e3e8e1] dark:hover:border-[#33423a] transition-all duration-200 overflow-hidden cursor-pointer" onclick="openModal('detail-{{ $note->id }}')" data-note-card data-i18n-entity="note" data-i18n-id="{{ $note->id }}">
                <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[54px] h-[5px] pointer-events-none">
                    <div class="w-full h-full @if($note->isDraft()) bg-[#c8cfc6] dark:bg-[#4a5e50] @elseif($note->isPending()) bg-[#e8c24a] dark:bg-[#c9a020] @elseif($note->isAccepted()) bg-[#0e6a38] dark:bg-[#1a8a50] @elseif($note->isRejected()) bg-[#c44040] dark:bg-[#a83535] @endif rounded-t-[5px]" style="clip-path: polygon(7% 100%, 93% 100%, 88% 0, 12% 0)"></div>
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-3.5">
                        <div class="flex items-center gap-3 min-w-0">
                            <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="shrink-0">
                                @if($note->owner->avatar_url)
                                    <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->localized_name }}" class="w-10 h-10 rounded-xl object-cover border border-[#eceee9] dark:border-[#2e352e] shadow-sm">
                                @else
                                    <span class="w-10 h-10 rounded-xl bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[#0e6a38] dark:text-[#5cb87a] flex items-center justify-center text-[13px] font-semibold shadow-sm">{{ $note->owner->initial }}</span>
                                @endif
                            </a>
                            <div class="min-w-0">
                                <div class="text-[13.5px] font-semibold tracking-tight text-ink-800 dark:text-[#e7ece5] truncate">{{ $note->owner->localized_name }}</div>
                                <div class="mt-0.5 inline-flex items-center gap-1.5 text-[11.5px] font-medium text-ink-500 dark:text-[#8a9a8e]"><span class="w-1 h-1 rounded-full bg-ink-200 dark:bg-[#3a4d3d]"></span><span dir="ltr" class="tabular-nums">{{ $note->camera_number }} · {{ $note->floor_number }}</span></div>
                            </div>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[11px] font-medium text-ink-500 dark:text-[#9bb0a0] tabular-nums whitespace-nowrap">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? ' → '.$note->observed_end_at->toTime12() : '' }}</span>
                    </div>
                    <p class="text-[14px] leading-[1.75] tracking-tight text-ink-700 dark:text-[#d5ded6] line-clamp-2 min-h-[48px] mb-4" data-i18n-field="description">{{ \Illuminate\Support\Str::limit(l10n_text('note', $note->id, 'description', $note->description), 120) }}</p>
                    <div class="flex items-center justify-between gap-3 pt-3.5 border-t border-[#f1f3f0] dark:border-[#2a352f]">
                        <span class="text-xs text-ink-400 dark:text-[#8a9a8e] flex items-center gap-1.5"><svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> {{ $note->created_at->diffForHumans() }}@php($attCount = $note->attachments_count ?? $note->attachments->count())@if($attCount > 0) <span class="hidden sm:inline">·</span> <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> {{ __('ui.attachment_count', ['count' => $attCount]) }}</span>@endif</span>
                        <div class="flex items-center gap-1.5" onclick="event.stopPropagation()">
                            @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                                <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-ink-700 dark:text-[#9bb0a0] text-xs font-semibold hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] transition">{{ __('ui.edit_btn') }}</a>
                                <button wire:click="send({{ $note->id }})" wire:confirm="{{ __('ui.confirm_send') }}" class="px-3.5 py-1.5 rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-xs font-semibold shadow-sm transition">{{ __('ui.send_btn') }}</button>
                            @elseif($isMonitor && $note->user_id === $userId && $note->isPending())
                                <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-ink-700 dark:text-[#9bb0a0] text-xs font-semibold hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] transition">{{ __('ui.edit_btn') }}</a>
                            @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                                <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-ink-800 dark:bg-[#2a302b] text-white text-xs font-semibold hover:bg-ink-900 transition">{{ __('ui.fix_btn') }}</a>
                                <button wire:click="resend({{ $note->id }})" wire:confirm="{{ __('ui.confirm_resend') }}" class="px-3.5 py-1.5 rounded-full bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold shadow-sm transition">{{ __('ui.resend_btn') }}</button>
                            @elseif($isWriter && $note->isPending())
                                <button wire:click="accept({{ $note->id }})" wire:confirm="{{ __('ui.confirm_accept') }}" class="px-3.5 py-1.5 rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-xs font-semibold shadow-sm transition">{{ __('ui.accept_btn') }}</button>
                                <button x-data="{ rejectModal: false, reason: '' }" @click="rejectModal = true" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-red-200 dark:border-[#3d2626] text-red-600 dark:text-[#f08080] text-xs font-semibold hover:bg-red-50 dark:hover:bg-[#2d1f1f] transition">{{ __('ui.reject_btn') }}</button>
                                <template x-if="false"><div x-init="$watch('rejectModal', v => { if(v) $refs.rejectNoteId.value = {{ $note->id }}; })"></div></template>
                            @elseif($note->isAccepted())
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#0e6a38] dark:text-[#5cb87a] bg-[#eef4f0] dark:bg-[#1e3328] border border-[#d6e8dc] dark:border-[#24402e] px-2.5 py-1 rounded-full">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
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
                        <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="shrink-0 mt-0.5">
                            @if($note->owner->avatar_url)
                                <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->localized_name }}" class="w-10 h-10 rounded-xl object-cover border border-[#eceee9] dark:border-[#2e352e] shadow-sm">
                            @else
                                <span class="w-10 h-10 rounded-xl bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[#0e6a38] dark:text-[#5cb87a] flex items-center justify-center text-[13px] font-semibold shadow-sm">{{ $note->owner->initial }}</span>
                            @endif
                        </a>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <span class="text-[12px] font-semibold tracking-tight text-ink-700 dark:text-[#d5ded6] truncate pr-1">{{ $note->owner->localized_name }}</span>
                                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[10px] font-medium text-ink-500 dark:text-[#8a9a8e] tabular-nums" dir="ltr">{{ $note->camera_number }} · {{ $note->floor_number }}</span>
                            </div>
                            <p class="text-[14px] leading-[1.65] tracking-tight text-ink-700 dark:text-[#d5ded6] line-clamp-2 mb-3 note-card-body" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;word-break:break-word" data-i18n-field="description">{{ \Illuminate\Support\Str::limit(l10n_text('note', $note->id, 'description', $note->description), 100) }}</p>
                            <div class="flex items-center gap-1.5 flex-wrap" onclick="event.stopPropagation()">
                                @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                                    <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-ink-700 dark:text-[#9bb0a0] text-xs font-semibold hover:bg-[#f5f7f5] dark:hover:bg-[#2a302b] transition">{{ __('ui.edit_btn') }}</a>
                                    <button wire:click="send({{ $note->id }})" wire:confirm="{{ __('ui.confirm_send') }}" class="px-3.5 py-1.5 rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-xs font-semibold shadow-sm transition">{{ __('ui.send_btn') }}</button>
                                @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                                    <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-full bg-ink-800 dark:bg-[#2a302b] text-white text-xs font-semibold hover:bg-ink-900 transition">{{ __('ui.fix_btn') }}</a>
                                    <button wire:click="resend({{ $note->id }})" wire:confirm="{{ __('ui.confirm_resend') }}" class="px-3.5 py-1.5 rounded-full bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold shadow-sm transition">{{ __('ui.resend_btn') }}</button>
                                @elseif($isWriter && $note->isPending())
                                    <button wire:click="accept({{ $note->id }})" wire:confirm="{{ __('ui.confirm_accept') }}" class="px-3.5 py-1.5 rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-xs font-semibold shadow-sm transition">{{ __('ui.accept_btn') }}</button>
                                    <button class="px-3.5 py-1.5 rounded-full bg-white dark:bg-[#1e2320] border border-red-200 dark:border-[#3d2626] text-red-600 dark:text-[#f08080] text-xs font-semibold hover:bg-red-50 dark:hover:bg-[#2d1f1f] transition">{{ __('ui.reject_btn') }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    @if($notes->hasPages())
        <div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-[#737373]">
                {{ __('ui.showing') }} <span class="font-bold text-ink-700">{{ $notes->firstItem() ?? 0 }}–{{ $notes->lastItem() ?? 0 }}</span> {{ __('ui.of') }} <span class="font-bold text-ink-700">{{ $notes->total() }}</span>
            </div>
            <div>{{ $notes->links() }}</div>
        </div>
    @endif
@endif
