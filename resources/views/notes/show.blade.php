@extends('layouts.app')

@php
    $isOwner = auth()->id() === $note->user_id;
    $isWriter = auth()->user()->isReportWriter();
    $reactionLabels = ['like' => ['👍', __('ui.like')], 'love' => ['❤️', __('ui.love')], 'insightful' => ['💡', __('ui.insightful')], 'thanks' => ['🙏', __('ui.thanks')], 'dislike' => ['👎', __('ui.dislike')], 'angry' => ['😡', __('ui.angry')]];
    $noteReactions = $note->reactions->groupBy('reaction')->map->count();
    $myNoteReaction = $note->reactions->firstWhere('user_id', auth()->id())?->reaction;
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
        'owner_name' => $note->owner->localized_name,
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
    
    <div class="flex items-center gap-2.5 sm:gap-3">
        <a href="{{ route('notes.index') }}" class="w-9 h-9 sm:w-9 sm:h-9 min-w-[36px] min-h-[36px] rounded-lg bg-white border border-[#e6e9e1] flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-[#f5f7f5] transition shrink-0" aria-label="{{ __('ui.back') }}">
            <x-phosphor-caret-right-duotone class="w-5 h-5" />
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="text-[16px] sm:text-lg font-extrabold text-ink-800 leading-tight inline-flex items-center gap-1.5 flex-wrap"><span>{{ __('ui.camera') }} <span dir="ltr" class="tabular-nums">{{ $note->camera_number }}</span></span> <span class="opacity-60">·</span> <span>{{ __('ui.floor') }} <span dir="ltr" class="tabular-nums">{{ $note->floor_number }}</span></span></h1>
            <p class="text-[11px] sm:text-xs text-[#737373] truncate" data-no-translate><a href="{{ route('profile.showUser', $note->owner->id) }}" class="hover:text-[#0e6a38] hover:underline transition">{{ $note->owner->localized_name }}</a></p>
        </div>
        <span class="shrink-0 inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1 rounded-full text-[11px] sm:text-xs font-bold
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

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3">
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-2.5 sm:p-3 text-center">
            <div class="text-[10px] sm:text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.camera') }}</div>
            <div class="text-[17px] sm:text-lg font-extrabold text-ink-800 tabular-nums" dir="ltr">{{ $note->camera_number }}</div>
        </div>
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-2.5 sm:p-3 text-center">
            <div class="text-[10px] sm:text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.floor') }}</div>
            <div class="text-[17px] sm:text-lg font-extrabold text-ink-800 tabular-nums" dir="ltr">{{ $note->floor_number }}</div>
        </div>
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-2.5 sm:p-3 text-center">
            <div class="text-[10px] sm:text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.time_label') }}</div>
            <div class="text-[13px] sm:text-sm font-bold text-ink-800 tabular-nums" dir="ltr">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? ' — '.$note->observed_end_at->toTime12() : '' }}</div>
            <div class="text-[10px] sm:text-[11px] text-[#737373] tabular-nums" dir="ltr">{{ $note->observed_at->format('Y-m-d') }}</div>
        </div>
        <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-2.5 sm:p-3 text-center">
            <div class="text-[10px] sm:text-[11px] font-bold text-ink-300 mb-1">{{ __('ui.attachments') }}</div>
            <div class="text-[17px] sm:text-lg font-extrabold text-ink-800 tabular-nums" dir="ltr">{{ $note->attachments->count() }}</div>
            <div class="text-[10px] sm:text-[11px] text-[#737373]">{{ __('ui.file_label') }}</div>
        </div>
    </div>

    
    <div class="flex items-center gap-3 p-3 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1]">
        <x-user-link :user="$note->owner" size="lg" shape="rounded-lg" skin="soft" weight="font-bold" img-extra-class="border border-[#e6e9e1] shadow-sm" />
        <div>
            <div class="text-[11px] font-bold text-ink-300">{{ __('ui.observer') }}</div>
            <div class="text-sm font-bold text-ink-800"><a href="{{ route('profile.showUser', $note->owner->id) }}" class="hover:text-[#0e6a38] hover:underline transition">{{ $note->owner->localized_name }}</a></div>
        </div>
        <div class="mr-auto text-left">
            <div class="text-[11px] font-bold text-ink-300">{{ __('ui.created_at') }}</div>
            <div class="text-xs font-medium text-[#525252]">{{ $note->created_at->toDatetime12() }}</div>
        </div>
    </div>

    
    <div class="bg-white rounded-2xl border border-[#e6e9e1] p-4 sm:p-6">
        <h3 class="text-[13px] sm:text-sm font-bold text-ink-700 mb-2.5 sm:mb-3 flex items-center gap-2">
            <x-phosphor-file-text-duotone class="w-4 h-4 text-[#0e6a38]" />
            {{ __('ui.description') }}
        </h3>
        @if(!empty($note->description))
            <div class="p-3 sm:p-4 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] text-[14px] sm:text-sm leading-[1.8] sm:leading-[1.9] text-ink-700 whitespace-pre-wrap break-words min-h-[60px]" data-i18n-field="description">{{ l10n_text('note', $note->id, 'description', $note->description) }}</div>
        @else
            <div class="p-4 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] text-sm leading-[1.9] text-ink-400 italic">{{ __('ui.no_description') }}</div>
        @endif
        @if($note->isRejected() && $note->rejection_reason)
            <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-200">
                <h4 class="text-sm font-bold text-red-700 mb-2">{{ __('ui.reject_reason') }}</h4>
                <p class="text-sm leading-7 text-red-600" data-i18n-field="rejection_reason">{{ l10n_text('note', $note->id, 'rejection_reason', $note->rejection_reason) }}</p>
                @if($note->processor)
                    <div class="mt-2 text-xs font-bold text-red-500">{{ __('ui.by_user') }} <a href="{{ route('profile.showUser', $note->processor->id) }}" class="hover:underline transition">{{ $note->processor->localized_name }}</a> — {{ $note->processed_at?->toDatetime12() }}</div>
                @endif
            </div>
        @endif
    </div>

    @if($note->general_submission_id === null)
    <section id="note-reactions" class="flex flex-wrap items-center justify-between gap-3 p-3.5 sm:p-4 rounded-xl bg-white border border-[#e6e9e1] scroll-mt-4">
        <h3 class="text-[12.5px] font-extrabold text-ink-700">{{ __('ui.note_reactions') }}</h3>
        <div class="flex flex-wrap gap-1.5">
            @foreach($reactionLabels as $type => [$emoji, $label])
                <form method="POST" action="{{ route('notes.reaction', $note) }}">
                    @csrf @method('PUT')<input type="hidden" name="reaction" value="{{ $type }}">
                    <button type="submit" aria-pressed="{{ $myNoteReaction === $type ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5 min-h-9 px-2.5 rounded-full border text-[11.5px] font-bold transition {{ $myNoteReaction === $type ? 'bg-[#e8f3ec] border-[#9dc9aa] text-[#0e6a38]' : 'bg-[#f9faf8] border-[#e6e9e1] text-[#6b7a6e] hover:border-[#0e6a38] hover:text-[#0e6a38]' }}" title="{{ $label }}"><span aria-hidden="true">{{ $emoji }}</span><span>{{ $noteReactions[$type] ?? 0 }}</span></button>
                </form>
            @endforeach
        </div>
    </section>
    @endif

    
    @if($note->attachments->count() > 0)
    <div class="bg-white rounded-2xl border border-[#e6e9e1] p-4 sm:p-6">
        <h3 class="text-[13px] sm:text-sm font-bold text-ink-700 mb-2.5 sm:mb-3 flex items-center gap-2">
            <x-phosphor-paperclip-duotone class="w-4 h-4 text-[#0e6a38]" />
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
                            <x-phosphor-video-camera-duotone class="w-4 h-4 text-[#737373]" />
                        @elseif(str_contains($attachment->mime_type, 'audio'))
                            <x-phosphor-microphone-duotone class="w-4 h-4 text-red-500" />
                        @else
                            <x-phosphor-image-duotone class="w-4 h-4 text-[#0e6a38]" />
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-bold text-ink-700 truncate">{{ $attachment->original_name }}</div>
                        <div class="text-xs text-[#737373]">{{ $attachment->mime_type }} — {{ number_format($attachment->file_size/1024, 1) }} KB</div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" onclick="openAttachmentView('{{ route('notes.attachments.view', $attachment) }}', '{{ $attachment->mime_type }}', '{{ addslashes($attachment->original_name) }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 text-xs font-bold hover:bg-[#f5f7f5] hover:border-[#d4ddd3] transition">{{ __('ui.view_attachment') }}</button>
                        @if(auth()->user()->isReportWriter() || auth()->user()->isGeneralManager())
                            <a href="{{ route('notes.attachments.download', $attachment) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition" title="{{ __('ui.download_writer_only') }}">↓</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    
    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2 sm:gap-2 p-3 sm:p-4 bg-white rounded-xl border border-[#e6e9e1]">
        @if($isOwner && $note->isDraft())
            <div class="flex gap-2 w-full sm:w-auto">
                <a href="{{ route('notes.edit', $note) }}" class="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-lg bg-surface-50 border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-surface-100 transition">{{ __('ui.edit_btn') }}</a>
                <form method="POST" action="{{ route('notes.send', $note) }}" class="flex-1 sm:flex-none inline">@csrf<button type="submit" class="w-full px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">{{ __('ui.send_review') }}</button></form>
            </div>
            <form method="POST" action="{{ route('notes.destroy', $note) }}" class="w-full sm:w-auto sm:mr-auto" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">@csrf @method('DELETE')<button type="submit" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-lg border border-red-200 text-red-600 text-sm font-bold hover:bg-red-50 transition">{{ __('ui.delete_report') }}</button></form>
        @elseif($isOwner && $note->isRejected())
            <div class="flex gap-2 w-full sm:w-auto">
                <a href="{{ route('notes.edit', $note) }}" class="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-lg bg-ink-800 text-white text-sm font-bold hover:bg-ink-900 transition">{{ __('ui.fix_resend') }}</a>
                <form method="POST" action="{{ route('notes.resend', $note) }}" class="flex-1 sm:flex-none inline">@csrf<button type="submit" class="w-full px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition">{{ __('ui.resend_btn') }}</button></form>
            </div>
        @elseif(auth()->user()->isReportWriter() && $note->isPending())
            <div class="flex gap-2 w-full sm:w-auto">
                <form method="POST" action="{{ route('notes.accept', $note) }}" class="flex-1 sm:flex-none inline">@csrf<button type="submit" class="w-full px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">{{ __('ui.accept_approve') }}</button></form>
                <button type="button" onclick="openModal('reject-modal')" class="flex-1 sm:flex-none px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">{{ __('ui.reject_reason_btn') }}</button>
            </div>
        @endif
        <button type="button" onclick='openPrintModal(@json($printNoteData))' class="w-full sm:w-auto sm:mr-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 sm:py-2 min-h-[44px] sm:min-h-0 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition">
            <x-phosphor-printer-duotone class="w-4 h-4" />
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
                <x-phosphor-x-duotone class="w-5 h-5" />
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
