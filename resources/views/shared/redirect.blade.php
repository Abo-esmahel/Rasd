@extends('layouts.app')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-8">
    <section class="w-full max-w-md overflow-hidden rounded-3xl border border-surface-300 bg-white shadow-sm" aria-live="polite">
        <div class="relative overflow-hidden bg-gradient-to-l from-sage-600 via-[#0d5c31] to-[#083a20] px-6 pt-8 pb-12 text-center">
            <div class="pointer-events-none absolute -top-14 -left-12 h-44 w-44 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-20 right-6 h-52 w-52 rounded-full bg-black/15 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border border-white/30 bg-white/15 text-white backdrop-blur-sm shadow-lg">
                @if($icon === 'moved')
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                @elseif($icon === 'expired')
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @elseif($icon === 'denied')
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                @elseif($icon === 'done')
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @elseif($icon === 'missing')
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172A4 4 0 015.656 14H5a3 3 0 01-3-3V9a3 3 0 013-3h1.172a4 4 0 012.828-1.172h1.172A4 4 0 0113 5.656V6a3 3 0 003 3h2a3 3 0 013 3v2a3 3 0 01-3 3h-.344a4 4 0 01-2.828 1.172H9.172z"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg>
                @else
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                @endif
            </div>
        </div>

        <div class="px-6 sm:px-8 pb-6 sm:pb-8 -mt-6">
            <div class="rounded-2xl border border-surface-300 bg-surface-50 px-5 py-5 text-center">
                <h1 class="text-lg font-extrabold text-ink-800 leading-snug">{{ $title }}</h1>
                <p class="mt-1.5 text-[13px] leading-6 text-ink-400">{{ $message }}</p>
                @isset($wanted)
                    <p class="mt-2 truncate font-mono text-[11px] text-ink-300" dir="ltr">/{{ $wanted }}</p>
                @endisset
            </div>

            <div class="mt-4 flex items-center gap-3 rounded-2xl border border-surface-300 bg-white px-4 py-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sage-600 font-extrabold tabular-nums text-white text-lg" id="sr-count" aria-hidden="true">{{ $delay }}</span>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold text-ink-500">{{ __('ui.redirect_auto_in') }} <span id="sr-count-text">{{ $delay }}</span> {{ __('ui.redirect_seconds') }}</div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-200" role="progressbar" aria-label="{{ __('ui.redirect_progress') }}">
                        <div id="sr-bar" class="h-full w-0 rounded-full bg-gradient-to-l from-sage-500 to-sage-700 transition-all duration-1000 ease-linear"></div>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-2">
                <a href="{{ $target }}" id="sr-go" class="inline-flex items-center justify-center gap-2 rounded-xl bg-sage-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-sage-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    {{ __('ui.redirect_go_now') }}
                </a>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" id="sr-back" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-surface-300 bg-white px-4 py-2.5 text-sm font-bold text-ink-500 hover:bg-surface-100 transition">
                        {{ __('ui.redirect_back') }}
                    </button>
                    <a href="{{ $home }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-surface-300 bg-white px-4 py-2.5 text-sm font-bold text-ink-500 hover:bg-surface-100 transition">
                        {{ __('ui.redirect_home') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    var target = @json($target);
    var home = @json($home);
    var delay = Math.max(0, parseInt(@json($delay), 10) || 0);
    var remaining = delay;
    var countEl = document.getElementById('sr-count');
    var countText = document.getElementById('sr-count-text');
    var bar = document.getElementById('sr-bar');
    var done = false;

    function go() {
        if (done) return;
        done = true;
        window.location.href = target;
    }

    function tick() {
        if (done) return;
        if (remaining <= 0) { go(); return; }
        remaining -= 1;
        if (countEl) countEl.textContent = remaining;
        if (countText) countText.textContent = remaining;
        if (bar && delay > 0) bar.style.width = Math.round(((delay - remaining) / delay) * 100) + '%';
        if (remaining <= 0) { setTimeout(go, 400); return; }
        setTimeout(tick, 1000);
    }

    document.getElementById('sr-back').addEventListener('click', function () {
        if (window.history.length > 1) { window.history.back(); setTimeout(function () { if (!done) go(); }, 1200); }
        else { window.location.href = home; }
    });

    if (bar && delay > 0) bar.style.width = '4%';
    setTimeout(tick, 1000);

    // احتياط بلا JS
    var meta = document.createElement('meta');
    meta.httpEquiv = 'refresh';
    meta.content = Math.max(1, delay) + ';url=' + target;
    document.head.appendChild(meta);
})();
</script>
@endsection
