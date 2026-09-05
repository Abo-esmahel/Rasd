@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Header — نفس لغة notes/index --}}
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('profile.show') }}" class="w-9 h-9 rounded-lg bg-white border border-surface-300 flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-surface-100 transition shrink-0" aria-label="رجوع">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-extrabold text-ink-800 leading-none">ترتيب المراقبين</h1>
            <p class="text-sm text-ink-400 mt-1">حسب عدد الملاحظات المقبولة — الأكثر قبولاً أولاً</p>
        </div>
        <span class="shrink-0 px-2.5 py-1.5 rounded-lg bg-white border border-surface-300 text-xs font-bold text-ink-500 tabular-nums">{{ $monitors->count() }} مراقب</span>
    </div>

    {{-- Overview — بطاقة واحدة هادئة بدل 5 بطاقات ملونة --}}
    <div class="bg-white rounded-2xl border border-surface-300 overflow-hidden mb-5">
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-x-reverse divide-surface-300">
            @php
                $overview = [
                    ['label' => 'إجمالي الملاحظات', 'value' => $globalStats['total'] ?? 0, 'accent' => false],
                    ['label' => 'مقبولة', 'value' => $globalStats['accepted'] ?? 0, 'accent' => true],
                    ['label' => 'قيد المراجعة', 'value' => $globalStats['pending'] ?? 0, 'accent' => false],
                    ['label' => 'مرفوضة', 'value' => $globalStats['rejected'] ?? 0, 'accent' => false],
                ];
                $accRate = ($globalStats['total'] ?? 0) > 0 ? round(($globalStats['accepted'] ?? 0) / max(1, $globalStats['total']) * 100) : 0;
            @endphp
            @foreach($overview as $item)
                <div class="px-4 py-3.5 text-center">
                    <div class="text-xl font-extrabold tabular-nums leading-none {{ $item['accent'] ? 'text-sage-600' : 'text-ink-800' }}">{{ $item['value'] }}</div>
                    <div class="mt-1.5 text-[11px] font-bold text-ink-400">{{ $item['label'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="px-4 sm:px-5 pb-4">
            <div class="flex items-center justify-between text-[11px] font-bold text-ink-400 mb-1.5">
                <span>نسبة القبول العامة</span>
                <span class="tabular-nums text-ink-600">{{ $accRate }}%</span>
            </div>
            <div class="h-1.5 rounded-full bg-surface-100 overflow-hidden">
                <div class="h-full rounded-full bg-sage-500" style="width: {{ $accRate }}%"></div>
            </div>
        </div>
    </div>

    {{-- Podium — الأول فقط مميز بهدوء، بدون ذهبي صارخ --}}
    @if($monitors->count() >= 1 && ($monitors->first()->accepted_notes ?? 0) > 0)
        @php $top = $monitors->first(); $topRate = $top->total_notes > 0 ? round($top->accepted_notes / $top->total_notes * 100) : 0; @endphp
        <a href="{{ route('profile.showUser', $top->id) }}" class="block bg-white rounded-2xl border border-sage-200 overflow-hidden mb-5 hover:border-sage-300 transition group">
            <div class="px-5 py-4 flex items-center gap-4">
                @if($top->avatar_url)
                    <img src="{{ $top->avatar_url }}" alt="{{ $top->name }}" class="w-12 h-12 rounded-full object-cover border-2 border-sage-200 shrink-0">
                @else
                    <div class="w-12 h-12 rounded-full bg-sage-600 text-white flex items-center justify-center text-lg font-extrabold shrink-0">{{ $top->initial }}</div>
                @endif
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-sage-50 border border-sage-200 text-sage-700 text-[11px] font-extrabold shrink-0">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 1l2.4 4.9 5.4.8-3.9 3.8.9 5.4L10 13.4 5.2 15.9l.9-5.4L2.2 6.7l5.4-.8L10 1z"/></svg>
                            الأول
                        </span>
                        <span class="text-[15px] font-extrabold text-ink-800 truncate group-hover:text-sage-700 transition">{{ $top->name }}</span>
                    </div>
                    <div class="mt-1 text-xs text-ink-400 truncate" dir="ltr">{{ '@' . $top->username }}</div>
                </div>
                <div class="text-left shrink-0">
                    <div class="text-2xl font-extrabold text-ink-800 tabular-nums leading-none">{{ $top->accepted_notes }}</div>
                    <div class="mt-1 text-[11px] font-bold text-ink-400">ملاحظة مقبولة · {{ $topRate }}%</div>
                </div>
            </div>
        </a>
    @endif

    {{-- List — صفوف هادئة قابلة للنقر بالكامل --}}
    <div class="bg-white rounded-2xl border border-surface-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-surface-300 bg-surface-50">
            <h2 class="text-[13px] font-extrabold text-ink-700">كل المراقبين</h2>
        </div>

        @if($monitors->isEmpty())
            <div class="px-6 py-12 text-center">
                <div class="mx-auto w-11 h-11 rounded-xl bg-surface-100 border border-surface-300 flex items-center justify-center">
                    <svg class="w-5 h-5 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6-4a3 3 0 11-3-3"/></svg>
                </div>
                <p class="mt-3 text-sm font-bold text-ink-600">لا يوجد مراقبون بعد</p>
                <p class="mt-1 text-xs text-ink-400">سيظهر الترتيب هنا فور إضافة مراقبين وملاحظات</p>
            </div>
        @else
            <div class="divide-y divide-surface-200">
                @foreach($monitors as $index => $monitor)
                    @php
                        $rate = $monitor->total_notes > 0 ? round($monitor->accepted_notes / $monitor->total_notes * 100) : 0;
                        $isTopThree = $index < 3;
                    @endphp
                    <a href="{{ route('profile.showUser', $monitor->id) }}" class="flex items-center gap-3.5 px-4 sm:px-5 py-3.5 hover:bg-surface-50 transition group">
                        {{-- Rank — رقم هادئ، حلقة خضراء خفيفة للأوائل فقط --}}
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-[13px] font-extrabold tabular-nums shrink-0 border {{ $isTopThree ? 'bg-sage-50 text-sage-700 border-sage-200' : 'bg-white text-ink-400 border-surface-300' }}">{{ $index + 1 }}</span>

                        @if($monitor->avatar_url)
                            <img src="{{ $monitor->avatar_url }}" alt="{{ $monitor->name }}" class="w-10 h-10 rounded-full object-cover border border-surface-300 shrink-0">
                        @else
                            <div class="w-10 h-10 rounded-full bg-surface-100 border border-surface-300 text-ink-600 flex items-center justify-center font-extrabold shrink-0">{{ $monitor->initial }}</div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-ink-800 truncate group-hover:text-sage-700 transition">{{ $monitor->name }}</div>
                            <div class="mt-1 flex items-center gap-2 min-w-0">
                                <div class="flex-1 h-1 rounded-full bg-surface-100 overflow-hidden min-w-[40px] max-w-[160px]">
                                    <div class="h-full rounded-full bg-sage-500" style="width: {{ $rate }}%"></div>
                                </div>
                                <span class="text-[11px] font-bold text-ink-400 tabular-nums shrink-0">{{ $rate }}% قبول</span>
                            </div>
                        </div>

                        <div class="text-left shrink-0 min-w-[64px]">
                            <div class="text-base font-extrabold tabular-nums leading-none {{ $monitor->accepted_notes > 0 ? 'text-ink-800' : 'text-ink-300' }}">{{ $monitor->accepted_notes }}</div>
                            <div class="mt-1 text-[11px] text-ink-400 tabular-nums">من {{ $monitor->total_notes }}</div>
                        </div>

                        <svg class="w-4 h-4 text-ink-300 group-hover:text-sage-600 group-hover:-translate-x-0.5 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <p class="mt-4 text-center text-[11px] text-ink-400">يُحتسب الترتيب من الملاحظات المقبولة فقط · يُحدَّث تلقائياً</p>
</div>
@endsection
