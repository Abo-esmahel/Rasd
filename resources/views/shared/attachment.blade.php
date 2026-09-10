@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl border border-[#e6e9e1] overflow-hidden shadow-sm">
        <div class="p-5 border-b border-[#e6e9e1] flex items-center gap-3">
            <div class="shrink-0 w-11 h-11 rounded-xl bg-[#eef4f0] text-[#0e6a38] flex items-center justify-center">
                @if(str_starts_with($mime, 'image/'))
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                @elseif(str_starts_with($mime, 'video/'))
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                @elseif(str_starts_with($mime, 'audio/'))
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                @else
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
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
                    <svg class="w-12 h-12 mx-auto text-[#4ade80]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-2v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <audio src="{{ $fileUrl }}" class="w-full mt-4" controls preload="metadata"></audio>
                </div>
            @else
                <div class="py-10 text-center">
                    <p class="text-sm font-bold text-white">لا توجد معاينة لهذا النوع</p>
                    <p class="text-xs text-white/60 mt-1">يمكن تنزيل الملف لعرضه</p>
                </div>
            @endif
        </div>
        <div class="p-4 flex flex-wrap items-center gap-2">
            @if($canDownload)
                <a href="{{ $downloadUrl }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-sm font-bold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    تنزيل
                </a>
            @endif
            <button type="button" onclick="sharedCopyLink(this)" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-white border border-[#e6e9e1] text-ink-700 text-sm font-bold hover:bg-[#f5f7f5] transition">نسخ الرابط</button>
        </div>
    </div>
    <p class="mt-4 text-center text-xs text-ink-300">وزارة الإعلام — مشاركة عامة</p>
</div>
@push('scripts')
<script>
function sharedCopyLink(btn){
    var done = function(){ var o = btn.textContent; btn.textContent = 'تم النسخ ✓'; setTimeout(function(){ btn.textContent = o; }, 1200); };
    if (navigator.clipboard) navigator.clipboard.writeText(window.location.href).then(done).catch(done); else done();
}
</script>
@endpush
@endsection
