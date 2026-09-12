@extends('layouts.app')

@php
    $isOwner = auth()->id() === $note->user_id;
    $isWriter = auth()->user()->isReportWriter();
    $statusLabel = status_label($note->status);
    $printNoteData = [
        'id' => $note->id,
        'floor_number' => $note->floor_number,
        'camera_number' => $note->camera_number,
        'observed_date' => $note->observed_at->format('Y-m-d'),
        'observed_time_start' => $note->observed_at->toTime12(),
        'observed_time_end' => $note->observed_end_at ? $note->observed_end_at->toTime12() : '—',
        'description' => l10n_text('note', $note->id, 'description', $note->description),
        'status' => $note->status,
        'status_label' => $statusLabel,
        'owner_name' => $note->owner->name,
        'attachments' => $note->attachments->map(fn($a) => [
            'id' => $a->id,
            'name' => $a->original_name,
            'mime' => $a->mime_type,
            'file_size' => $a->file_size,
            'url' => route('notes.attachments.view', $a)
        ])->toArray(),
    ];
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6" data-i18n-entity="note" data-i18n-id="{{ $note->id }}">
    
    <div class="flex items-center gap-3">
        <a href="{{ route('notes.index') }}" class="w-9 h-9 rounded-lg bg-white border border-[#e6e9e1] flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-[#f5f7f5] transition" aria-label="{{ __('ui.back') }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="text-lg font-extrabold text-ink-800">{{ __('ui.camera') }} {{ $note->camera_number }} · {{ __('ui.floor') }} {{ $note->floor_number }}</h1>
            <p class="text-xs text-[#737373]" data-no-translate>{{ $note->owner->name }}</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold
            @if($note->isDraft()) bg-ink-100 text-ink-600
            @elseif($note->isPending()) bg-amber-50 text-amber-700 border border-amber-200
            @elseif($note->isAccepted()) bg-[#eef4f0] text-[#0e6a38] border border-[#cde7d6]
            @else bg-red-50 text-red-700 border border-red-200 @endif">
            <span data-status="{{ $note->status }}">{{ status_label($note->status) }}</span>
        </span>
    </div>

    
    <div class="mt-2">
        @include('notes.partials.translation_status', ['note' => $note])
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
            <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.camera') }}</div>
            <div class="text-lg font-extrabold text-ink-800">{{ $note->camera_number }}</div>
        </div>
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
            <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.floor') }}</div>
            <div class="text-lg font-extrabold text-ink-800">{{ $note->floor_number }}</div>
        </div>
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
            <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.time_label') }}</div>
            <div class="text-sm font-bold text-ink-800">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? ' — '.$note->observed_end_at->toTime12() : '' }}</div>
            <div class="text-[11px] text-[#737373]">{{ $note->observed_at->format('Y-m-d') }}</div>
        </div>
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
            <div class="text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.attachments') }}</div>
            <div class="text-lg font-extrabold text-ink-800">{{ $note->attachments->count() }}</div>
            <div class="text-[11px] text-[#737373]">{{ __('ui.file_label') }}</div>
        </div>
    </div>

    
    <div class="flex items-center gap-3 p-3 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1]">
        <a href="{{ route('profile.showUser', $note->owner->id) }}" class="shrink-0 hover:opacity-80 transition" aria-label="{{ __('ui.profile_aria') }}">
        @if($note->owner->avatar_url)
            <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->name }}" class="w-9 h-9 rounded-lg object-cover border border-[#e6e9e1] shadow-sm">
        @else
            <div class="w-9 h-9 rounded-lg bg-[#eef4f0] text-[#0e6a38] flex items-center justify-center font-bold text-sm">{{ $note->owner->initial }}</div>
        @endif
        </a>
        <div>
            <div class="text-[11px] font-bold text-ink-300">{{ __('ui.observer') }}</div>
            <div class="text-sm font-bold text-ink-800"><a href="{{ route('profile.showUser', $note->owner->id) }}" class="hover:text-[#0e6a38] hover:underline transition">{{ $note->owner->name }}</a></div>
        </div>
        <div class="mr-auto text-left">
            <div class="text-[11px] font-bold text-ink-300">{{ __('ui.created_at') }}</div>
            <div class="text-xs font-medium text-[#525252]">{{ $note->created_at->toDatetime12() }}</div>
        </div>
    </div>

    
    <div class="bg-white rounded-2xl border border-[#e6e9e1] p-6">
        <h3 class="text-sm font-bold text-ink-700 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            {{ __('ui.description') }}
        </h3>
        @if(!empty($note->description))
            <div class="p-4 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] text-sm leading-[1.9] text-ink-700 whitespace-pre-wrap break-words min-h-[60px]" data-i18n-field="description">{{ l10n_text('note', $note->id, 'description', $note->description) }}</div>
        @else
            <div class="p-4 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] text-sm leading-[1.9] text-ink-400 italic">{{ __('ui.no_description') }}</div>
        @endif
        @if($note->isRejected() && $note->rejection_reason)
            <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-200">
                <h4 class="text-sm font-bold text-red-700 mb-2">{{ __('ui.reject_reason') }}</h4>
                <p class="text-sm leading-7 text-red-600" data-i18n-field="rejection_reason">{{ l10n_text('note', $note->id, 'rejection_reason', $note->rejection_reason) }}</p>
                @if($note->processor)
                    <div class="mt-2 text-xs font-bold text-red-500">{{ __('ui.by_user') }} {{ $note->processor->name }} — {{ $note->processed_at?->toDatetime12() }}</div>
                @endif
            </div>
        @endif
    </div>

    
    @if($note->attachments->count() > 0)
    <div class="bg-white rounded-2xl border border-[#e6e9e1] p-6">
        <h3 class="text-sm font-bold text-ink-700 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            {{ __('ui.attachments_section', ['count' => $note->attachments->count()]) }}
        </h3>
        <div class="space-y-2">
            @foreach($note->attachments as $attachment)
                
                @if(str_starts_with($attachment->mime_type, 'image/'))
                    <button type="button" onclick="openAttachmentView('{{ route('notes.attachments.view', $attachment) }}', '{{ $attachment->mime_type }}', '{{ addslashes($attachment->original_name) }}')" class="block w-full rounded-xl border border-[#e6e9e1] hover:border-[#cde7d6] overflow-hidden transition group" title="{{ __('ui.expand_full') }}">
                        <img src="{{ route('notes.attachments.view', $attachment) }}" alt="{{ $attachment->original_name }}" data-testid="attachment-image" data-attachment-id="{{ $attachment->id }}" class="w-full max-h-96 object-contain bg-[#f5f7f5]" loading="lazy" oncontextmenu="return false;" draggable="false">
                    </button>
                @endif
                <div class="flex items-center gap-3 p-3 rounded-xl border border-[#e6e9e1] hover:border-[#cde7d6] hover:bg-[#eef4f0]/30 transition group">
                    <div class="w-9 h-9 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center shrink-0">
                        @if(str_contains($attachment->mime_type, 'video'))
                            <svg class="w-4 h-4 text-[#737373]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        @elseif(str_contains($attachment->mime_type, 'audio'))
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                        @else
                            <svg class="w-4 h-4 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-bold text-ink-700 truncate">{{ $attachment->original_name }}</div>
                        <div class="text-xs text-[#737373]">{{ $attachment->mime_type }} — {{ number_format($attachment->file_size/1024, 1) }} KB</div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" onclick="openAttachmentView('{{ route('notes.attachments.view', $attachment) }}', '{{ $attachment->mime_type }}', '{{ addslashes($attachment->original_name) }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 text-xs font-bold hover:bg-[#f5f7f5] hover:border-[#d4ddd3] transition">{{ __('ui.view_attachment') }}</button>
                        @if(auth()->user()->isReportWriter())
                            <a href="{{ route('notes.attachments.download', $attachment) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition" title="{{ __('ui.download_writer_only') }}">↓</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    
    <div class="flex flex-wrap items-center gap-2 p-4 bg-white rounded-xl border border-[#e6e9e1]">
        @if($isOwner && $note->isDraft())
            <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg bg-surface-50 border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-surface-100 transition">{{ __('ui.edit_btn') }}</a>
            <form method="POST" action="{{ route('notes.send', $note) }}" class="inline">@csrf<button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">{{ __('ui.send_review') }}</button></form>
            <form method="POST" action="{{ route('notes.destroy', $note) }}" class="inline mr-auto" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">@csrf @method('DELETE')<button type="submit" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 text-sm font-bold hover:bg-red-50 transition">{{ __('ui.delete_report') }}</button></form>
        @elseif($isOwner && $note->isRejected())
            <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg bg-ink-800 text-white text-sm font-bold hover:bg-ink-900 transition">{{ __('ui.fix_resend') }}</a>
            <form method="POST" action="{{ route('notes.resend', $note) }}" class="inline">@csrf<button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition">{{ __('ui.resend_btn') }}</button></form>
        @elseif(auth()->user()->isReportWriter() && $note->isPending())
            <form method="POST" action="{{ route('notes.accept', $note) }}" class="inline">@csrf<button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">{{ __('ui.accept_approve') }}</button></form>
            <button type="button" onclick="openModal('reject-modal')" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">{{ __('ui.reject_reason_btn') }}</button>
        @endif
        <button type="button" onclick='openPrintModal(@json($printNoteData))' class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition mr-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            {{ __('ui.print_btn') }}
        </button>
    </div>
</div>


@if(auth()->user()->isReportWriter() && $note->isPending())
<div id="reject-modal" data-modal class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" onclick="closeModal('reject-modal')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-[#e6e9e1]"><h3 class="text-base font-extrabold text-ink-800">{{ __('ui.reject_note_title') }}</h3></div>
        <form method="POST" action="{{ route('notes.reject', $note) }}" class="p-6 space-y-4">@csrf<div><label class="block text-sm font-bold text-ink-700 mb-2">{{ __('ui.reject_reason') }} <span class="text-red-500">*</span></label><textarea name="rejection_reason" rows="4" required maxlength="2000" class="w-full rounded-xl border border-[#e6e9e1] bg-white py-3 px-4 text-sm leading-6 text-ink-800 placeholder:text-ink-300 focus:border-red-400 focus:ring-2 focus:ring-red-400/10 outline-none transition resize-none" placeholder="{{ __('ui.reject_reason_ph') }}"></textarea></div><div class="flex gap-3"><button type="button" onclick="closeModal('reject-modal')" class="flex-1 px-4 py-2 rounded-lg border border-[#e6e9e1] text-[#525252] font-bold text-sm">{{ __('ui.cancel_btn') }}</button><button type="submit" class="flex-1 px-4 py-2 rounded-lg bg-red-500 text-white font-bold text-sm hover:bg-red-600 transition">{{ __('ui.reject_note_title') }}</button></div></form>
    </div>
</div>
@endif

@include('notes.partials.print_modal')


<div id="attachment-view-modal" data-modal class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" onclick="closeModal('attachment-view-modal')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-surface-300 flex items-center justify-between gap-3 shrink-0">
            <h3 id="attachment-view-title" class="text-sm font-bold text-ink-800 truncate"></h3>
            <button type="button" onclick="closeModal('attachment-view-modal')" class="w-8 h-8 rounded-lg hover:bg-surface-100 flex items-center justify-center text-ink-400 hover:text-ink-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 min-h-0 bg-ink-900 flex items-center justify-center p-4 overflow-auto">
            <img id="attachment-view-image" class="hidden max-w-full max-h-[70vh] rounded-lg object-contain" oncontextmenu="return false;" draggable="false" alt="{{ __('ui.preview_image_alt') }}">
            <video id="attachment-view-video" class="hidden max-w-full max-h-[70vh] rounded-lg" controls controlsList="nodownload" oncontextmenu="return false;" disablePictureInPicture></video>
            <audio id="attachment-view-audio" class="hidden w-full max-w-md" controls controlsList="nodownload" oncontextmenu="return false;"></audio>
            <div id="attachment-view-fallback" class="hidden text-center text-white/70 text-sm">{{ __('ui.no_match_attach') }}</div>
        </div>
        <div class="px-4 py-3 border-t border-surface-300 bg-surface-50 flex items-center justify-between gap-3 shrink-0">
            <p class="text-xs text-ink-400">{{ __('ui.view_only') }}</p>
            <button type="button" onclick="closeModal('attachment-view-modal')" class="px-4 py-2 rounded-lg bg-white border border-surface-300 text-ink-600 text-sm font-bold hover:bg-surface-100 transition">{{ __('ui.close_camera') }}</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openAttachmentView(url, mime, name){
    const modal = document.getElementById('attachment-view-modal');
    const img = document.getElementById('attachment-view-image');
    const video = document.getElementById('attachment-view-video');
    const audio = document.getElementById('attachment-view-audio');
    const fallback = document.getElementById('attachment-view-fallback');
    const title = document.getElementById('attachment-view-title');
    if(title) title.textContent = name;
    img.classList.add('hidden'); img.src='';
    video.classList.add('hidden'); video.pause(); video.src=''; video.load();
    audio.classList.add('hidden'); audio.pause(); audio.src=''; audio.load();
    fallback.classList.add('hidden');
    if(mime.startsWith('image/')){
        img.src = url;
        img.classList.remove('hidden');
    } else if(mime.startsWith('video/')){
        video.src = url;
        video.classList.remove('hidden');
        video.load();
    } else if(mime.startsWith('audio/')){
        audio.src = url;
        audio.classList.remove('hidden');
        audio.load();
    } else {
        fallback.classList.remove('hidden');
        fallback.textContent = mime;
    }
    modal.classList.remove('hidden');
    document.body.style.overflow='hidden';
    img.oncontextmenu = () => false;
    video.oncontextmenu = () => false;
}

document.addEventListener('contextmenu', e=>{
    const modal = document.getElementById('attachment-view-modal');
    if(modal && !modal.classList.contains('hidden') && (e.target.tagName==='IMG' || e.target.tagName==='VIDEO')){
        e.preventDefault();
    }
});
</script>
@endpush
