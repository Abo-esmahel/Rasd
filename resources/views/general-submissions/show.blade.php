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
        {{-- Actions --}}
        <div class="p-4 bg-[#f5f7f5] border-t border-[#e6e9e1] flex flex-wrap items-center gap-2">
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
</div>
@endsection
