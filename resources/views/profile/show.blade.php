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
        
        <div class="bg-white rounded-2xl border border-surface-300 overflow-hidden p-6 text-center">
            @if($user->avatar_url)
                <div class="mx-auto shrink-0" style="width:160px;height:160px;flex-shrink:0;">
                    <button type="button" onclick="openModal('avatar-view-modal')" class="block rounded-full overflow-hidden border-2 border-surface-300 shadow-sm hover:opacity-90 transition cursor-zoom-in" style="width:160px;height:160px;border-radius:9999px;overflow:hidden;padding:0;" aria-label="عرض الصورة بحجم كامل" title="عرض الصورة">
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="object-cover" style="width:100%;height:100%;object-fit:cover;display:block;">
                    </button>
                </div>
                <div id="avatar-view-modal" data-modal class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" onclick="closeModal('avatar-view-modal')"></div>
                    <div class="relative w-full max-w-lg">
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full max-h-[80vh] object-contain rounded-2xl shadow-2xl border border-white/20 bg-ink-900">
                        <button type="button" onclick="closeModal('avatar-view-modal')" aria-label="إغلاق" class="absolute -top-3 -left-3 w-9 h-9 rounded-full bg-white text-ink-600 shadow-lg flex items-center justify-center hover:text-ink-900 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            @else
                <div class="mx-auto rounded-full bg-sage-100 border-2 border-surface-300 flex items-center justify-center text-sage-700 text-4xl font-extrabold" style="width:160px;height:160px;border-radius:9999px;flex-shrink:0;">
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

            
            @if(isset($stats))
                <div class="mt-5 pt-4 border-t border-surface-200">
                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-surface-200 text-center">
                        <div class="px-2">
                            <div class="text-lg font-extrabold text-ink-800 tabular-nums leading-none">{{ $stats['total'] }}</div>
                            <div class="mt-1.5 text-[11px] font-medium text-ink-400">إجمالي</div>
                        </div>
                        <div class="px-2">
                            <div class="text-lg font-extrabold text-ink-800 tabular-nums leading-none">{{ $stats['accepted'] }}</div>
                            <div class="mt-1.5 text-[11px] font-medium text-ink-400">مقبولة</div>
                        </div>
                        <div class="px-2">
                            @if($user->isMonitor())
                                <div class="text-lg font-extrabold text-ink-800 tabular-nums leading-none">{{ $stats['pending'] }}</div>
                                <div class="mt-1.5 text-[11px] font-medium text-ink-400">قيد المراجعة</div>
                            @else
                                <div class="text-lg font-extrabold text-ink-800 tabular-nums leading-none">{{ $stats['draft'] }}</div>
                                <div class="mt-1.5 text-[11px] font-medium text-ink-400">مسودة</div>
                            @endif
                        </div>
                    </div>
                    @if($stats['total'] > 0)
                        <p class="mt-3 text-center text-[11px] text-ink-400 tabular-nums">نسبة القبول {{ round($stats['accepted'] / max(1, $stats['total']) * 100) }}%</p>
                    @endif
                </div>
            @endif

            @if(!($isOwn ?? true))
                <a href="{{ route('notes.index') }}?observer={{ $user->id }}" class="mt-5 w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl text-white font-bold text-sm transition shadow-sm" style="background-color:#1f6f4a">
                    عرض ملاحظاته
                </a>
            @endif
        </div>

        
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
