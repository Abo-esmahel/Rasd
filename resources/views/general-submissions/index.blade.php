@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-extrabold text-ink-800">الإرسالات العامة</h1>
            <p class="text-sm text-ink-400">الإرسالات الموجهة إليك أو التي أنشأتها</p>
        </div>
        @can('create', App\Models\GeneralSubmission::class)
        <a href="{{ route('general-submissions.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm transition">
            + إرسال عام جديد
        </a>
        @endcan
    </div>

    @if($submissions->isEmpty())
        <div class="bg-white rounded-2xl border border-[#e6e9e1] p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-[#f5f7f5] flex items-center justify-center mx-auto mb-3">📋</div>
            <h3 class="font-bold text-ink-700">لا توجد إرسالات</h3>
            <p class="text-sm text-ink-400 mt-1">لم يتم إنشاء أي إرسال عام بعد</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($submissions as $submission)
                <a href="{{ route('general-submissions.show', $submission) }}" class="block bg-white rounded-xl border border-[#e6e9e1] p-5 hover:border-[#cde7d6] hover:shadow-sm transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono font-bold text-ink-400">#{{ str_pad($submission->id,4,'0',STR_PAD_LEFT) }}</span>
                                @if($submission->status === 'draft')
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-ink-100 text-ink-600">مسودة</span>
                                @elseif($submission->status === 'pending')
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">قيد المراجعة</span>
                                @elseif($submission->status === 'accepted')
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#eef4f0] text-[#0e6a38]">مقبولة</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700">مرفوضة</span>
                                @endif
                                <span class="text-xs text-ink-400">{{ $submission->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-ink-700 mt-2 line-clamp-2">{{ Str::limit($submission->description, 140) }}</p>
                            <div class="flex items-center gap-3 mt-2 text-xs text-ink-400">
                                <span>الطابق {{ $submission->floor_number }}</span>
                                <span>•</span>
                                <span>كاميرا {{ $submission->camera_number }}</span>
                                <span>•</span>
                                <span>{{ $submission->observed_at->format('Y-m-d H:i') }}</span>
                                @if($submission->attachments->count() > 0)
                                    <span>•</span>
                                    <span class="font-bold">📎 {{ $submission->attachments->count() }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 mt-3">
                                <span class="text-xs text-ink-400">المرسل:</span>
                                <span class="text-xs font-bold text-ink-700">{{ $submission->owner->name }}</span>
                                <span class="text-xs text-ink-300">→</span>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($submission->reportWriters as $w)
                                        <span class="px-2 py-0.5 rounded-full bg-[#f5f7f5] border border-[#e6e9e1] text-[11px] font-bold text-ink-600">{{ $w->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
