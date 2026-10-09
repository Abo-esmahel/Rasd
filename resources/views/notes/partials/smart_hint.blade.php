@php
    $isEdit = isset($note) && $note && $note->exists;
    $inputLangKey = app()->getLocale() === 'en' ? __('ui.smart_note_source_en') : __('ui.smart_note_source_ar');
@endphp
<div class="flex items-start gap-2 p-3 rounded-xl bg-[#eef4f0] dark:bg-[#1e3328] border border-[#cde7d6] dark:border-[#24402e]">
    <x-phosphor-lightning-duotone class="w-4 h-4 text-[#0e6a38] dark:text-[#4ade80] shrink-0 mt-0.5" />
    <div class="min-w-0">
        <div class="text-[11px] font-extrabold text-[#0e6a38] dark:text-[#4ade80]">{{ $inputLangKey }}</div>
        <div class="text-[11px] leading-5 text-ink-500 dark:text-[#9bb0a0]">{{ $isEdit ? __('ui.smart_note_hint_edit') : __('ui.smart_note_hint_create') }}</div>
    </div>
</div>
