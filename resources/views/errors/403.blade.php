@extends('layouts.app')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-8">
    <section class="w-full max-w-md overflow-hidden rounded-3xl border border-surface-300 bg-white shadow-sm">
        <div class="relative overflow-hidden bg-gradient-to-l from-amber-600 via-[#d97706] to-[#92400e] px-6 pt-8 pb-12 text-center">
            <div class="pointer-events-none absolute -top-14 -left-12 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
            <div class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border border-white/30 bg-white/15 text-white backdrop-blur-sm shadow-lg font-extrabold text-2xl">403</div>
            <p class="relative mt-3 text-sm font-bold text-white/90">لا تملك صلاحية</p>
        </div>
        <div class="px-6 sm:px-8 pb-6 sm:pb-8 -mt-6">
            <div class="rounded-2xl border border-surface-300 bg-surface-50 px-5 py-5 text-center">
                <h1 class="text-lg font-extrabold text-ink-800 leading-snug">لا تملك صلاحية الوصول</h1>
                <p class="mt-1.5 text-[13px] leading-6 text-ink-400">هذه الصفحة مخصصة لدور آخر — سجّل الدخول بالحساب المناسب.</p>
            </div>
            <div class="mt-4 grid gap-2">
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="history.back()" class="inline-flex items-center justify-center rounded-xl border border-surface-300 bg-white px-4 py-2.5 text-sm font-bold text-ink-500 hover:bg-surface-100 transition">{{ __('ui.redirect_back') }}</button>
                    <a href="{{ auth()->check() ? route('notes.index') : route('login') }}" class="inline-flex items-center justify-center rounded-xl bg-sage-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sage-700 transition">{{ __('ui.redirect_home') }}</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
