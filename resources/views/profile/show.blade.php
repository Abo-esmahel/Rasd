@extends('layouts.app')

@section('content')
@php
    $isMonitor = $user->isMonitor();
    $total = isset($stats) ? (int) ($stats['total'] ?? 0) : 0;
    $notesUrl = ($isOwn ?? true) ? route('notes.index') : route('notes.index', ['observer' => $user->id]);
@endphp
<div class="max-w-3xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('notes.index') }}" aria-label="{{ __('ui.back') }}" class="w-9 h-9 rounded-xl bg-white border border-surface-300 inline-flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-surface-100 transition">
            <svg class="w-4 h-4 ltr:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>

    {{-- Profile Header: Cover + Avatar متداخل مع الحافة السفلية (نصفه فوق Cover ونصفه خارجه).
        سبب الإصلاح: كان الـAvatar عنصراً في الـflow بعد الـCover مع -mt فقط، فكانت نسبة
        الـoverlap هشة ومربوطة بقيمتين ثابتتين. الآن الـAvatar مُثبّت بـ absolute داخل
        anchor نسبي، ومركزه العمودي منطبق تماماً على حافة الـCover السفلية، وموضعه الأفقي
        بـ inset-inline-start فينعكس تلقائياً (يمين في RTL / يسار في LTR). --}}
    <style>
        .profile-hero{
            --cover-h: clamp(216px, 48vw, 288px);
            --avatar-size: clamp(160px, 40vw, 232px);
            --avatar-inset: clamp(16px, 4vw, 32px);
            --hero-gap: clamp(10px, 2.5vw, 16px);
            /* نسبة الجزء الخارج من الـAvatar تحت حافة الـCover — الباقي فوقها (مرفوع) */
            --avatar-below: 0.35;
        }
        .profile-cover-anchor{ position: relative; }
        .profile-cover{
            position: relative;
            overflow: hidden;
            height: var(--cover-h);
        }
        .profile-avatar{
            position: absolute;
            z-index: 2;
            /* أفقي منطقي: يمين في RTL / يسار في LTR — بدون left/right ثابت */
            inset-inline-start: var(--avatar-inset);
            /* مرفوع: ~65% فوق الـCover و~35% خارجه */
            bottom: calc(var(--avatar-size) * var(--avatar-below) * -1);
            width: var(--avatar-size);
            height: var(--avatar-size);
            border-radius: 9999px;
        }
        .profile-identity{
            /* حجز مساحة الجزء الخارج فقط + فجوة تنفس — فلا يصطدم بالنص ولا يترك فراغاً ضخماً */
            padding-top: calc((var(--avatar-size) * var(--avatar-below)) + var(--hero-gap));
        }
        .profile-total-num{
            font-size: clamp(2.5rem, 10vw, 3rem);
        }
    </style>
    <header class="profile-hero">
        <div class="profile-cover-anchor">
            <div class="profile-cover rounded-2xl bg-gradient-to-l from-[#175c34] via-[#0d5c31] to-[#083a20] dark:from-[#0c2f1d] dark:via-[#0a2417] dark:to-[#060f0a]">
                <div class="pointer-events-none absolute -start-12 -top-24 h-64 w-64 rounded-full border-[12px] border-white/10" aria-hidden="true"></div>
                <div class="pointer-events-none absolute end-16 -bottom-32 h-80 w-80 rounded-full border border-white/10" aria-hidden="true"></div>
                <div class="pointer-events-none absolute end-44 top-0 h-40 w-40 rounded-full border-[6px] border-white/[0.07]" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-20 start-1/3 h-56 w-56 rounded-full bg-white/[0.07] blur-2xl" aria-hidden="true"></div>
            </div>
            <div class="profile-avatar">
                @if($user->avatar_url)
                    <button type="button" onclick="openModal('avatar-view-modal')" class="block h-full w-full overflow-hidden rounded-full ring-4 ring-white dark:ring-[#1e2320] shadow-lg hover:opacity-95 transition bg-white p-0 cursor-pointer" aria-label="{{ __('ui.view_full_image') }}">
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                    </button>
                @else
                    <div class="flex h-full w-full items-center justify-center rounded-full ring-4 ring-white dark:ring-[#1e2320] bg-[#dfe9df] text-[#14532d] dark:bg-[#1f6f4a] dark:text-white font-extrabold shadow-lg" style="font-size: calc(var(--avatar-size) * 0.38)" aria-hidden="true">
                        {{ $user->initial }}
                    </div>
                @endif
            </div>
        </div>
        <div class="profile-identity text-start">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-[22px] sm:text-2xl font-extrabold text-ink-800 leading-snug truncate">{{ $user->name }}</h1>
                    <p class="mt-1 text-[13px] font-bold text-ink-400">{{ $isMonitor ? __('ui.role_monitor') : __('ui.role_writer') }}</p>
                </div>
                @if($isOwn ?? true)
                <div class="shrink-0">
                    <a href="{{ route('profile.edit') }}" class="inline-block px-6 py-2.5 rounded-xl bg-sage-600 text-white text-sm font-bold hover:bg-sage-700 transition whitespace-nowrap">{{ __('ui.edit_profile') }}</a>
                </div>
                @endif
            </div>
        </div>
    </header>

    {{-- معلومات الحساب: Section واحدة متماسكة — عنوان + صفوف بفواصل خفيفة.
        أيقونات thin (stroke 1.5، ‏18px) كعنصر مساعد فقط، labels صغيرة وvalues أوضح. --}}
    <section class="mt-6" aria-labelledby="account-info-title">
        <div class="rounded-2xl border border-surface-300 bg-white px-5 dark:bg-[#252b26]">
            <h2 id="account-info-title" class="pt-4 pb-1 text-[13px] font-extrabold text-ink-500">{{ __('ui.account_info') }}</h2>
            <dl class="divide-y divide-surface-300">
                <div class="flex items-center gap-3 py-3.5">
                    <svg class="w-[18px] h-[18px] shrink-0 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <div class="min-w-0 flex-1">
                        <dt class="text-[11px] font-bold text-ink-400">{{ __('ui.username') }}</dt>
                        <dd class="mt-0.5 text-sm font-bold text-ink-800 truncate" dir="ltr">{{ '@' . $user->username }}</dd>
                    </div>
                </div>
                <div class="flex items-center gap-3 py-3.5">
                    <svg class="w-[18px] h-[18px] shrink-0 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <div class="min-w-0 flex-1">
                        <dt class="text-[11px] font-bold text-ink-400">{{ __('ui.personal_number') }}</dt>
                        @if($user->personal_number)
                            <dd class="mt-0.5 text-sm font-bold text-ink-800 tabular-nums tracking-widest" dir="ltr">{{ $user->personal_number }}</dd>
                        @else
                            <dd class="mt-0.5 text-sm font-bold text-ink-300">—</dd>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3 py-3.5">
                    <svg class="w-[18px] h-[18px] shrink-0 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5h1l1 1v4m-4 0h4"/></svg>
                    <div class="min-w-0 flex-1">
                        <dt class="text-[11px] font-bold text-ink-400">{{ __('ui.entity') }}</dt>
                        <dd class="mt-0.5 text-sm font-bold text-ink-800">{{ __('ui.ministry_syria') }}</dd>
                    </div>
                </div>
            </dl>
        </div>
    </section>

    {{-- إجمالي الملاحظات: جزء من التصميم — رقم كبير وتحته label صغير، بدون Cards إضافية --}}
    <section class="mt-6 text-center" aria-label="{{ __('ui.total_notes') }}">
        <div class="border-y border-surface-300 py-6 px-6">
        @if($total > 0)
        <a href="{{ $notesUrl }}" class="inline-block hover:opacity-70 transition">
        @else
        <div>
        @endif
            <div class="profile-total-num font-extrabold tabular-nums text-ink-800 leading-none">{{ $total }}</div>
            <div class="mt-1.5 text-xs font-bold text-ink-400">{{ __('ui.total_notes') }}</div>
        @if($total > 0)
        </a>
        @else
        </div>
        @endif
        </div>
    </section>

    {{-- لغة الواجهة --}}
    @if($isOwn ?? true)
    <section class="mt-6">
        <h2 class="px-1 mb-2 text-[13px] font-extrabold text-ink-500">{{ __('ui.language_title') }}</h2>
        <div class="flex rounded-xl border border-surface-300 bg-white p-1 gap-1 dark:bg-[#252b26]">
            <form method="POST" action="{{ route('locale.update') }}" class="flex-1">
                @csrf
                <input type="hidden" name="locale" value="ar">
                <button type="submit" data-lang-btn="ar" aria-pressed="{{ app()->getLocale() === 'ar' ? 'true' : 'false' }}" class="w-full px-4 py-2 rounded-lg text-sm font-extrabold transition {{ app()->getLocale() === 'ar' ? 'bg-sage-600 text-white shadow-sm' : 'text-ink-500 hover:bg-surface-100' }}">{{ __('ui.arabic') }}</button>
            </form>
            <form method="POST" action="{{ route('locale.update') }}" class="flex-1">
                @csrf
                <input type="hidden" name="locale" value="en">
                <button type="submit" data-lang-btn="en" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}" class="w-full px-4 py-2 rounded-lg text-sm font-extrabold transition {{ app()->getLocale() === 'en' ? 'bg-sage-600 text-white shadow-sm' : 'text-ink-500 hover:bg-surface-100' }}">{{ __('ui.english') }}</button>
            </form>
        </div>
    </section>
    @endif
</div>

{{-- نافذة الصورة المكبرة --}}
@if($user->avatar_url)
    <div id="avatar-view-modal" data-modal class="hidden fixed inset-0 z-[70] items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" onclick="closeModal('avatar-view-modal')"></div>
        <div class="relative w-full max-w-lg">
            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full max-h-[80vh] object-contain rounded-2xl shadow-2xl border border-white/20 bg-ink-900">
            <button type="button" onclick="closeModal('avatar-view-modal')" aria-label="{{ __('ui.close') }}" class="absolute -top-3 -end-3 w-9 h-9 rounded-full bg-white text-ink-600 shadow-lg flex items-center justify-center hover:text-ink-900 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
@endif
@endsection
