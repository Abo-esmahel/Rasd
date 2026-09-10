@extends('layouts.app')

@section('content')
<div id="notif-center-page" class="max-w-3xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-extrabold text-text-primary dark:text-text-primary tracking-tight">سجل الإشعارات</h1>
            <p class="text-sm text-text-secondary dark:text-text-secondary mt-1">متابعة فورية لحالة ملاحظاتك — تحديث لحظي</p>
            <div id="notif-center-status" class="mt-1.5 text-xs text-text-muted dark:text-text-muted" aria-live="polite"></div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($unreadCount > 0)
                <button type="button" id="center-mark-all" class="px-4 py-2 rounded-xl bg-surface-elevated border border-border text-text-secondary dark:text-text-secondary text-sm font-bold hover:bg-surface-muted dark:hover:bg-surface-muted transition">تحديد الكل كمقروء</button>
            @endif
            <a href="{{ route('notes.index') }}" class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-hover transition shadow-sm">الملاحظات</a>
        </div>
    </div>


    @php $filter = request('filter', 'all'); @endphp
    <div class="mb-5 border-b border-border -mx-4 sm:mx-0 px-4 sm:px-0 overflow-x-auto">
        <nav class="flex gap-6 min-w-max" aria-label="تصفية الإشعارات">
            @foreach(['all'=>'الكل','unread'=>'غير مقروءة','read'=>'مقروءة'] as $key=>$label)
                @php $isActive = $filter === $key; $url = route('notifications.index', array_filter(['filter'=>$key !== 'all' ? $key : null])); @endphp
                <a href="{{ $url }}" class="relative py-3 text-[13px] whitespace-nowrap border-b-2 transition {{ $isActive ? 'border-primary text-text-primary dark:text-text-primary font-bold' : 'border-transparent text-text-muted dark:text-text-muted hover:text-text-secondary dark:hover:text-text-secondary font-medium' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>


    <div id="notif-center-live" class="sr-only" aria-live="polite" aria-atomic="true"></div>

    @if($notifications->isEmpty())
        <div class="bg-surface-elevated rounded-2xl border border-border p-10 text-center" role="status" aria-live="polite">
            <div class="w-14 h-14 rounded-2xl bg-surface-muted flex items-center justify-center mx-auto">
                <svg class="w-7 h-7 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.586a1 1 0 011.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="mt-4 text-base font-bold text-text-primary dark:text-text-primary">لا توجد إشعارات</h3>
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

                    $title = $data['title'] ?? ($isRejected ? 'مرفوض' : ($isAccepted ? 'تم القبول' : 'واردة'));
                    $shortMsg = $data['message'] ?? 'إشعار جديد';
                    $sender = $data['sender_name'] ?? $data['processor_name'] ?? null;
                    $iconBg = $isRejected ? 'bg-surface-elevated border border-red-200 dark:border-red-900/30 text-red-600 dark:text-red-400' : ($isAccepted ? 'bg-surface-elevated border border-border text-primary dark:text-green-400' : 'bg-surface-elevated border border-border text-amber-600 dark:text-amber-400');
                    $iconPath = $isRejected
                        ? 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                        : ($isAccepted ? 'M5 13l4 4L19 7' : 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' );
                @endphp
                <div class="group bg-surface-elevated rounded-xl border border-border overflow-hidden hover:border-border-strong hover:shadow-sm transition {{ is_null($notification->read_at) ? 'shadow-sm' : 'opacity-[0.96]' }} focus-within:ring-2 focus-within:ring-primary/20" tabindex="0" role="article" aria-labelledby="notif-{{ $notification->id }}-title">
                    <div class="p-4 flex gap-3">
                        <div class="w-9 h-9 rounded-xl {{ $iconBg }} flex items-center justify-center shrink-0 mt-0.5 shadow-sm" aria-hidden="true">
                            <svg class="w-4.5 h-4.5 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-2">
                                <h3 id="notif-{{ $notification->id }}-title" class="min-w-0 flex-1 text-[13px] font-extrabold text-text-primary dark:text-text-primary leading-5 break-words">{{ $title }}</h3>
                                @if(is_null($notification->read_at))
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isRejected ? 'bg-red-500' : ($isAccepted ? 'bg-primary' : 'bg-amber-500') }} mt-1.5 shrink-0"></span>
                                @endif
                                <span class="text-[11px] text-text-muted dark:text-text-muted mr-auto shrink-0 whitespace-nowrap">{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</span>
                            </div>
                            <p class="text-xs font-medium text-text-secondary dark:text-text-secondary mt-1 leading-5 line-clamp-1">{{ $shortMsg }}</p>
                            <p class="text-[11px] text-text-muted dark:text-text-muted mt-1 truncate">
                                @if($sender) {{ $sender }} · @endif
                                كاميرا {{ $data['camera_number'] ?? '—' }} · طابق {{ $data['floor_number'] ?? '—' }}
                            </p>
                            @if(!empty($data['reason']))
                            <div class="mt-2.5 text-xs leading-5 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/30 rounded-lg px-3 py-2 line-clamp-2">
                                {{ $data['reason'] }}
                            </div>
                            @endif
                            <div class="mt-3 flex items-center gap-2">
                                <a href="{{ $url }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-bold hover:bg-primary-hover transition shadow-sm" data-notif-action="open">عرض التفاصيل</a>
                                @if(is_null($notification->read_at))
                                    <button type="button" data-notif-id="{{ $notification->id }}" class="notif-mark-one px-3 py-1.5 rounded-lg bg-surface-elevated border border-border text-text-secondary dark:text-text-secondary text-xs font-bold hover:bg-surface-muted dark:hover:bg-surface-muted transition" aria-label="تحديد كمقروء">تمت القراءة</button>
                                @else
                                    <span class="text-[11px] text-text-muted dark:text-text-muted mr-auto">مقروء</span>
                                @endif
                            </div>
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

  if (window.NotificationManagerInstance) {
    updateStatus(window.NotificationManagerInstance.connectionState);
  }


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

      const card = btn.closest('[role="article"]');
      if (card) {
        card.classList.remove('border-red-200','bg-red-50/30','border-[#cde7d6]','bg-[#eef4f0]/30','border-amber-200','bg-amber-50/30');
        card.classList.add('border-border');
        const badge = card.querySelector('[aria-label="غير مقروء"]');
        if (badge) badge.outerHTML = '<span class="mr-auto text-text-muted dark:text-text-muted">مقروء</span>';
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
        card.classList.add('border-border');
        const badge = card.querySelector('[aria-label="غير مقروء"]');
        if (badge) badge.outerHTML = '<span class="mr-auto text-text-muted dark:text-text-muted">مقروء</span>';
      });
      if (liveEl) liveEl.textContent = 'تم تحديد كل الإشعارات كمقروءة';
      markAllBtn.remove();
    } catch {
      markAllBtn.disabled = false;
      markAllBtn.textContent = orig;
    }
  });


  window.addEventListener('notification:toast', (e) => {


    if (listEl && e.detail?.notification) {
      const n = e.detail.notification;

      const filter = new URLSearchParams(location.search).get('filter') || 'all';
      if (filter === 'read') return;

      if (listEl.querySelector(`[data-notif-id="${n.id}"]`)) return;


      if (liveEl) liveEl.textContent = 'وصل إشعار جديد: ' + (n.data?.message || 'إشعار');
    }
  });
});
</script>
@endpush
@endsection
