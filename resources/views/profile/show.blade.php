@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('notes.index') }}" class="w-9 h-9 rounded-lg bg-white border border-surface-300 flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-surface-100 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-extrabold text-ink-800 leading-none">{{ $isOwn ?? true ? 'الملف الشخصي' : 'ملف ' . $user->name }}</h1>
            <p class="text-sm text-ink-400 mt-1">{{ $isOwn ?? true ? 'بيانات حسابك ودورك في النظام' : 'عرض ملف مستخدم آخر — مسموح للجميع' }}</p>
        </div>
        <a href="{{ route('ranking') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 8V3H8m8 0v2m-8 0v2"/></svg>
            الترتيب
        </a>
    </div>

    <div class="grid lg:grid-cols-[300px_1fr] gap-5">
        {{-- Sidebar card --}}
        <div class="bg-white rounded-2xl border border-surface-300 overflow-hidden p-6 text-center">
            @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="mx-auto w-[88px] h-[88px] rounded-2xl object-cover border-2 border-surface-300 shadow-sm">
            @else
                <div class="mx-auto w-[88px] h-[88px] rounded-2xl bg-sage-100 flex items-center justify-center text-sage-700 text-2xl font-extrabold">
                    {{ $user->initial }}
                </div>
            @endif
            <h2 class="mt-3.5 text-base font-extrabold text-ink-800">{{ $user->name }}</h2>
            <p class="text-sm text-ink-400 mt-0.5" dir="ltr">{{ '@' . $user->username }}</p>
            @if($user->personal_number)
                <p class="mt-1 text-xs font-mono font-bold text-ink-500 tracking-widest" dir="ltr">{{ $user->personal_number }}</p>
            @else
                <p class="mt-1 text-xs text-ink-300">بدون رقم شخصي</p>
            @endif
            <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold {{ $user->isMonitor() ? 'bg-sage-50 text-sage-700 border border-sage-200' : 'bg-surface-100 text-ink-600 border border-surface-300' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $user->isMonitor() ? 'bg-sage-500' : 'bg-ink-400' }}"></span>
                {{ $user->isMonitor() ? 'مُراقب ميداني' : 'كاتب تقارير' }}
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2.5 text-center">
                <div class="rounded-xl bg-surface-50 border border-surface-300 p-2.5">
                    <div class="text-[11px] font-bold text-ink-300">الحالة</div>
                    <div class="text-xs font-bold text-sage-600 mt-0.5">نشط</div>
                </div>
                <div class="rounded-xl bg-surface-50 border border-surface-300 p-2.5">
                    <div class="text-[11px] font-bold text-ink-300">عضو منذ</div>
                    <div class="text-xs font-bold text-ink-700 mt-0.5">{{ $user->created_at->format('Y-m-d') }}</div>
                </div>
            </div>

            {{-- إحصائيات هادئة ومتناسقة --}}
            @if(isset($stats))
                <div class="mt-4 rounded-xl border border-surface-300 overflow-hidden">
                    <div class="px-3 py-2.5 bg-surface-50 border-b border-surface-300 flex items-center justify-between">
                        <span class="text-xs font-bold text-ink-700">الملاحظات</span>
                        <span class="text-[11px] font-mono text-ink-400">{{ $stats['total'] }} إجمالي</span>
                    </div>
                    <div class="p-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-ink-500">مقبولة</span>
                            <span class="font-bold text-ink-800">{{ $stats['accepted'] }} <span class="font-normal text-ink-400">· {{ $stats['total'] > 0 ? round($stats['accepted']/max(1,$stats['total'])*100) : 0 }}%</span></span>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-surface-100 overflow-hidden flex">
                            <div class="bg-sage-500 h-full" style="width: {{ $stats['total']>0 ? round($stats['accepted']/$stats['total']*100) : 0 }}%"></div>
                            @if($user->isMonitor())
                                <div class="bg-amber-400 h-full" style="width: {{ $stats['total']>0 ? round($stats['pending']/$stats['total']*100) : 0 }}%"></div>
                                <div class="bg-red-400 h-full" style="width: {{ $stats['total']>0 ? round($stats['rejected']/$stats['total']*100) : 0 }}%"></div>
                            @endif
                        </div>
                        @if($user->isMonitor())
                            <div class="mt-3 p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-center">
                                <div class="text-[11px] font-bold text-amber-700 tracking-widest">تقييم المراقب</div>
                                <div class="mt-1 text-lg font-bold text-amber-600 tracking-[0.15em]">{{ $user->rating_stars }}</div>
                                <div class="text-xs font-bold text-ink-700">{{ $user->rating }} / 5 — {{ $user->rating_label }}</div>
                                <div class="mt-1 text-[11px] text-ink-400">بناءً على {{ $stats['total'] }} ملاحظة</div>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center text-[11px]">
                                <div class="py-2 rounded-lg bg-white border border-surface-300">
                                    <div class="font-bold text-ink-600">{{ $stats['pending'] }}</div>
                                    <div class="text-ink-400">قيد المراجعة</div>
                                </div>
                                <div class="py-2 rounded-lg bg-white border border-surface-300">
                                    <div class="font-bold text-ink-600">{{ $stats['rejected'] }}</div>
                                    <div class="text-ink-400">مرفوضة</div>
                                </div>
                                <div class="py-2 rounded-lg bg-white border border-surface-300">
                                    <div class="font-bold text-ink-600">{{ $stats['draft'] }}</div>
                                    <div class="text-ink-400">مسودة</div>
                                </div>
                            </div>
                        @else
                            {{-- كاتب التقرير — فقط مسودات ومقبولة --}}
                            <div class="mt-3 grid grid-cols-2 gap-2 text-center text-[11px]">
                                <div class="py-2 rounded-lg bg-white border border-surface-300">
                                    <div class="font-bold text-ink-600">{{ $stats['draft'] }}</div>
                                    <div class="text-ink-400">مسودة</div>
                                </div>
                                <div class="py-2 rounded-lg bg-white border border-surface-300">
                                    <div class="font-bold text-sage-600">{{ $stats['accepted'] }}</div>
                                    <div class="text-ink-400">مقبولة</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if($isOwn ?? true)
                <a href="{{ route('profile.edit') }}" class="mt-5 w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl text-white font-bold text-sm transition shadow-sm" style="background-color:#1f6f4a">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    تعديل البيانات
                </a>
            @else
                <a href="{{ route('notes.index') }}?observer={{ $user->id }}" class="mt-5 w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl text-white font-bold text-sm transition shadow-sm" style="background-color:#1f6f4a">
                    عرض ملاحظاته
                </a>
            @endif
        </div>

        {{-- Details --}}
        <div class="bg-white rounded-2xl border border-surface-300 overflow-hidden">
            <div class="px-6 py-4 border-b border-surface-300 flex items-center justify-between bg-surface-50">
                <h3 class="text-sm font-extrabold text-ink-800">معلومات الحساب</h3>
                <span class="text-xs text-ink-300 font-mono">ID #{{ $user->id }}</span>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="rounded-xl bg-surface-50 border border-surface-300 p-3.5">
                        <div class="text-xs font-bold text-ink-300 mb-1">الاسم الكامل</div>
                        <div class="text-sm font-bold text-ink-800">{{ $user->name }}</div>
                    </div>
                    <div class="rounded-xl bg-surface-50 border border-surface-300 p-3.5">
                        <div class="text-xs font-bold text-ink-300 mb-1">اسم المستخدم</div>
                        <div class="text-sm font-bold text-ink-800 font-mono text-left" dir="ltr">{{ $user->username }}</div>
                    </div>
                    <div class="rounded-xl bg-surface-50 border border-surface-300 p-3.5">
                        <div class="text-xs font-bold text-ink-300 mb-1">الرقم الشخصي</div>
                        @if($user->personal_number)
                            <div class="text-sm font-bold text-ink-800 font-mono tracking-widest" dir="ltr">{{ $user->personal_number }}</div>
                        @else
                            <div class="text-sm font-bold text-ink-300">— غير محدد</div>
                        @endif
                    </div>
                    <div class="rounded-xl bg-surface-50 border border-surface-300 p-3.5">
                        <div class="text-xs font-bold text-ink-300 mb-1">الدور</div>
                        <div class="text-sm font-bold {{ $user->isMonitor() ? 'text-sage-600' : 'text-ink-700' }}">{{ $user->isMonitor() ? 'مراقب — إنشاء ومتابعة الملاحظات' : 'كاتب تقارير — اعتماد ورفض' }}</div>
                    </div>
                    <div class="rounded-xl bg-surface-50 border border-surface-300 p-3.5 sm:col-span-2">
                        <div class="text-xs font-bold text-ink-300 mb-1">الجهة</div>
                        <div class="text-sm font-bold text-ink-800">وزارة الإعلام — سورية</div>
                    </div>
                </div>

                <div class="rounded-xl bg-sage-50 border border-sage-200 p-4 flex gap-3">
                    <div class="w-7 h-7 rounded-lg bg-sage-100 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-sage-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-sm leading-6 text-sage-700">
                        <span class="font-bold text-ink-700">صلاحياتك:</span>
                        @if($user->isMonitor())
                            يمكنك إنشاء مسودات، تعديل ملاحظاتك، إرسال وإعادة إرسال. لا يمكنك اعتماد أو رفض.
                        @else
                            يمكنك عرض كل الملاحظات ما عدا المسودات، وقبول أو رفض الملاحظات قيد المراجعة.
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    @if($isOwn ?? true)
                        <a href="{{ route('profile.edit') }}" class="px-5 py-2.5 rounded-xl text-white font-bold text-sm transition shadow-sm" style="background-color:#1f6f4a">تعديل الملف</a>
                    @endif
                    <a href="{{ route('notes.index') }}" class="px-5 py-2.5 rounded-xl border border-surface-300 bg-white text-ink-500 font-medium text-sm hover:bg-surface-100 transition">العودة للملاحظات</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
