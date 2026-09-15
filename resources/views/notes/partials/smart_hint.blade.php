@php
    $isEdit = isset($note) && $note && $note->exists;
    $inputLangKey = app()->getLocale() === 'en' ? __('ui.smart_note_source_en') : __('ui.smart_note_source_ar');
@endphp

    </div>
</div>
