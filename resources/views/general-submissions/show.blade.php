@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto" data-i18n-entity="submission" data-i18n-id="{{ $generalSubmission->id }}">
    <a href="{{ route('general-submissions.index') }}" class="inline-flex items-center gap-2 text-sm text-ink-400 hover:text-ink-700 mb-4">{{ back_arrow() }} {{ __('ui.back_aria') }}</a>

    <div class="bg-white rounded-2xl border border-[#e6e9e1] overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-[#e6e9e1]">
            <div class="flex items-center gap-2 mb-3">
                @if($generalSubmission->status === 'pending')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span data-status="pending">{{ __('ui.pending') }}</span></span>
                @elseif($generalSubmission->status === 'accepted')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-[#eef4f0] text-[#0e6a38]"><span data-status="accepted">{{ __('ui.accepted') }}</span></span>
                @elseif($generalSubmission->status === 'rejected')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700"><span data-status="rejected">{{ __('ui.rejected') }}</span></span>
                @endif
            </div>
            <h1 class="text-lg font-extrabold text-ink-800 break-words" data-i18n-field="description">{{ l10n_text('submission', $generalSubmission->id, 'description', $generalSubmission->description) }}</h1>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4 mt-3 text-sm text-ink-500">
                <span class="whitespace-nowrap">{{ __('ui.floor') }} {{ $generalSubmission->floor_number }}</span>
                <span class="whitespace-nowrap">{{ __('ui.camera') }} {{ $generalSubmission->camera_number }}</span>
                <span class="whitespace-nowrap tabular-nums" dir="ltr">{{ $generalSubmission->observed_at->format('Y-m-d H:i') }}</span>
                <span class="text-xs text-ink-400 font-mono whitespace-nowrap" dir="ltr" title="{{ __('ui.created_at') }}">{{ __('ui.created_label') }} {{ $generalSubmission->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div class="mt-3 text-sm">
                <span class="text-ink-400">{{ __('ui.sender') }}</span>
                <a href="{{ route('profile.showUser', $generalSubmission->owner->id) }}" class="font-bold text-ink-700 hover:text-[#0e6a38] hover:underline transition" data-no-translate>{{ $generalSubmission->owner->localized_name }}</a>
            </div>
            @if(auth()->user()->isMonitor())
            <div class="mt-2 flex flex-wrap gap-1">
                <span class="text-xs text-ink-400">{{ __('ui.to') }}</span>
                @foreach($generalSubmission->reportWriters as $w)
                    <a href="{{ route('profile.showUser', $w->id) }}" class="px-2 py-0.5 rounded-full bg-[#f5f7f5] border text-xs font-bold hover:text-[#0e6a38] hover:border-[#cde7d6] transition">{{ $w->localized_name }}</a>
                @endforeach
            </div>
            @endif
        </div>
        @if($generalSubmission->attachments->count() > 0)
        <div class="px-4 sm:px-6 pt-4">
            <h3 class="text-sm font-bold text-ink-700 mb-3">{{ __('ui.attachments_section', ['count' => $generalSubmission->attachments->count()]) }}</h3>
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
                        <a href="{{ route('submission-attachments.view', $attachment) }}" target="_blank" class="px-3 py-1.5 rounded-lg bg-white border border-[#e6e9e1] text-xs font-bold">{{ __('ui.view_attachment') }}</a>
                        @if(auth()->user()->isReportWriter())
                            <a href="{{ route('submission-attachments.download', $attachment) }}" class="px-2.5 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold" title="{{ __('ui.download_writer_only') }}">↓</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif
        <div class="p-4 sm:p-6">
            <div class="mb-2">@include('notes.partials.translation_status', ['note' => $generalSubmission, 'type' => 'submission'])</div>
            <h3 class="font-bold text-ink-700 mb-2">{{ __('ui.description') }}</h3>
            <p class="text-sm leading-7 text-ink-600 whitespace-pre-wrap" data-i18n-field="description">{{ l10n_text('submission', $generalSubmission->id, 'description', $generalSubmission->description) }}</p>
            @if($generalSubmission->isRejected() && $generalSubmission->rejection_reason)
                <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-200">
                    <h4 class="text-sm font-bold text-red-700 mb-2">{{ __('ui.reject_reason') }}</h4>
                    <p class="text-sm leading-7 text-red-600" data-i18n-field="rejection_reason">{{ l10n_text('submission', $generalSubmission->id, 'rejection_reason', $generalSubmission->rejection_reason) }}</p>
                    @if($generalSubmission->processed_by)
                        @php $processor = \App\Models\User::find($generalSubmission->processed_by); @endphp
                        @if($processor)
                            <div class="mt-2 text-xs font-bold text-red-500">{{ __('ui.by_user') }} <a href="{{ route('profile.showUser', $processor->id) }}" class="hover:underline transition">{{ $processor->localized_name }}</a> — {{ $generalSubmission->processed_at?->format('Y-m-d H:i') }}</div>
                        @endif
                    @endif
                </div>
            @endif
            @if($generalSubmission->isAccepted() && $generalSubmission->processed_by)
                @php $processor = \App\Models\User::find($generalSubmission->processed_by); @endphp
                @if($processor)
                    <div class="mt-4 p-3 rounded-xl bg-[#eef4f0] border border-[#cde7d6] text-sm">
                        <a href="{{ route('profile.showUser', $processor->id) }}" class="font-bold text-[#0e6a38] hover:underline transition">{{ __('ui.approved_by') }} {{ $processor->localized_name }}</a>
                        <span class="text-ink-400"> — {{ $generalSubmission->processed_at?->format('Y-m-d H:i') }}</span>
                    </div>
                @endif
            @endif
        </div>
        
        <div class="p-4 bg-[#f5f7f5] border-t border-[#e6e9e1] flex flex-wrap items-center gap-2">
            <button type="button" onclick="openModal('gs-share-modal')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition">
                <x-phosphor-whatsapp-logo-duotone class="w-4 h-4" />
                {{ __('ui.share_whatsapp') }}
            </button>
            @can('accept', $generalSubmission)
                <form method="POST" action="{{ route('general-submissions.accept', $generalSubmission) }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">{{ __('ui.accept_approve') }}</button>
                </form>
                <button type="button" onclick="document.getElementById('reject-box').classList.toggle('hidden')" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">{{ __('ui.reject_reason_btn') }}</button>
            @endcan
            <a href="{{ route('general-submissions.index') }}" class="mr-auto px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">{{ __('ui.back_aria') }}</a>
        </div>
        @can('reject', $generalSubmission)
        <div id="reject-box" class="hidden p-4 bg-white border-t border-[#e6e9e1]">
            <form method="POST" action="{{ route('general-submissions.reject', $generalSubmission) }}" class="space-y-3">
                @csrf
                <label class="block text-sm font-bold text-ink-700">{{ __('ui.reject_reason') }} <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" required maxlength="1000" class="w-full rounded-xl border border-[#e6e9e1] p-3 text-sm focus:border-red-400 focus:ring-2 focus:ring-red-400/10 outline-none" placeholder="{{ __('ui.reject_reason_ph') }}"></textarea>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">{{ __('ui.confirm_reject') }}</button>
                    <button type="button" onclick="document.getElementById('reject-box').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-[#f5f7f5] transition">{{ __('ui.cancel_btn') }}</button>
                </div>
            </form>
        </div>
        @endcan
    </div>

    @php
        $gsShareAttachments = $generalSubmission->attachments->map(fn($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime_type, 'url' => '/s/submission-attachments/' . $a->id])->toArray();
        $gsShareText = __('ui.camera_floor_format', ['camera' => $generalSubmission->camera_number, 'floor' => $generalSubmission->floor_number]) . "\n" . l10n_text('submission', $generalSubmission->id, 'description', $generalSubmission->description);
    @endphp
    <div id="gs-share-modal" data-modal class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm" onclick="closeModal('gs-share-modal')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-5 py-4 border-b border-[#e6e9e1] flex items-center justify-between">
                <h3 class="text-sm font-extrabold text-ink-800">{{ __('ui.share_sub') }}</h3>
                <button type="button" onclick="closeModal('gs-share-modal')" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-300 hover:text-ink-700 transition" aria-label="{{ __('ui.close_camera') }}">
                    <x-phosphor-x-duotone class="w-5 h-5" />
                </button>
            </div>
            <div class="p-5 space-y-3">
                <button type="button" id="gs-share-send" onclick="doGsShare()" class="w-full px-4 py-3 rounded-xl bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition">{{ __('ui.share_whatsapp') }}</button>
                <button type="button" onclick="gsCopyText(this)" class="w-full px-4 py-2.5 rounded-xl bg-white border border-[#e6e9e1] text-ink-700 text-sm font-bold hover:bg-[#f5f7f5] transition">{{ __('ui.copy_text') }}</button>
                <div id="gs-share-links" class="space-y-2"></div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
const gsBaseText = @json($gsShareText);
const gsItems = @json($gsShareAttachments);
const GS_T = {
    audio: @json(__('ui.audio_clip')),
    video: @json(__('ui.video_clip')),
    image: @json(__('ui.view_all') === 'View all' ? 'Image' : 'صورة'),
    other: @json(__('ui.attachment_alt')),
    filesAttached: @json(__('ui.available_attachments')),
    watchLinks: @json(__('ui.watch_links')),
    copy: @json(__('ui.copy_text')),
    copied: @json(__('ui.notif_marked_one')),
    copyFail: @json(__('ui.notif_update_failed'))
};
function gsShortName(it, c){
    var m = it.mime || '';
    if (m.indexOf('audio/') === 0) return GS_T.audio + ' ' + (++c.aud);
    if (m.indexOf('video/') === 0) return GS_T.video + ' ' + (++c.vid);
    if (m.indexOf('image/') === 0) return GS_T.image + ' ' + (++c.img);
    return GS_T.other + ' ' + (++c.other);
}
function gsFullText(){
    var c = {img:0, vid:0, aud:0, other:0};
    var t = gsBaseText;
    if (gsItems.length) {
        t += '\n\n*' + GS_T.filesAttached + ' (' + gsItems.length + ')*\n' + gsItems.map(function(it){
            return gsShortName(it, c) + ':\n' + String.fromCharCode(8206) + shareBase() + it.url;
        }).join('\n\n');
    }
    return t;
}
function gsRenderLinks(){
    var box = document.getElementById('gs-share-links');
    if (!box) return;
    if (!gsItems.length) { box.innerHTML = ''; return; }
    var html = '<div class="text-xs font-bold text-ink-500">' + GS_T.watchLinks + '</div>';
    gsItems.forEach(function(it){
        var abs = shareBase() + it.url;
        html += '<div class="flex items-center gap-2"><input readonly onclick="this.select()" value="' + abs.replace(/&/g,'&amp;').replace(/"/g,'&quot;') + '" dir="ltr" class="flex-1 min-w-0 text-xs font-mono text-ink-500 bg-[#f5f7f5] border border-[#e6e9e1] rounded-lg px-2 py-1.5">';
        html += '<button type="button" data-url="' + abs.replace(/&/g,'&amp;').replace(/"/g,'&quot;') + '" onclick="gsCopyLink(this)" class="shrink-0 px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold">' + GS_T.copy + '</button></div>';
    });
    box.innerHTML = html;
}
function gsCopyText(btn){
    var done = function(ok){ var o = btn.textContent; btn.textContent = ok ? (GS_T.copied + ' ✓') : GS_T.copyFail; setTimeout(function(){ btn.textContent = o; }, 1200); };
    window.copyTextToClipboard(gsFullText()).then(done);
}
function gsCopyLink(btn){
    var v = btn.dataset.url || '';
    var done = function(ok){ var o = btn.textContent; btn.textContent = ok ? '✓' : GS_T.copyFail; setTimeout(function(){ btn.textContent = o; }, 1200); };
    window.copyTextToClipboard(v).then(done);
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
