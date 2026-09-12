@php
    $isEdit = isset($note) && $note && $note->exists;
    $inputLangKey = app()->getLocale() === 'en' ? __('ui.smart_note_source_en') : __('ui.smart_note_source_ar');
@endphp
<div class="rounded-xl border border-[#cde7d6] bg-[#eef4f0]/60 p-4 flex gap-3">
    <div class="shrink-0 w-9 h-9 rounded-lg bg-white border border-[#cde7d6] flex items-center justify-center">
        <svg class="w-4.5 h-4.5 w-5 h-5 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2c0 2-2 4-5 5m5-5c1 2 3 4 6 5m-3-2l3 3m0 0l3-3m-3 3v4m-9 3h9m-9 0l2-2m-2 2l-2-2"/></svg>
    </div>
    <div class="min-w-0">
        <div class="text-sm font-extrabold text-[#0e6a38]">{{ __('ui.smart_note_title') }}</div>
        <p class="mt-1 text-xs leading-5 text-ink-600">{{ $isEdit ? __('ui.smart_note_hint_edit') : __('ui.smart_note_hint_create') }}</p>
        <p class="mt-1 text-[11px] font-bold text-ink-400">{{ $inputLangKey }}</p>
    </div>
</div>
