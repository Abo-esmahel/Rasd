@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('general-submissions.index') }}" class="inline-flex items-center gap-2 text-sm text-ink-400 hover:text-ink-700 mb-4">‹ العودة</a>

    <div class="bg-white rounded-2xl border border-[#e6e9e1] overflow-hidden">
        <div class="p-6 border-b border-[#e6e9e1]">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs font-mono text-ink-400">#{{ str_pad($generalSubmission->id,4,'0',STR_PAD_LEFT) }}</span>
                @if($generalSubmission->status === 'pending')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">قيد المراجعة</span>
                @elseif($generalSubmission->status === 'accepted')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-[#eef4f0] text-[#0e6a38]">مقبولة</span>
                @elseif($generalSubmission->status === 'rejected')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700">مرفوضة</span>
                @endif
            </div>
            <h1 class="text-lg font-extrabold text-ink-800">{{ $generalSubmission->description }}</h1>
            <div class="flex items-center gap-4 mt-3 text-sm text-ink-500">
                <span>الطابق {{ $generalSubmission->floor_number }}</span>
                <span>كاميرا {{ $generalSubmission->camera_number }}</span>
                <span>{{ $generalSubmission->observed_at->format('Y-m-d H:i') }}</span>
                <span class="text-xs text-ink-400 font-mono" dir="ltr" title="وقت الإنشاء">أُنشئت {{ $generalSubmission->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div class="mt-3 text-sm">
                <span class="text-ink-400">المرسل:</span>
                <span class="font-bold text-ink-700">{{ $generalSubmission->owner->name }}</span>
            </div>
            @if(auth()->user()->isMonitor())
            <div class="mt-2 flex flex-wrap gap-1">
                <span class="text-xs text-ink-400">موجه إلى:</span>
                @foreach($generalSubmission->reportWriters as $w)
                    <span class="px-2 py-0.5 rounded-full bg-[#f5f7f5] border text-xs font-bold">{{ $w->name }}</span>
                @endforeach
            </div>
            @endif
        </div>
        @if($generalSubmission->attachments->count() > 0)
        <div class="px-6 pt-4">
            <h3 class="text-sm font-bold text-ink-700 mb-3">المرفقات ({{ $generalSubmission->attachments->count() }})</h3>
            <div class="space-y-2">
                @foreach($generalSubmission->attachments as $attachment)
                    @if(str_starts_with($attachment->mime_type, 'image/'))
                        <img src="{{ route('submission-attachments.view', $attachment) }}" alt="{{ $attachment->original_name }}" data-testid="gs-attachment-image" data-attachment-id="{{ $attachment->id }}" class="w-full max-h-96 object-contain bg-[#f5f7f5] rounded-xl border border-[#e6e9e1]" loading="lazy">
                    @elseif(str_starts_with($attachment->mime_type, 'video/'))
                        <video src="{{ route('submission-attachments.view', $attachment) }}" class="w-full max-h-96 rounded-xl bg-black" controls controlsList="nodownload"></video>
                    @elseif(str_starts_with($attachment->mime_type, 'audio/'))
                        <audio src="{{ route('submission-attachments.view', $attachment) }}" class="w-full" controls controlsList="nodownload"></audio>
                    @endif
                    <div class="flex items-center gap-3 p-3 rounded-xl border border-[#e6e9e1]">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-ink-700 truncate">{{ $attachment->original_name }}</div>
                            <div class="text-xs text-[#737373]">{{ $attachment->mime_type }} — {{ number_format($attachment->file_size/1024, 1) }} KB</div>
                        </div>
                        <a href="{{ route('submission-attachments.view', $attachment) }}" target="_blank" class="px-3 py-1.5 rounded-lg bg-white border border-[#e6e9e1] text-xs font-bold">عرض</a>
                        @if(auth()->user()->isReportWriter())
                            <a href="{{ route('submission-attachments.download', $attachment) }}" class="px-2.5 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold">تنزيل</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif
        <div class="p-6">
            <h3 class="font-bold text-ink-700 mb-2">الوصف</h3>
            <p class="text-sm leading-7 text-ink-600 whitespace-pre-wrap">{{ $generalSubmission->description }}</p>
            @if($generalSubmission->isRejected() && $generalSubmission->rejection_reason)
                <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-200">
                    <h4 class="text-sm font-bold text-red-700 mb-2">سبب الرفض</h4>
                    <p class="text-sm leading-7 text-red-600">{{ $generalSubmission->rejection_reason }}</p>
                    @if($generalSubmission->processed_by)
                        @php $processor = \App\Models\User::find($generalSubmission->processed_by); @endphp
                        @if($processor)
                            <div class="mt-2 text-xs font-bold text-red-500">بواسطة {{ $processor->name }} — {{ $generalSubmission->processed_at?->format('Y-m-d H:i') }}</div>
                        @endif
                    @endif
                </div>
            @endif
            @if($generalSubmission->isAccepted() && $generalSubmission->processed_by)
                @php $processor = \App\Models\User::find($generalSubmission->processed_by); @endphp
                @if($processor)
                    <div class="mt-4 p-3 rounded-xl bg-[#eef4f0] border border-[#cde7d6] text-sm">
                        <span class="font-bold text-[#0e6a38]">تم القبول بواسطة {{ $processor->name }}</span>
                        <span class="text-ink-400"> — {{ $generalSubmission->processed_at?->format('Y-m-d H:i') }}</span>
                    </div>
                @endif
            @endif
        </div>
        
        <div class="p-4 bg-[#f5f7f5] border-t border-[#e6e9e1] flex flex-wrap items-center gap-2">
            <button type="button" onclick="openModal('gs-share-modal')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1112 20.2zm4.6-6.1c-.3-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 01-3.3-2.9c-.3-.4 0-.5.1-.7l.5-.6c.1-.2.1-.4 0-.5L9.5 8c-.2-.4-.4-.4-.6-.4h-.5c-.2 0-.5.2-.7.5-.9 1-.6 2.7.7 4.5a11.6 11.6 0 004.5 3.9c1.7.7 2.4.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2 0-.1-.2-.1-.4-.2z"/></svg>
                مشاركة واتساب
            </button>
            @can('accept', $generalSubmission)
                <form method="POST" action="{{ route('general-submissions.accept', $generalSubmission) }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">قبول واعتماد</button>
                </form>
                <button type="button" onclick="document.getElementById('reject-box').classList.toggle('hidden')" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">رفض مع سبب</button>
            @endcan
            <a href="{{ route('general-submissions.index') }}" class="mr-auto px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">عودة</a>
        </div>
        @can('reject', $generalSubmission)
        <div id="reject-box" class="hidden p-4 bg-white border-t border-[#e6e9e1]">
            <form method="POST" action="{{ route('general-submissions.reject', $generalSubmission) }}" class="space-y-3">
                @csrf
                <label class="block text-sm font-bold text-ink-700">سبب الرفض <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" required maxlength="1000" class="w-full rounded-xl border border-[#e6e9e1] p-3 text-sm focus:border-red-400 focus:ring-2 focus:ring-red-400/10 outline-none" placeholder="اذكر سبب الرفض بوضوح..."></textarea>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">تأكيد الرفض</button>
                    <button type="button" onclick="document.getElementById('reject-box').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-[#f5f7f5] transition">إلغاء</button>
                </div>
            </form>
        </div>
        @endcan
    </div>

    @php
        $gsShareAttachments = $generalSubmission->attachments->map(fn($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime_type, 'url' => '/s/submission-attachments/' . $a->id])->toArray();
        $gsShareText = 'إرسال عام #' . str_pad($generalSubmission->id, 4, '0', STR_PAD_LEFT) . ' — كاميرا ' . $generalSubmission->camera_number . ' طابق ' . $generalSubmission->floor_number . "\n" . $generalSubmission->description;
    @endphp
    <div id="gs-share-modal" data-modal class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm" onclick="closeModal('gs-share-modal')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-5 py-4 border-b border-[#e6e9e1] flex items-center justify-between">
                <h3 class="text-sm font-extrabold text-ink-800">مشاركة الإرسال</h3>
                <button type="button" onclick="closeModal('gs-share-modal')" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-300 hover:text-ink-700 transition" aria-label="إغلاق">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-3">
                <button type="button" id="gs-share-send" onclick="doGsShare()" class="w-full px-4 py-3 rounded-xl bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition">مشاركة عبر واتساب</button>
                <button type="button" onclick="gsCopyText(this)" class="w-full px-4 py-2.5 rounded-xl bg-white border border-[#e6e9e1] text-ink-700 text-sm font-bold hover:bg-[#f5f7f5] transition">نسخ النص</button>
                <div id="gs-share-links" class="space-y-2"></div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
const gsBaseText = @json($gsShareText);
const gsItems = @json($gsShareAttachments);
function gsShortName(it, c){
    var m = it.mime || '';
    if (m.indexOf('audio/') === 0) return 'مقطع صوتي ' + (++c.aud);
    if (m.indexOf('video/') === 0) return 'فيديو ' + (++c.vid);
    if (m.indexOf('image/') === 0) return 'صورة ' + (++c.img);
    return 'مرفق ' + (++c.other);
}
function gsFullText(){
    var c = {img:0, vid:0, aud:0, other:0};
    var t = gsBaseText;
    if (gsItems.length) {
        t += '\n\n*الملفات المرفقة (' + gsItems.length + ')*\n' + gsItems.map(function(it){
            return gsShortName(it, c) + ':\n' + String.fromCharCode(8206) + shareBase() + it.url;
        }).join('\n\n');
    }
    return t;
}
function gsRenderLinks(){
    var box = document.getElementById('gs-share-links');
    if (!box) return;
    if (!gsItems.length) { box.innerHTML = ''; return; }
    var html = '<div class="text-xs font-bold text-ink-500">روابط المشاهدة — دائمة</div>';
    gsItems.forEach(function(it){
        var abs = shareBase() + it.url;
        html += '<div class="flex items-center gap-2"><input readonly onclick="this.select()" value="' + abs.replace(/&/g,'&amp;').replace(/"/g,'&quot;') + '" dir="ltr" class="flex-1 min-w-0 text-xs font-mono text-ink-500 bg-[#f5f7f5] border border-[#e6e9e1] rounded-lg px-2 py-1.5">';
        html += '<button type="button" data-url="' + abs.replace(/&/g,'&amp;').replace(/"/g,'&quot;') + '" onclick="gsCopyLink(this)" class="shrink-0 px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold">نسخ</button></div>';
    });
    box.innerHTML = html;
}
function gsCopyText(btn){
    var done = function(){ var o = btn.textContent; btn.textContent = 'تم النسخ ✓'; setTimeout(function(){ btn.textContent = o; }, 1200); };
    if (navigator.clipboard) navigator.clipboard.writeText(gsFullText()).then(done).catch(done); else done();
}
function gsCopyLink(btn){
    var v = btn.dataset.url || '';
    var done = function(){ var o = btn.textContent; btn.textContent = 'تم ✓'; setTimeout(function(){ btn.textContent = o; }, 1200); };
    if (navigator.clipboard) navigator.clipboard.writeText(v).then(done).catch(done); else done();
}
async function doGsShare(){
    var btn = document.getElementById('gs-share-send');
    var text = gsFullText();
    if (navigator.share) {
        try {
            var files = [];
            for (const it of gsItems) {
                try {
                    const r = await fetch(it.url);
                    if (!r.ok) continue;
                    files.push(new File([await r.blob()], it.name, {type: it.mime}));
                } catch(e) {}
            }
            var data = {text: text};
            if (files.length && navigator.canShare && navigator.canShare({files: files})) data.files = files;
            await navigator.share(data);
            closeModal('gs-share-modal');
            return;
        } catch(e) { if (e && e.name === 'AbortError') return; }
    }
    gsRenderLinks();
    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
}
document.getElementById('gs-share-modal')?.addEventListener('click', function(e){
    if (e.target === this) closeModal('gs-share-modal');
});
gsRenderLinks();
</script>
@endpush
@endsection
