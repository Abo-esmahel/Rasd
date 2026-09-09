@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-extrabold text-ink-800">الإشعارات</h1>
            <p class="text-sm text-ink-400 mt-1">إشعارات الإرساليات والملاحظات — قبول ورفض وإرسال</p>
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
                @php
                    $data = $notification->data;
                    $type = $data['type'] ?? ($notification->type ?? '');
                    $isRejected = str_contains($type, 'reject') || str_contains(strtolower($type), 'rejected') || isset($data['reason']);
                    $isAccepted = str_contains($type, 'accept') || str_contains(strtolower($type), 'accepted');
                    $isSent = str_contains($type, 'sent') || str_contains($type, 'dispatch_sent');
                    $url = $data['url'] ?? null;
                    if (!$url) {
                        if (isset($data['note_id'])) $url = route('notes.show', $data['note_id']);
                        elseif (isset($data['submission_id'])) $url = route('general-submissions.show', $data['submission_id']);
                        elseif (isset($data['general_submission_id'])) $url = route('general-submissions.show', $data['general_submission_id']);
                        else $url = route('notifications.index');
                    }
                    $iconBg = is_null($notification->read_at)
                        ? ($isRejected ? 'bg-red-50 border border-red-200 text-red-500' : ($isAccepted ? 'bg-[#eef4f0] border border-[#cde7d6] text-[#0e6a38]' : 'bg-amber-50 border border-amber-200 text-amber-600'))
                        : 'bg-surface-100 border border-surface-300 text-ink-400';
                    $iconPath = $isRejected
                        ? 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                        : ($isAccepted ? 'M5 13l4 4L19 7' : 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' );
                @endphp
                <div class="bg-white rounded-xl border {{ is_null($notification->read_at) ? ($isRejected ? 'border-red-200 bg-red-50/30' : ($isAccepted ? 'border-[#cde7d6] bg-[#eef4f0]/30' : 'border-amber-200 bg-amber-50/30')) : 'border-surface-300' }} p-4 flex gap-3">
                    <div class="w-10 h-10 rounded-xl {{ $iconBg }} flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-ink-800">{{ $data['message'] ?? 'إشعار جديد' }}</p>
                        @if(!empty($data['reason']))
                        <div class="mt-2 p-3 rounded-xl bg-white border border-surface-300">
                            <div class="text-xs font-bold text-ink-500 mb-1">سبب الرفض:</div>
                            <p class="text-sm leading-6 text-ink-700">{{ $data['reason'] }}</p>
                        </div>
                        @endif
                        @if(!empty($data['processor_name']) || !empty($data['sender_name']))
                        <div class="mt-1 text-xs text-ink-500">
                            @if(!empty($data['processor_name'])) بواسطة {{ $data['processor_name'] }} @endif
                            @if(!empty($data['sender_name'])) من {{ $data['sender_name'] }} @endif
                        </div>
                        @endif
                        <div class="mt-2 flex items-center gap-3 text-xs text-ink-400">
                            <span>{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</span>
                            <span>•</span>
                            <span>كاميرا {{ $data['camera_number'] ?? '—' }} — الطابق {{ $data['floor_number'] ?? '—' }}</span>
                            @if(is_null($notification->read_at))
                                <span class="mr-auto inline-flex items-center gap-1 text-red-500 font-bold"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>غير مقروء</span>
                            @else
                                <span class="mr-auto text-ink-300">مقروء</span>
                            @endif
                        </div>
                        <div class="mt-3 flex gap-2">
                            <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition">عرض التفاصيل</a>
                            @if(is_null($notification->read_at))
                                <form method="POST" action="{{ route('notifications.markOneRead', $notification->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-white border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">تحديد كمقروء</button>
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
