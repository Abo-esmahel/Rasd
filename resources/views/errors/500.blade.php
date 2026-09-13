@extends('layouts.app')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-8">
    <section class="w-full max-w-md overflow-hidden rounded-3xl border border-surface-300 bg-white shadow-sm">
        <div class="relative overflow-hidden bg-gradient-to-l from-ink-700 via-[#1f2937] to-[#111827] px-6 pt-8 pb-12 text-center">
            <div class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-3xl border border-white/20 bg-white/10 text-white backdrop-blur-sm shadow-lg font-extrabold text-2xl">500</div>
            <p class="relative mt-3 text-sm font-bold text-white/80">خطأ في الخادم</p>
        </div>
        <div class="px-6 sm:px-8 pb-6 sm:pb-8 -mt-6">
            <div class="rounded-2xl border border-surface-300 bg-surface-50 px-5 py-5 text-center">
                <h1 class="text-lg font-extrabold text-ink-800 leading-snug">حدث خطأ غير متوقع</h1>
                <p class="mt-1.5 text-[13px] leading-6 text-ink-400">نعمل على إصلاحه — حاول تحديث الصفحة أو العودة لاحقاً.</p>
                @if(isset($exception) && config('app.debug'))
                    <p class="mt-2 text-[11px] text-red-500 font-mono break-all">{{ $exception->getMessage() }}</p>
                @endif
            </div>
            <div class="mt-4 grid gap-2">
                <button type="button" onclick="location.reload()" class="inline-flex items-center justify-center rounded-xl bg-sage-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sage-700 transition">إعادة المحاولة</button>
                <a href="{{ auth()->check() ? route('notes.index') : route('login') }}" class="inline-flex items-center justify-center rounded-xl border border-surface-300 bg-white px-4 py-2.5 text-sm font-bold text-ink-500 hover:bg-surface-100 transition">{{ __('ui.redirect_home') }}</a>
            </div>
        </div>
    </section>
</div>
@endsection
