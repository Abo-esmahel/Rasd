@php
    $userId = auth()->id();
    $isMonitor = auth()->user()->isMonitor();
    $isWriter = !$isMonitor;
@endphp

 {{-- STATUS TABS --}}
<div class="border-b border-[#e6e9e1] mb-6 -mx-4 sm:mx-0 px-4 sm:px-0 overflow-x-auto scrollbar-hide">
    <nav class="flex gap-6 min-w-max" aria-label="حالات الملاحظات">
        @php
            $tabs = [
                ['key'=>null, 'label'=>'الكل', 'count'=>$counts['total']],
                ['key'=>'draft', 'label'=>'مسودات', 'count'=>$counts['draft']],
                ['key'=>'pending', 'label'=>'قيد المراجعة', 'count'=>$counts['pending']],
                ['key'=>'accepted', 'label'=>'مقبولة', 'count'=>$counts['accepted']],
                ['key'=>'rejected', 'label'=>'مرفوضة', 'count'=>$counts['rejected']],
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

 {{-- FILTERS --}}
@if($mode === 'all')
<div class="mb-5">
    <details class="group">
        <summary class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-[#737373] hover:text-ink-700 hover:bg-[#eceee9] cursor-pointer transition list-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            فلاتر وترتيب
            @if($observer || $date || $floor_number || $camera_number || $sort)
                <span class="w-1.5 h-1.5 rounded-full bg-[#0e6a38]"></span>
            @endif
            <svg class="w-3.5 h-3.5 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="mt-3 p-4 bg-surface-50 rounded-xl border border-surface-300">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">المراقب</label>
                    <select wire:model.live="observer" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                        <option value="">الكل</option>
                        @foreach($observers as $obs)
                            <option value="{{ $obs->id }}">{{ $obs->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">ترتيب حسب</label>
                    <select wire:model.live="sort" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                        <option value="">الأحدث</option>
                        <option value="observer">المراقب (أ-ي)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">تاريخ الملاحظة</label>
                    <input type="date" wire:model.live="date" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">رقم الطابق</label>
                    <input type="number" wire:model.live="floor_number" min="1" placeholder="مثال: 3" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#525252] mb-1.5">رقم الكاميرا</label>
                    <input type="number" wire:model.live="camera_number" min="1" placeholder="مثال: 12" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
                <div class="flex gap-2 items-end">
                    <button wire:click="clearFilters" class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-[#e6e9e1] text-[#737373] font-medium text-sm hover:bg-[#f5f7f5] transition">مسح الفلاتر</button>
                </div>
            </div>
        </div>
    </details>
</div>
@endif

 {{-- NOTES LIST --}}
@if($notes->count() === 0)
    <div class="bg-surface-50 rounded-xl border border-surface-300 p-10 text-center">
        <div class="w-14 h-14 rounded-2xl bg-surface-100 flex items-center justify-center mx-auto">
            <svg class="w-7 h-7 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="mt-4 text-base font-bold text-ink-700">لا توجد ملاحظات</h3>
        <p class="mt-1.5 text-sm text-[#737373]">جرّب تغيير معايير الفلترة أو أنشئ ملاحظة جديدة.</p>
    </div>
@else
    {{-- Desktop --}}
    <div wire:poll.30s class="bg-white rounded-xl border border-[#e6e9e1] shadow-sm overflow-hidden hidden sm:block">
        <div class="grid grid-cols-[auto_1fr_auto_auto] gap-4 items-center px-5 py-2.5 bg-[#f5f7f5] border-b border-[#e6e9e1] text-xs font-bold text-ink-400">
            <div class="w-20">#</div>
            <div>الملاحظة</div>
            <div class="w-28 text-center">الحالة</div>
            <div class="w-36 text-center">إجراءات</div>
        </div>

        @foreach($notes as $note)
            <div class="grid grid-cols-[auto_1fr_auto_auto] gap-4 items-center px-5 py-3.5 border-b border-surface-300 last:border-b-0 cursor-pointer hover:bg-sage-50 hover:border-l-[3px] hover:border-l-sage-600 transition-all duration-200">
                <div class="w-20">
                    <span class="text-sm font-bold text-[#737373] font-mono">#{{ str_pad($note->id, 4, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1 text-sm font-bold text-ink-800">
                            <svg class="w-3.5 h-3.5 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            كاميرا {{ $note->camera_number }}
                        </span>
                        <span class="text-ink-200">·</span>
                        <span class="text-sm text-[#525252]">الطابق {{ $note->floor_number }}</span>
                        <span class="text-ink-200">·</span>
                        <span class="text-sm text-[#737373]">{{ $note->observed_at->format('H:i') }}{{ $note->observed_end_at ? ' — '.$note->observed_end_at->format('H:i') : '' }}</span>
                        <span class="text-ink-200">·</span>
                        <span class="text-sm text-[#737373]">{{ $note->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1 text-sm text-[#737373] line-clamp-1 leading-relaxed">{{ \Illuminate\Support\Str::limit($note->description, 120) }}</p>
                    <div class="mt-1.5 flex items-center gap-2 text-xs text-ink-300">
                        <span class="inline-flex items-center gap-1.5">
                            @if($note->owner->avatar_url)
                                <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->name }}" class="w-4 h-4 rounded-full object-cover border border-[#e6e9e1]">
                            @else
                                <span class="w-4 h-4 rounded-full bg-sage-50 text-sage-700 flex items-center justify-center text-[9px] font-bold">{{ $note->owner->initial }}</span>
                            @endif
                            {{ $note->owner->name }}
                        </span>
                        @if($note->attachments->count() > 0)
                            <span class="text-ink-200">·</span>
                            <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>{{ $note->attachments->count() }} مرفق</span>
                        @endif
                    </div>
                </div>
                <div class="w-28 flex justify-center">
                    @if($note->isDraft())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-ink-100 text-ink-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-ink-300"></span>مسودة
                        </span>
                    @elseif($note->isPending())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>قيد المراجعة
                        </span>
                    @elseif($note->isAccepted())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-sage-50 text-sage-700 border border-sage-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-sage-600"></span>مقبولة
                        </span>
                    @elseif($note->isRejected())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>مرفوضة
                        </span>
                    @endif
                </div>
                <div class="w-36 flex justify-center" onclick="event.stopPropagation()">
                    @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-lg bg-surface-50 border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">تعديل</a>
                            <button wire:click="send({{ $note->id }})" wire:confirm="هل أنت متأكد من الإرسال؟" class="px-3 py-1.5 rounded-lg bg-sage-600 text-white text-xs font-bold hover:bg-sage-700 transition">إرسال</button>
                        </div>
                    @elseif($isMonitor && $note->user_id === $userId && $note->isPending())
                        <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-lg bg-surface-50 border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">تعديل</a>
                    @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-lg bg-ink-800 text-white text-xs font-bold hover:bg-ink-900 transition">تصحيح</a>
                            <button wire:click="resend({{ $note->id }})" wire:confirm="هل أنت متأكد من إعادة الإرسال؟" class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs font-bold hover:bg-amber-600 transition">إعادة إرسال</button>
                        </div>
                    @elseif($isWriter && $note->isPending())
                        <div class="flex items-center gap-1.5">
                            <button wire:click="accept({{ $note->id }})" wire:confirm="هل أنت متأكد من القبول؟" class="px-3 py-1.5 rounded-lg bg-sage-600 text-white text-xs font-bold hover:bg-sage-700 transition">قبول</button>
                            <button x-data="{ rejectModal: false, reason: '' }" @click="rejectModal = true" class="px-3 py-1.5 rounded-lg bg-red-500 text-white text-xs font-bold hover:bg-red-600 transition">رفض</button>
                            <template x-if="false"><div x-init="$watch('rejectModal', v => { if(v) $refs.rejectNoteId.value = {{ $note->id }}; })"></div></template>
                        </div>
                    @elseif($note->isAccepted())
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-sage-600">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            مُعتمدة
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Mobile --}}
    <div wire:poll.30s class="sm:hidden space-y-3">
        @foreach($notes as $note)
            <div class="bg-white rounded-xl border border-surface-300 shadow-sm p-4 hover:shadow-lg hover:border-sage-600 transition-all duration-200">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-[#737373] font-mono">#{{ str_pad($note->id, 4, '0', STR_PAD_LEFT) }}</span>
                        @if($note->isDraft())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-ink-100 text-[#525252]">مسودة</span>
                        @elseif($note->isPending())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">قيد المراجعة</span>
                        @elseif($note->isAccepted())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#eef4f0] text-[#0e6a38] border border-[#cde7d6]">مقبولة</span>
                        @elseif($note->isRejected())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 border border-red-200">مرفوضة</span>
                        @endif
                    </div>
                    <span class="text-xs text-ink-300">{{ $note->observed_at->format('H:i') }}{{ $note->observed_end_at ? ' — '.$note->observed_end_at->format('H:i') : '' }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-ink-600 mb-1.5">
                    <span class="font-semibold">كاميرا {{ $note->camera_number }}</span>
                    <span class="text-ink-200">·</span>
                    <span>الطابق {{ $note->floor_number }}</span>
                </div>
                <p class="text-sm text-[#737373] line-clamp-2 leading-relaxed">{{ \Illuminate\Support\Str::limit($note->description, 100) }}</p>
                <div class="mt-2.5 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs text-ink-300">
                        <span class="inline-flex items-center gap-1">
                            @if($note->owner->avatar_url)
                                <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->name }}" class="w-4 h-4 rounded-full object-cover border border-surface-300">
                            @else
                                <span class="w-4 h-4 rounded-full bg-sage-100 text-sage-700 flex items-center justify-center text-[9px] font-bold">{{ $note->owner->initial }}</span>
                            @endif
                            {{ $note->owner->name }}
                        </span>
                    </div>
                    <div onclick="event.stopPropagation()">
                        @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('notes.edit', $note) }}" class="px-2.5 py-1 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#525252] text-xs font-bold">تعديل</a>
                                <button wire:click="send({{ $note->id }})" wire:confirm="هل أنت متأكد من الإرسال؟" class="px-2.5 py-1 rounded-lg bg-[#0e6a38] text-white text-xs font-bold">إرسال</button>
                            </div>
                        @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('notes.edit', $note) }}" class="px-2.5 py-1 rounded-lg bg-ink-800 text-white text-xs font-bold">تصحيح</a>
                                <button wire:click="resend({{ $note->id }})" wire:confirm="هل أنت متأكد؟" class="px-2.5 py-1 rounded-lg bg-amber-500 text-white text-xs font-bold">إعادة</button>
                            </div>
                        @elseif($isWriter && $note->isPending())
                            <div class="flex items-center gap-1.5">
                                <button wire:click="accept({{ $note->id }})" wire:confirm="هل أنت متأكد من القبول؟" class="px-2.5 py-1 rounded-lg bg-[#0e6a38] text-white text-xs font-bold">قبول</button>
                                <button class="px-2.5 py-1 rounded-lg bg-red-500 text-white text-xs font-bold">رفض</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($notes->hasPages())
        <div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-[#737373]">
                عرض <span class="font-bold text-ink-700">{{ $notes->firstItem() ?? 0 }}–{{ $notes->lastItem() ?? 0 }}</span> من <span class="font-bold text-ink-700">{{ $notes->total() }}</span>
            </div>
            <div>{{ $notes->links() }}</div>
        </div>
    @endif
@endif
