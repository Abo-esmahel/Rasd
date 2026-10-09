@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl border border-[#e6e9e1] overflow-hidden shadow-sm">
        <div class="p-5 border-b border-[#e6e9e1] flex items-center gap-3">
            <div class="shrink-0 w-11 h-11 rounded-xl bg-[#eef4f0] text-[#0e6a38] flex items-center justify-center">
                @if(str_starts_with($mime, 'image/'))
                    <x-phosphor-image-duotone class="w-6 h-6" />
                @elseif(str_starts_with($mime, 'video/'))
                    <x-phosphor-video-camera-duotone class="w-6 h-6" />
                @elseif(str_starts_with($mime, 'audio/'))
                    <x-phosphor-music-note-duotone class="w-6 h-6" />
                @else
                    <x-phosphor-paperclip-duotone class="w-6 h-6" />
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-sm font-extrabold text-ink-800 truncate" title="{{ $name }}">{{ $name }}</h1>
                <p class="text-xs text-ink-400 mt-0.5">{{ $mime }} @if($size) — {{ number_format($size/1024/1024, 1) }} MB @endif</p>
            </div>
        </div>
        <div class="p-4 sm:p-5 bg-[#0f1a13]">
            @if(str_starts_with($mime, 'image/'))
                <img src="{{ $fileUrl }}" alt="{{ $name }}" class="w-full max-h-[70vh] object-contain rounded-xl bg-black" loading="eager">
            @elseif(str_starts_with($mime, 'video/'))
                <video src="{{ $fileUrl }}" class="w-full max-h-[70vh] rounded-xl bg-black" controls playsinline preload="metadata"></video>
            @elseif(str_starts_with($mime, 'audio/'))
                <div class="py-6 px-2">
                    <x-phosphor-music-note-duotone class="w-12 h-12 mx-auto text-[#4ade80]" />
                    <audio src="{{ $fileUrl }}" class="w-full mt-4" controls preload="metadata"></audio>
                </div>
            @else
                <div class="py-10 text-center">
                    <p class="text-sm font-bold text-white">{{ __('ui.no_preview_title') }}</p>
                    <p class="text-xs text-white/60 mt-1">{{ __('ui.no_preview_hint') }}</p>
                </div>
            @endif
        </div>
        <div class="p-4 flex flex-wrap items-center gap-2">
            @if($canDownload)
                <a href="{{ $downloadUrl }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-sm font-bold transition">
                    <x-phosphor-download-simple-duotone class="w-4 h-4" />
                    {{ __('ui.download_btn') }}
                </a>
            @endif
            <button type="button" onclick="sharedCopyLink(this)" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-white border border-[#e6e9e1] text-ink-700 text-sm font-bold hover:bg-[#f5f7f5] transition">{{ __('ui.copy_link') }}</button>
        </div>
    </div>
    <p class="mt-4 text-center text-xs text-ink-300">{{ __('ui.public_share_footer') }}</p>
</div>
@push('scripts')
<script>
function sharedCopyLink(btn){
    var T_OK = @json(__('ui.copied_ok'));
    var T_FAIL = @json(__('ui.copy_failed'));
    var done = function(ok){ var o = btn.textContent; btn.textContent = ok ? T_OK : T_FAIL; setTimeout(function(){ btn.textContent = o; }, 1200); };
    window.copyTextToClipboard(window.location.href).then(done);
}
</script>
@endpush
@endsection
