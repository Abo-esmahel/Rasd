@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-extrabold text-ink-800">الإشعارات</h1>
            <p class="text-sm text-ink-400 mt-1">إشعارات رفض الملاحظات وأسبابها</p>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.markRead') }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-white border border-surface-300 text-ink-600 text-sm font-bold hover:bg-surface-100 transition">تحديد الكل كمقروء</button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="bg-white rounded-2xl border border-surface-300 p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-surface-100 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.586a1 1 0 011.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="mt-4 text-base font-bold text-ink-700">لا توجد إشعارات</h3>
            <p class="mt-1.5 text-sm text-ink-400">عندما يتم رفض إحدى ملاحظاتك، سيصلك إشعار هنا وفي شريط النظام</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($notifications as $notification)
                <div class="bg-white rounded-xl border {{ is_null($notification->read_at) ? 'border-red-200 bg-red-50/30' : 'border-surface-300' }} p-4 flex gap-3">
                    <div class="w-10 h-10 rounded-xl {{ is_null($notification->read_at) ? 'bg-red-50 border border-red-200 text-red-500' : 'bg-surface-100 border border-surface-300 text-ink-400' }} flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-ink-800">{{ $notification->data['message'] ?? 'تم رفض ملاحظتك' }}</p>
                        <div class="mt-2 p-3 rounded-xl bg-white border border-surface-300">
                            <div class="text-xs font-bold text-ink-500 mb-1">سبب الرفض:</div>
                            <p class="text-sm leading-6 text-ink-700">{{ $notification->data['reason'] ?? '—' }}</p>
                        </div>
                        <div class="mt-2 flex items-center gap-3 text-xs text-ink-400">
                            <span>{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</span>
                            <span>•</span>
                            <span>كاميرا {{ $notification->data['camera_number'] ?? '—' }} — الطابق {{ $notification->data['floor_number'] ?? '—' }}</span>
                            @if(is_null($notification->read_at))
                                <span class="mr-auto inline-flex items-center gap-1 text-red-500 font-bold"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>غير مقروء</span>
                            @else
                                <span class="mr-auto text-ink-300">مقروء</span>
                            @endif
                        </div>
                        <div class="mt-3 flex gap-2">
                            <a href="{{ route('notes.index') }}?status=rejected" class="px-3 py-1.5 rounded-lg bg-white border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">عرض الملاحظة</a>
                            @if(is_null($notification->read_at))
                                <form method="POST" action="{{ route('notifications.markOneRead', $notification->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-sage-600 text-white text-xs font-bold hover:bg-sage-700 transition">تحديد كمقروء</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
