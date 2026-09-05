@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('profile.show') }}" class="w-9 h-9 rounded-lg bg-white border border-surface-300 flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-surface-100 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-extrabold text-ink-800 leading-none">ترتيب المراقبين</h1>
            <p class="text-sm text-ink-400 mt-1">إحصائيات حسب عدد الملاحظات المقبولة — الأكثر قبولاً أولاً</p>
        </div>
    </div>

    {{-- Global stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-surface-300 p-4 text-center">
            <div class="text-2xl font-extrabold text-ink-800">{{ $globalStats['total'] }}</div>
            <div class="text-xs font-bold text-ink-400">الإجمالي</div>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-200 p-4 text-center">
            <div class="text-2xl font-bold text-amber-700">{{ $globalStats['pending'] }}</div>
            <div class="text-xs font-bold text-amber-600">قيد المراجعة</div>
        </div>
        <div class="bg-sage-50 rounded-xl border border-sage-200 p-4 text-center">
            <div class="text-2xl font-bold text-sage-700">{{ $globalStats['accepted'] }}</div>
            <div class="text-xs font-bold text-sage-600">مقبولة</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-200 p-4 text-center">
            <div class="text-2xl font-bold text-red-600">{{ $globalStats['rejected'] }}</div>
            <div class="text-xs font-bold text-red-500">مرفوضة</div>
        </div>
        <div class="bg-surface-50 rounded-xl border border-surface-300 p-4 text-center">
            <div class="text-2xl font-bold text-ink-600">{{ $globalStats['draft'] }}</div>
            <div class="text-xs font-bold text-ink-400">مسودات</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-surface-300 overflow-hidden">
        <div class="px-6 py-4 border-b border-surface-300 bg-surface-50 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-ink-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-sage-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                ترتيب المراقبين (حسب المقبول)
            </h2>
            <span class="text-xs text-ink-400">{{ $monitors->count() }} مراقب</span>
        </div>

        @if($monitors->isEmpty())
            <div class="p-10 text-center text-ink-400 text-sm">لا يوجد مراقبون بعد</div>
        @else
            <div class="divide-y divide-surface-200">
                @foreach($monitors as $index => $monitor)
                    <div class="flex items-center gap-4 p-4 hover:bg-surface-50 transition">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-extrabold shrink-0
                            @if($index===0) bg-amber-100 text-amber-700 border border-amber-200
                            @elseif($index===1) bg-ink-100 text-ink-600 border border-surface-300
                            @elseif($index===2) bg-orange-50 text-orange-700 border border-orange-200
                            @else bg-surface-100 text-ink-500 border border-surface-300 @endif">
                            {{ $index+1 }}
                        </div>
                        <a href="{{ route('profile.showUser', $monitor->id) }}" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80 transition">
                            @if($monitor->avatar_url)
                                <img src="{{ $monitor->avatar_url }}" alt="{{ $monitor->name }}" class="w-10 h-10 rounded-xl object-cover border border-surface-300 shrink-0">
                            @else
                                <div class="w-10 h-10 rounded-xl bg-sage-100 text-sage-700 flex items-center justify-center font-bold shrink-0">{{ $monitor->initial }}</div>
                            @endif
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-800 truncate">{{ $monitor->name }}</div>
                                <div class="text-xs text-ink-400 font-mono" dir="ltr">{{ '@' . $monitor->username }} @if($monitor->personal_number) • {{ $monitor->personal_number }} @endif</div>
                            </div>
                        </a>
                        <div class="hidden sm:flex items-center gap-2 text-center">
                            <div class="w-14">
                                <div class="text-sm font-extrabold text-ink-800">{{ $monitor->total_notes }}</div>
                                <div class="text-[11px] text-ink-400">الكل</div>
                            </div>
                            <div class="w-14">
                                <div class="text-sm font-bold text-sage-600">{{ $monitor->accepted_notes }}</div>
                                <div class="text-[11px] text-ink-400">مقبولة</div>
                            </div>
                            <div class="w-14 hidden lg:block">
                                <div class="text-sm font-bold text-amber-600">{{ $monitor->rating }}</div>
                                <div class="text-[11px] text-ink-400">تقييم</div>
                            </div>
                            <div class="w-20 hidden lg:flex flex-col items-center">
                                <div class="text-xs font-bold text-amber-600 tracking-widest">{{ $monitor->rating_stars }}</div>
                                <div class="text-[11px] text-ink-400">{{ $monitor->rating_label }}</div>
                            </div>
                            <div class="w-14">
                                <div class="text-sm font-bold text-ink-700">{{ $monitor->total_notes > 0 ? round($monitor->accepted_notes / $monitor->total_notes * 100) : 0 }}%</div>
                                <div class="text-[11px] text-ink-400">قبول</div>
                            </div>
                        </div>
                        <a href="{{ route('profile.showUser', $monitor->id) }}" class="hidden sm:inline-flex px-3 py-1.5 rounded-lg bg-white border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">عرض</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-6 flex justify-center">
        <a href="{{ route('notes.index') }}" class="px-5 py-2.5 rounded-xl bg-white border border-surface-300 text-ink-600 font-bold text-sm hover:bg-surface-100 transition">العودة للملاحظات</a>
    </div>
</div>
@endsection
