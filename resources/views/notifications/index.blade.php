@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-extrabold text-ink-800">الإشعارات</h1>
            <p class="text-sm text-ink-400 mt-1">إشعارات الإرساليات والملاحظات — قبول ورفض وإرسال — تحديث حي بدون Refresh</p>
            <div id="notif-center-status" class="mt-1.5 text-xs text-ink-400" aria-live="polite"></div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($unreadCount > 0)
                <button type="button" id="center-mark-all" class="px-4 py-2 rounded-xl bg-white border border-surface-300 text-ink-600 text-sm font-bold hover:bg-surface-100 transition">تحديد الكل كمقروء</button>
            @endif
            <a href="{{ route('notes.index') }}" class="px-4 py-2 rounded-xl bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">الملاحظات</a>
        </div>
    </div>

    {{-- Filters: all / unread / read --}}
    @php $filter = request('filter', 'all'); @endphp
    <div class="mb-5 border-b border-[#e6e9e1] -mx-4 sm:mx-0 px-4 sm:px-0 overflow-x-auto">
        <nav class="flex gap-6 min-w-max" aria-label="تصفية الإشعارات">
            @foreach(['all'=>'الكل','unread'=>'غير مقروءة','read'=>'مقروءة'] as $key=>$label)
                @php $isActive = $filter === $key; $url = route('notifications.index', array_filter(['filter'=>$key !== 'all' ? $key : null])); @endphp
                <a href="{{ $url }}" class="relative py-3 text-[13px] whitespace-nowrap border-b-2 transition {{ $isActive ? 'border-[#0e6a38] text-ink-800 font-bold' : 'border-transparent text-ink-400 hover:text-ink-600 font-medium' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    {{-- Live region for screen readers --}}
    <div id="notif-center-live" class="sr-only" aria-live="polite" aria-atomic="true"></div>

    @if($notifications->isEmpty())
        <div class="bg-white rounded-2xl border border-surface-300 p-10 text-center" role="status" aria-live="polite">
            <div class="w-14 h-14 rounded-2xl bg-surface-100 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.586a1 1 0 011.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="mt-4 text-base font-bold text-ink-700">لا توجد إشعارات</h3>
            <p class="mt-1.5 text-sm text-ink-400">عندما يتم قبول أو رفض ملاحظة، أو عند إرسال ملاحظة جديدة للمراجعة، سيصلك التنبيه فوراً مع صوت وتوست بدون تحديث الصفحة.</p>
            <p class="mt-3 text-xs text-ink-300">التحديث الحي يعمل عبر SSE/WebSocket — جرب إرسال ملاحظة جديدة في تبويب آخر.</p>
        </div>
    @else
        <div id="notif-center-list" class="space-y-3" role="feed" aria-busy="false">
            @foreach($notifications as $notification)
                @php
                    $data = $notification->data;
                    if (is_string($data)) $data = json_decode($data, true) ?: [];
                    $type = $data['type'] ?? ($notification->type ?? '');
                    $isRejected = str_contains($type, 'reject') || str_contains(strtolower($type), 'rejected') || isset($data['reason']);
                    $isAccepted = str_contains($type, 'accept') || str_contains(strtolower($type), 'accepted');
                    $priority = $data['priority'] ?? ($isRejected ? 'high' : 'normal');
                    $isHigh = $priority === 'high' || $priority === 'critical';
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
                <div class="bg-white rounded-xl border {{ is_null($notification->read_at) ? ($isRejected ? 'border-red-200 bg-red-50/30' : ($isAccepted ? 'border-[#cde7d6] bg-[#eef4f0]/30' : 'border-amber-200 bg-amber-50/30')) : 'border-surface-300' }} p-4 flex gap-3 focus-within:ring-2 focus-within:ring-[#0e6a38] focus-within:ring-offset-1" tabindex="0" role="article" aria-labelledby="notif-{{ $notification->id }}-title">
                    <div class="w-10 h-10 rounded-xl {{ $iconBg }} flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p id="notif-{{ $notification->id }}-title" class="text-sm font-bold text-ink-800">{{ $data['message'] ?? 'إشعار جديد' }}</p>
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
                            @if($isHigh) <span class="ml-2 inline-flex px-1.5 py-0.5 rounded bg-red-50 text-red-600 border border-red-200 text-[10px] font-bold">عالي</span> @endif
                        </div>
                        @endif
                        <div class="mt-2 flex items-center gap-3 text-xs text-ink-400">
                            <span>{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</span>
                            <span>•</span>
                            <span>كاميرا {{ $data['camera_number'] ?? '—' }} — الطابق {{ $data['floor_number'] ?? '—' }}</span>
                            @if(is_null($notification->read_at))
                                <span class="mr-auto inline-flex items-center gap-1 text-red-500 font-bold" aria-label="غير مقروء"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>غير مقروء</span>
                            @else
                                <span class="mr-auto text-ink-300">مقروء</span>
                            @endif
                        </div>
                        <div class="mt-3 flex gap-2">
                            <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition" data-notif-action="open">عرض التفاصيل</a>
                            @if(is_null($notification->read_at))
                                <button type="button" data-notif-id="{{ $notification->id }}" class="notif-mark-one px-3 py-1.5 rounded-lg bg-white border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition" aria-label="تحديد كمقروء">تحديد كمقروء</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if(isset($paginator) && $paginator->hasPages())
            <div class="mt-6 flex justify-center">
                {{ $paginator->withQueryString()->links() }}
            </div>
        @endif
    @endif
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const listEl = document.getElementById('notif-center-list');
  const statusEl = document.getElementById('notif-center-status');
  const liveEl = document.getElementById('notif-center-live');
  const markAllBtn = document.getElementById('center-mark-all');

  function updateStatus(state) {
    if (!statusEl) return;
    const map = {
      'CONNECTED': '● متصل — التحديث فوري',
      'RECONNECTING': '○ يعيد الاتصال…',
      'DISCONNECTED': '○ غير متصل — يحاول إعادة الاتصال',
    };
    statusEl.textContent = map[state] || '';
    statusEl.dataset.state = (state||'').toLowerCase();
  }

  window.addEventListener('notification:connection', (e) => updateStatus(e.detail.state));
  // initial
  if (window.NotificationManagerInstance) {
    updateStatus(window.NotificationManagerInstance.connectionState);
  }

  // Mark one as read (AJAX without reload)
  listEl?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.notif-mark-one');
    if (!btn) return;
    const id = btn.getAttribute('data-notif-id');
    if (!id) return;
    btn.disabled = true;
    btn.textContent = '…';
    try {
      const mgr = window.NotificationManagerInstance;
      if (mgr) await mgr.markOneAsRead(id);
      else {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch(`/notifications/${encodeURIComponent(id)}/read`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': token, 'Accept':'application/json' },
        });
        location.reload();
      }
      // visually update card
      const card = btn.closest('[role="article"]');
      if (card) {
        card.classList.remove('border-red-200','bg-red-50/30','border-[#cde7d6]','bg-[#eef4f0]/30','border-amber-200','bg-amber-50/30');
        card.classList.add('border-surface-300');
        const badge = card.querySelector('[aria-label="غير مقروء"]');
        if (badge) badge.outerHTML = '<span class="mr-auto text-ink-300">مقروء</span>';
        btn.remove();
        if (liveEl) liveEl.textContent = 'تم تحديد الإشعار كمقروء';
      }
    } catch (err) {
      btn.disabled = false;
      btn.textContent = 'تحديد كمقروء';
      alert('تعذر تحديد الإشعار كمقروء');
    }
  });

  markAllBtn?.addEventListener('click', async () => {
    markAllBtn.disabled = true;
    const orig = markAllBtn.textContent;
    markAllBtn.textContent = '…';
    try {
      const mgr = window.NotificationManagerInstance;
      if (mgr) await mgr.markAllAsRead();
      else {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/notifications/mark-read', { method:'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept':'application/json' }, body: new URLSearchParams({})});
        location.reload();
      }
      document.querySelectorAll('.notif-mark-one').forEach(b=>b.remove());
      document.querySelectorAll('[role="article"]').forEach(card=>{
        card.classList.remove('border-red-200','bg-red-50/30','border-[#cde7d6]','bg-[#eef4f0]/30','border-amber-200','bg-amber-50/30');
        card.classList.add('border-surface-300');
        const badge = card.querySelector('[aria-label="غير مقروء"]');
        if (badge) badge.outerHTML = '<span class="mr-auto text-ink-300">مقروء</span>';
      });
      if (liveEl) liveEl.textContent = 'تم تحديد كل الإشعارات كمقروءة';
      markAllBtn.remove();
    } catch {
      markAllBtn.disabled = false;
      markAllBtn.textContent = orig;
    }
  });

  // Listen for live incoming while on this page -> prepend without reload
  window.addEventListener('notification:toast', (e) => {
    // Optionally prepend to listEl if we are on 'all' filter and list exists
    // For simplicity, we show a small banner and let user refresh or we prepend optimistic card
    if (listEl && e.detail?.notification) {
      const n = e.detail.notification;
      // Only prepend if filter is all/unread
      const filter = new URLSearchParams(location.search).get('filter') || 'all';
      if (filter === 'read') return;
      // Avoid duplicate (already seen?)
      if (listEl.querySelector(`[data-notif-id="${n.id}"]`)) return;
      // Simple reload is safer than constructing full card HTML here; but we can fetch via API to re-render.
      // For now, just announce
      if (liveEl) liveEl.textContent = 'وصل إشعار جديد: ' + (n.data?.message || 'إشعار');
    }
  });
});
</script>
@endpush
@endsection
