@php
    // Smart Note badge — قراءة فقط (STRICT: بلا Gemini، بلا storm).
    // يعتمد على preload من الـ Controller (requestCache) — لا استعلامات هنا.
    $locale = app()->getLocale();
    $l10nType = $type ?? 'note';
    $descSource = (string) ($note->description ?? '');
    $descState = translation_state($l10nType, $note->id, 'description', $descSource, $locale);
    $rejSource = (string) ($note->rejection_reason ?? '');
    $rejState = $rejSource !== '' ? translation_state($l10nType, $note->id, 'rejection_reason', $rejSource, $locale) : 'source';
    $states = array_filter([$descState, $rejState], fn($s) => $s !== 'source');
    $overall = in_array('pending', $states, true) ? 'pending' : (in_array('ready', $states, true) ? 'ready' : 'source');
    // قديمة بلا ترجمة (stale/failed) — updated منذ >5 دقائق وما زالت pending.
    $isStale = $overall === 'pending' && $note->updated_at && $note->updated_at->lt(now()->subMinutes(5));
@endphp
@if($overall !== 'source')
<div class="flex items-center gap-2 text-xs" data-note-translation-badge data-note-id="{{ $note->id }}" data-l10n-type="{{ $l10nType }}" data-state="{{ $overall }}">
    @if($overall === 'ready')
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#eef4f0] text-[#0e6a38] border border-[#cde7d6] font-bold">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ __('ui.note_translation_ready') }}
        </span>
    @elseif($isStale)
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-50 text-red-700 border border-red-200 font-bold">
            {{ __('ui.note_translation_failed') }}
        </span>
        <button type="button" data-translation-retry="{{ $note->id }}" data-l10n-type="{{ $l10nType }}"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 font-bold hover:bg-[#f5f7f5] transition">
            {{ __('ui.note_translation_retry') }}
        </button>
    @else
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-bold">
            <span class="w-3 h-3 border-2 border-amber-600 border-t-transparent rounded-full animate-spin"></span>
            {{ __('ui.note_translation_preparing') }}
        </span>
        <button type="button" data-translation-retry="{{ $note->id }}" data-l10n-type="{{ $l10nType }}"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 font-bold hover:bg-[#f5f7f5] transition">
            {{ __('ui.note_translation_retry') }}
        </button>
    @endif
</div>
@pushOnce('scripts')
<script>
(function(){
    if (window.__rasdNoteRetryBound) return;
    window.__rasdNoteRetryBound = true;
    document.addEventListener('click', async function(e){
        var btn = e.target && e.target.closest ? e.target.closest('[data-translation-retry]') : null;
        if (!btn) return;
        var id = btn.getAttribute('data-translation-retry');
        var l10nType = btn.getAttribute('data-l10n-type') || 'note';
        if (!id) return;
        btn.disabled = true;
        var orig = btn.textContent;
        try {
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var url = (l10nType && l10nType !== 'note')
                ? '/translations/retry'
                : '/notes/' + encodeURIComponent(id) + '/translation-retry';
            var payload = (l10nType && l10nType !== 'note')
                ? { type: l10nType, id: id, locale: (window.RASD_LOCALE === 'en' ? 'en' : 'ar') }
                : { locale: (window.RASD_LOCALE === 'en' ? 'en' : 'ar') };
            var res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
                },
                body: JSON.stringify(payload)
            });
            var data = await res.json().catch(function(){ return {}; });
            if (data && data.message) {
                if (window.toast) window.toast(data.message);
                else btn.textContent = data.message;
            }
        } catch(_){
            btn.disabled = false;
            btn.textContent = orig;
        }
        setTimeout(function(){ btn.disabled = false; btn.textContent = orig; }, 8000);
    });
})();
</script>
@endPushOnce
@endif
