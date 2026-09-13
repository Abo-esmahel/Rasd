@extends('layouts.app')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-8">
    <section class="w-full max-w-md overflow-hidden rounded-3xl border border-surface-300 bg-white shadow-sm">
        <div class="relative overflow-hidden bg-gradient-to-l from-[#c41e1e] via-[#991b1b] to-[#7f1d1d] px-6 pt-8 pb-12 text-center">
            <div class="pointer-events-none absolute -top-14 -left-12 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-20 right-6 h-52 w-52 rounded-full bg-black/15 blur-3xl"></div>
            <div class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border border-white/30 bg-white/15 text-white backdrop-blur-sm shadow-lg font-extrabold text-2xl">404</div>
            <p class="relative mt-3 text-sm font-bold text-white/90">{{ __('ui.redirect_missing_title', [], 'ar') ?: 'الصفحة غير موجودة' }}</p>
        </div>
        <div class="px-6 sm:px-8 pb-6 sm:pb-8 -mt-6">
            <div class="rounded-2xl border border-surface-300 bg-surface-50 px-5 py-5 text-center">
                <h1 class="text-lg font-extrabold text-ink-800 leading-snug">عذراً، الصفحة غير موجودة</h1>
                <p class="mt-1.5 text-[13px] leading-6 text-ink-400">الرابط الذي اتبعته غير صحيح أو تم حذف الصفحة. لا تقلق، يمكنك العودة للمكان الصحيح.</p>
                @isset($wanted)
                    <p class="mt-2 truncate font-mono text-[11px] text-ink-300" dir="ltr">/{{ $wanted }}</p>
                @endisset
                @if(isset($exception) && config('app.debug'))
                    <p class="mt-2 text-[11px] text-red-500 font-mono break-all">{{ $exception->getMessage() }}</p>
                @endif
            </div>
            <div class="mt-4 grid gap-2">
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="history.length>1?history.back():window.location.href='{{ auth()->check() ? route('notes.index') : route('login') }}'" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-surface-300 bg-white px-4 py-2.5 text-sm font-bold text-ink-500 hover:bg-surface-100 transition">
                        {{ __('ui.redirect_back') }}
                    </button>
                    <a href="{{ auth()->check() ? route('notes.index') : route('login') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-sage-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-sage-700 transition">
                        {{ __('ui.redirect_home') }}
                    </a>
                </div>
                <p class="text-center text-[11px] text-ink-300 mt-1">كود الخطأ: 404 — لم يتم التوجيه إلى الإشعارات</p>
            </div>
        </div>
    </section>
</div>
@endsection
