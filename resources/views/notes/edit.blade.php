@extends('layouts.app')

@section('content')
@php
    $statusConfig = [
        'draft' => ['label'=>'مسودة','cls'=>'bg-ink-100 text-ink-500 border-[#e6e9e1]','dot'=>'bg-ink-300'],
        'pending' => ['label'=>'قيد المراجعة','cls'=>'bg-amber-50 text-amber-700 border-amber-200','dot'=>'bg-amber-400'],
        'accepted' => ['label'=>'مقبولة','cls'=>'bg-[#eef4f0] text-[#0e6a38] border-[#cde7d6]','dot'=>'bg-[#0e6a38]'],
        'rejected' => ['label'=>'مرفوضة','cls'=>'bg-red-50 text-red-700 border-red-200','dot'=>'bg-red-400'],
    ];
    $current = $statusConfig[$note->status] ?? $statusConfig['draft'];
@endphp

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div class="flex items-center gap-3 min-w-0">
        <a href="{{ route('notes.index') }}" class="w-9 h-9 rounded-lg bg-white border border-[#e6e9e1] flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-[#f5f7f5] transition shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-lg font-extrabold text-ink-800 leading-none">تعديل الملاحظة</h1>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $current['cls'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $current['dot'] }}"></span>{{ $current['label'] }}
                </span>
                <span class="text-xs text-ink-300 font-mono">#{{ $note->id }}</span>
            </div>
            <p class="text-xs text-ink-400 mt-1">آخر تحديث: {{ $note->updated_at->toDatetime12() }} — الملاحظة: {{ $note->observed_at->toDatetime12() }}</p>
        </div>
    </div>
    @if($note->isAccepted())
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold shrink-0">مُعتمدة — لا يمكن التعديل</span>
    @endif
</div>

<div class="max-w-4xl mx-auto space-y-4">
    @if($note->status === 'rejected' && $note->rejection_reason)
        <div class="flex gap-3 p-4 bg-red-50 border border-red-200 rounded-xl">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-red-700">سبب الرفض — يرجى التصحيح</div>
                <p class="mt-1 text-sm leading-6 text-red-600 break-words">{{ $note->rejection_reason }}</p>
                @if($note->processor)
                    <div class="mt-1.5 text-xs font-bold text-red-500">بواسطة {{ $note->processor->name }} — {{ $note->processed_at?->toDatetime12() }}</div>
                @endif
            </div>
        </div>
    @endif

    @if($note->isAccepted())
        <div class="flex gap-3 p-4 bg-[#eef4f0] border border-[#cde7d6] rounded-xl text-[#0e6a38]">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm leading-6 font-bold">هذه الملاحظة مقبولة نهائياً ولا يمكن تعديلها.</p>
        </div>
    @endif

    <div class="bg-[#fdfcfa] rounded-2xl border border-[#e6e9e1] overflow-hidden">
        <form method="POST" action="{{ route('notes.update', $note, false) }}" enctype="multipart/form-data" class="p-6 space-y-5" id="edit-form" novalidate>
            @csrf @method('PUT')
            <div id="form-errors-edit" class="hidden p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>
            <div id="upload-progress-edit" class="hidden p-4 bg-[#eef4f0] border border-[#cde7d6] rounded-xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-bold text-[#0e6a38] flex items-center gap-2"><span class="w-3 h-3 border-2 border-[#0e6a38] border-t-transparent rounded-full animate-spin"></span>جاري الرفع...</span>
                    <span id="upload-progress-text-edit" class="text-xs font-bold text-ink-500">0%</span>
                </div>
                <div class="w-full bg-white rounded-full h-2.5 border border-[#e6e9e1] overflow-hidden">
                    <div id="upload-progress-bar-edit" class="h-2.5 rounded-full bg-[#0e6a38] transition-all duration-300" style="width:0%"></div>
                </div>
                <div class="mt-1 text-[11px] text-ink-400">جاري رفع الملفات الأصلية دون تعديل (الجودة 100% محفوظة) — لا تغلق الصفحة</div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-ink-700 mb-1.5">رقم الطابق <span class="text-red-500">*</span></label>
                    <input type="number" name="floor_number" value="{{ old('floor_number', $note->floor_number) }}" min="0" required
                        class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-3 px-4 text-sm font-medium text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-ink-700 mb-1.5">رقم الكاميرا <span class="text-red-500">*</span></label>
                    <input type="number" name="camera_number" value="{{ old('camera_number', $note->camera_number) }}" min="1" required
                        class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-3 px-4 text-sm font-medium text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                </div>
            </div>

            <div class="rounded-xl border border-[#e6e9e1] bg-[#f5f7f5] p-4" id="edit-datetime-wrap">
                <label class="block text-sm font-bold text-ink-700 mb-1">الملاحظة <span class="text-red-500">*</span></label>
                @php
                    $editObserved = old('observed_at', $note->observed_at->format('Y-m-d\TH:i'));
                    $editDate = $editObserved ? date('Y-m-d', strtotime($editObserved)) : '';
                    $editTime = $editObserved ? date('H:i', strtotime($editObserved)) : '';
                    $editObservedEnd = old('observed_end_at', $note->observed_end_at ? $note->observed_end_at->format('Y-m-d\TH:i') : '');
                    $editEndTime = $editObservedEnd ? date('H:i', strtotime($editObservedEnd)) : '';
                @endphp
                <div class="space-y-3 mt-2">
                    <div>
                        <div class="text-xs font-bold text-ink-500 mb-1.5">التاريخ</div>
                        <input type="date" id="observed_date_edit" value="{{ $editDate }}" required
                            class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-2.5 px-4 text-sm font-bold text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                            style="color-scheme: light;">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-xs font-bold text-ink-500 mb-1.5">بداية الملاحظة</div>
                            <input type="time" id="observed_time_edit" value="{{ $editTime }}" required step="60"
                                class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-2.5 px-4 text-sm font-bold text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                                style="color-scheme: light;">
                        </div>
                        <div>
                            <div class="text-xs font-bold text-ink-500 mb-1.5">انتهاء الملاحظة</div>
                            <input type="time" id="observed_end_time_edit" value="{{ $editEndTime }}" step="60"
                                class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-2.5 px-4 text-sm font-bold text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                                style="color-scheme: light;">
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <button type="button" data-preset="now" class="preset-btn-edit px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition">الآن</button>
                    <button type="button" data-preset="hour-ago" class="preset-btn-edit px-3 py-1.5 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#1a2e1f] text-xs font-bold hover:bg-[#f5f7f5] transition">قبل ساعة</button>
                    <button type="button" data-preset="today-08" class="preset-btn-edit px-3 py-1.5 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#1a2e1f] text-xs font-bold hover:bg-[#f5f7f5] transition">اليوم 08:00</button>
                    <button type="button" id="clear-datetime-edit" class="px-3 py-1.5 rounded-lg bg-transparent border border-[#e6e9e1] text-ink-400 text-xs font-bold hover:bg-white transition">مسح</button>
                </div>
                <div class="mt-3 flex items-center gap-2 p-2.5 rounded-lg bg-white border border-[#e6e9e1]">
                    <svg class="w-4 h-4 text-[#0e6a38] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div id="observed_preview_edit" class="text-sm font-bold text-ink-700 truncate">—</div>
                </div>
                <input type="hidden" name="observed_at" id="observed_at_edit" value="{{ old('observed_at', $note->observed_at->format('Y-m-d\TH:i')) }}">
                <input type="hidden" name="observed_end_at" id="observed_end_at_edit" value="{{ old('observed_end_at', $note->observed_end_at ? $note->observed_end_at->format('Y-m-d\TH:i') : '') }}">
                @error('observed_at') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                @error('observed_end_at') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-ink-700 mb-1.5">الوصف <span class="text-red-500">*</span></label>
                <textarea name="description" rows="5" required
                    class="block w-full rounded-xl border border-[#e6e9e1] bg-white p-4 text-sm leading-7 text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition resize-none">{{ old('description', $note->description) }}</textarea>
            </div>

            @if($note->attachments->count() > 0)
                <div class="rounded-xl border border-[#e6e9e1] bg-[#f5f7f5] p-4">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-sm font-bold text-ink-700">المرفقات الحالية</label>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-white border border-[#e6e9e1] text-ink-400">{{ $note->attachments->count() }} / 5</span>
                    </div>
                    <div class="space-y-2">
                        @foreach($note->attachments as $attachment)
                            <div class="flex items-center gap-3 p-3 bg-white border border-[#e6e9e1] rounded-lg">
                                <div class="w-9 h-9 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center shrink-0">
                                    @if(str_contains($attachment->mime_type, 'video'))
                                            <svg class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        @elseif(str_contains($attachment->mime_type, 'audio'))
                                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                        @else
                                        <svg class="w-4 h-4 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 text-right">
                                    <button type="button" onclick="openAttachmentView('{{ route('notes.attachments.view', $attachment) }}', '{{ $attachment->mime_type }}', '{{ addslashes($attachment->original_name) }}')" class="block text-sm font-bold text-ink-700 hover:text-[#0e6a38] truncate transition text-right w-full">{{ $attachment->original_name }}</button>
                                    <div class="text-xs text-ink-400">{{ $attachment->mime_type }} — {{ number_format($attachment->file_size / 1024, 1) }} KB</div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button" onclick="openAttachmentView('{{ route('notes.attachments.view', $attachment) }}', '{{ $attachment->mime_type }}', '{{ addslashes($attachment->original_name) }}')" class="w-8 h-8 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center text-ink-400 hover:text-[#0e6a38] hover:bg-white transition" aria-label="عرض">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    @if(auth()->user()->isReportWriter())
                                        <a href="{{ route('notes.attachments.download', $attachment) }}" class="w-8 h-8 rounded-lg bg-[#0e6a38] text-white flex items-center justify-center hover:bg-[#0a4d28] transition" aria-label="تنزيل" title="تنزيل (للمدير فقط)">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </a>
                                    @endif
                                    @if(!$note->isAccepted())
                                        {{-- زر حذف مرتبط بنموذج حذف مشترك خارج نموذج التعديل (نماذج متداخلة غير صالحة كانت تكسر FormData) --}}
                                        <button type="submit" form="attach-del-form" formaction="{{ route('notes.attachments.destroy', [$note, $attachment], false) }}" formmethod="post" onclick="return confirm('هل أنت متأكد من الحذف؟')" class="w-8 h-8 rounded-lg bg-red-50 border border-red-200 flex items-center justify-center text-red-400 hover:bg-red-100 transition" aria-label="حذف">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!$note->isAccepted())
                <div>
                    <label class="block text-sm font-bold text-ink-700 mb-1.5">إضافة مرفقات</label>
                    <div class="rounded-xl border-2 border-dashed border-[#e6e9e1] bg-[#f5f7f5] hover:border-[#0e6a38] hover:bg-[#f5f7f5] p-5 text-center transition" id="edit-drop-zone">
                        <div class="mx-auto w-10 h-10 rounded-xl bg-white border border-[#e6e9e1] flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <div class="mt-3 flex flex-col sm:flex-row items-center justify-center gap-2">
                            <label for="edit-files" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#0e6a38] text-white font-bold text-sm cursor-pointer hover:bg-[#0a4d28] transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                اختيار ملفات
                            </label>
                            <button type="button" id="open-camera-btn-edit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 font-bold text-sm hover:bg-[#f5f7f5] transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 13a3 3 0 100-6 3 3 0 000 6z"/></svg>
                                الكاميرا المباشرة
                            </button>
                            <button type="button" id="audio-record-btn-edit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 font-bold text-sm hover:bg-[#f5f7f5] transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                تسجيل صوتي
                            </button>
                        </div>
                        <div id="audio-preview-edit" class="hidden mt-3 p-3 bg-white border border-[#e6e9e1] rounded-xl">
                            <div class="flex items-center gap-3">
                                <div id="audio-preview-icon-edit" class="w-10 h-10 rounded-lg bg-red-50 border border-red-200 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-red-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div id="audio-preview-status-edit" class="text-sm font-bold text-ink-700">جاري طلب الميكروفون...</div>
                                    <div id="audio-preview-timer-edit" class="text-xs text-ink-400 mt-0.5 hidden">00:00</div>
                                </div>
                                <button type="button" id="audio-preview-delete-edit" class="hidden shrink-0 w-8 h-8 rounded-lg hover:bg-red-50 flex items-center justify-center text-ink-400 hover:text-red-500 transition" title="حذف التسجيل">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <audio id="audio-preview-player-edit" class="hidden w-full mt-2 rounded-lg" controls></audio>
                            <div id="audio-preview-pending-edit" class="hidden mt-2 text-[11px] text-[#0e6a38] font-bold">سيتم إرفاقه عند حفظ الملاحظة</div>
                        </div>
                        <input type="file" id="edit-files" name="files[]" multiple accept="image/*,video/*,audio/*,.aac,.m4a,.mp3,.wav,.ogg,.flac,.opus,.wma,.aiff,.amr,.3ga,.weba" class="hidden">
                        <div id="edit-file-list" class="mt-3 hidden space-y-1.5 text-right"></div>
                    </div>

                    {{-- Camera Live Modal for Edit --}}
                    <div id="camera-modal-edit" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" id="camera-backdrop-edit"></div>
                        <div class="relative bg-white rounded-2xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                            <div class="px-4 py-3 border-b border-[#e6e9e1] flex items-center justify-between shrink-0">
                                <h3 class="text-sm font-extrabold text-ink-800 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                    الكاميرا المباشرة
                                    <span id="camera-mode-label-edit" class="text-xs font-medium text-ink-400 mr-1">— صورة</span>
                                </h3>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" id="switch-camera-btn-edit" class="w-8 h-8 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center text-ink-500 hover:text-ink-700 transition" title="تبديل الكاميرا">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/></svg>
                                    </button>
                                    <button type="button" id="close-camera-btn-edit" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-400 hover:text-ink-700 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex-1 min-h-0 bg-black flex flex-col items-center justify-center p-3 sm:p-4 relative overflow-hidden">
                                <video id="camera-video-edit" autoplay playsinline muted class="w-full h-auto max-h-[50vh] rounded-xl bg-black object-contain hidden"></video>
                                <canvas id="camera-canvas-edit" class="hidden"></canvas>
                                <div id="camera-placeholder-edit" class="text-center py-12">
                                    <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </div>
                                    <p class="text-sm text-white/70">جاري تشغيل الكاميرا...</p>
                                    <p id="camera-error-edit" class="hidden mt-2 text-xs text-red-300 max-w-xs mx-auto leading-5"></p>
                                </div>
                                <div id="camera-fallback-edit" class="w-full max-w-md p-4 border-t border-amber-200 bg-amber-50/50">
                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                            <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/><circle cx="12" cy="13" r="3"/></svg>
                                            <span class="text-sm font-bold text-ink-800">التقاط صورة</span>
                                            <input type="file" accept="image/*" capture="environment" class="hidden" onchange="handleFallbackFileEdit(this)">
                                        </label>
                                        <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                            <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            <span class="text-sm font-bold text-ink-800">تسجيل فيديو</span>
                                            <input type="file" accept="video/*" capture="environment" class="hidden" onchange="handleFallbackFileEdit(this)">
                                        </label>
                                        <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                            <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                            <span class="text-sm font-bold text-ink-800">تسجيل صوتي</span>
                                            <input type="file" accept="audio/*" capture="user" class="hidden" onchange="handleFallbackFileEdit(this)">
                                        </label>
                                    </div>
                                    <p class="mt-2 text-center text-[10px] text-ink-400">الكاميرا المباشرة (أعلى) تعمل فقط على https:// و localhost — البديل يعمل على كل شيء</p>
                                </div>
                                <div id="camera-timer-edit" class="hidden absolute top-4 right-4 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                                    <span id="camera-timer-text-edit">00:00</span>
                                </div>
                            </div>
                            <div class="p-4 border-t border-[#e6e9e1] bg-white shrink-0">
                                <div class="flex gap-2 justify-center flex-wrap">
                                    <button type="button" id="capture-photo-btn-edit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h2l2-2h4l2 2h2a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/><circle cx="12" cy="13" r="3"/></svg>
                                        التقاط صورة
                                    </button>
                                    <button type="button" id="start-record-btn-edit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-white border-2 border-red-200 text-red-600 font-bold text-sm hover:bg-red-50 transition">
                                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                        بدء تسجيل فيديو
                                    </button>
                                    <button type="button" id="stop-record-btn-edit" class="hidden flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-sm transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="6" y="6" width="12" height="12" rx="1"/></svg>
                                        إيقاف وحفظ
                                    </button>
                                </div>
                                <p class="mt-2.5 text-center text-[11px] text-ink-400 leading-4">الصور والفيديو تُضاف مباشرة للمرفقات دون حفظ في معرض الجهاز</p>
                                <p id="camera-status-edit" class="hidden mt-2 text-center text-xs font-bold"></p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex gap-3 pt-4 border-t border-[#e6e9e1]">
                <a href="{{ route('notes.index') }}" class="px-5 py-2.5 rounded-xl bg-transparent border border-[#e6e9e1] text-[#525252] font-bold text-sm hover:bg-[#f5f7f5] transition">إلغاء</a>
                <button type="submit" class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition sm:mr-auto">حفظ التعديلات</button>
            </div>
        </form>
        {{-- نموذج حذف المرفقات المشترك: خارج نموذج التعديل (التداخل غير صالح ويكسر FormData).
             أزرار الحذف أعلاه ترتبط به عبر form="attach-del-form" مع formaction لكل مرفق. --}}
        <form id="attach-del-form" method="POST" class="hidden" aria-hidden="true">
            @csrf @method('DELETE')
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function(){
        const dateEl=document.getElementById('observed_date_edit');
        const timeEl=document.getElementById('observed_time_edit');
        const endTimeEl=document.getElementById('observed_end_time_edit');
        const hidden=document.getElementById('observed_at_edit');
        const hiddenEnd=document.getElementById('observed_end_at_edit');
        const preview=document.getElementById('observed_preview_edit');
        function pad(n){return String(n).padStart(2,'0');}
        function toPreview(date,time){
            if(!date||!time) return null;
            const d=new Date(date+'T'+time);
            if(isNaN(d)) return null;
            try{ return d.toLocaleDateString('ar-EG',{weekday:'long',year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'});}catch(e){return date+' '+time;}
        }
        function sync(){
            const d=dateEl?.value, t=timeEl?.value, et=endTimeEl?.value;
            if(d && t){ hidden.value=d+'T'+t; const txt=toPreview(d,t); const endTxt=et?' — '+et:''; if(preview) preview.textContent=(txt||(d+' — '+t))+endTxt; hidden.setCustomValidity(''); }
            else { hidden.value=''; if(preview) preview.textContent='— اختر التاريخ والوقت —'; }
            if(d && et){
                let endVal=d+'T'+et;
                try{ if(t && et < t){ const nd=new Date(d+'T'+et); nd.setDate(nd.getDate()+1); const pad=n=>String(n).padStart(2,'0'); endVal=nd.getFullYear()+'-'+pad(nd.getMonth()+1)+'-'+pad(nd.getDate())+'T'+et; } }catch(_){}
                hiddenEnd.value=endVal;
            } else { hiddenEnd.value=''; }
        }
        dateEl?.addEventListener('change',sync); timeEl?.addEventListener('change',sync); endTimeEl?.addEventListener('change',sync);
        dateEl?.addEventListener('input',sync); timeEl?.addEventListener('input',sync); endTimeEl?.addEventListener('input',sync);
        document.querySelectorAll('.preset-btn-edit').forEach(btn=>{
            btn.addEventListener('click',()=>{
                const now=new Date(); let d=new Date(now); const p=btn.dataset.preset;
                if(p==='hour-ago') d=new Date(now.getTime()-60*60*1000);
                else if(p==='today-08'){ d=new Date(now); d.setHours(8,0,0,0); }
                if(dateEl) dateEl.value=`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
                if(timeEl) timeEl.value=`${pad(d.getHours())}:${pad(d.getMinutes())}`;
                sync();
            });
        });
        document.getElementById('clear-datetime-edit')?.addEventListener('click',()=>{ if(dateEl) dateEl.value=''; if(timeEl) timeEl.value=''; if(endTimeEl) endTimeEl.value=''; sync(); });
        sync();
    })();
    // \u2014\u2014\u2014 \u0627\u0644\u062d\u0641\u0627\u0638 \u0639\u0644\u0649 \u0627\u0644\u0645\u0644\u0641 \u0627\u0644\u0623\u0635\u0644\u064a 100% \u2014 \u0644\u0627 \u0636\u063a\u0637\u060c \u0644\u0627 resize\u060c \u0644\u0627 transcoding \u2014 byte-for-byte \u2014\u2014\u2014
    async function compressImageClientEdit(file){ return file; } // preserved 100% original - no compression
    function setUploadProgressEdit(pct, detail){
        const wrap=document.getElementById('upload-progress-edit'), bar=document.getElementById('upload-progress-bar-edit'), txt=document.getElementById('upload-progress-text-edit');
        if(!wrap) return;
        wrap.classList.remove('hidden');
        if(bar) bar.style.width=pct+'%';
        if(txt) txt.textContent=Math.round(pct)+'%';
    }
    function hideUploadProgressEdit(){ const w=document.getElementById('upload-progress-edit'); if(w) w.classList.add('hidden'); }
    function xhrUploadEdit(url, fd, csrf, onProgress){
        return new Promise((resolve, reject)=>{
            const xhr=new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Accept','application/json');
            xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
            if(csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.timeout=600000;
            if(xhr.upload && onProgress) xhr.upload.onprogress=(e)=>{ if(e.lengthComputable){ const pct=Math.round(e.loaded/e.total*100); const loadedMB=(e.loaded/1024/1024).toFixed(1); const totalMB=(e.total/1024/1024).toFixed(1); onProgress(pct, e.loaded, e.total); } };
            xhr.onload=()=>{ let data=null; try{ data=JSON.parse(xhr.responseText);}catch(_){} resolve({status:xhr.status, ok:xhr.status>=200&&xhr.status<300, data, raw:xhr.responseText}); };
            xhr.onerror=()=>reject(new Error('فشل الشبكة'));
            xhr.ontimeout=()=>reject(Object.assign(new Error('انتهت مهلة الإرسال'),{name:'AbortError'}));
            xhr.send(fd);
        });
    }

    // ——— Edit file handling with DataTransfer ———
    const input=document.getElementById('edit-files'),zone=document.getElementById('edit-drop-zone'),list=document.getElementById('edit-file-list');
    let fileTransferEdit = new DataTransfer();
    // UPLOAD INTENT — عدّاد مستقل عن مخازن النقل — ROOT CAUSE FIX (انظر create.blade.php)
    let intendedFilesCountEdit = 0;
    // مصدر حقيقة احتياطي: DataTransfer يُسقط ملفات الفيديو الكبيرة بصمت في بعض متصفحات الجوال
    let pendingFilesEdit = [];
    // حد الملفات من السيرفر (max_file_uploads) — تجاوزه يجعل PHP يسقط الملفات الزائدة بصمت
    const MAX_FILES_EDIT = {{ max(1, (int) ini_get('max_file_uploads') ?: 20) }};
    function syncInputEdit(){ try{ input.files = fileTransferEdit.files; }catch(e){ /* fileTransferEdit remains source of truth */ } }
    function renderEdit(){
        const files=Array.from(fileTransferEdit.files);
        if(!files.length){ list.classList.add('hidden'); list.innerHTML=''; return; }
        list.classList.remove('hidden');
        list.innerHTML=files.map((f,i)=>{
            const isV=f.type.startsWith('video/');
            const isA=f.type.startsWith('audio/');
            const sz=(f.size/1024/1024).toFixed(2)+' MB';
            const badge=f.name.startsWith('camera-')?'<span class="text-[10px] bg-[#eef4f0] text-[#0e6a38] px-1.5 py-0.5 rounded-full font-bold">كاميرا</span>':'';
            const recBadge=f.name.startsWith('recording-')?'<span class="text-[10px] bg-red-50 text-red-600 px-1.5 py-0.5 rounded-full font-bold">تسجيل</span>':'';
            return `<div class="flex items-center gap-2.5 p-2.5 bg-white border border-[#e6e9e1] rounded-lg text-sm group"><span class="text-sm">${isA?'🎤':isV?'🎬':'🖼️'}</span><div class="flex-1 min-w-0 text-right"><div class="font-bold text-ink-700 truncate flex items-center gap-1.5">${f.name} ${badge} ${recBadge}</div><div class="text-xs text-ink-400">${sz} • ${f.type||'—'}</div></div><button type="button" data-remove-edit="${i}" class="shrink-0 w-7 h-7 rounded-lg hover:bg-red-50 text-ink-300 hover:text-red-500 flex items-center justify-center transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></div>`;
        }).join('')+(files.length>MAX_FILES_EDIT?'<p class="text-xs font-bold text-red-500">الحد '+MAX_FILES_EDIT+' ملف</p>':'');
        list.querySelectorAll('[data-remove-edit]').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                const idx=parseInt(btn.dataset.removeEdit);
                const removedFile=Array.from(fileTransferEdit.files)[idx];
                const dt=new DataTransfer();
                Array.from(fileTransferEdit.files).forEach((file,j)=>{ if(j!==idx) dt.items.add(file); });
                fileTransferEdit=dt; intendedFilesCountEdit=Math.max(0, intendedFilesCountEdit-1);
                // حذف بالهوية (لا بالفهرس) — الفهارس قد تنحرف لو أسقط DataTransfer ملفاً
                if(removedFile) pendingFilesEdit=pendingFilesEdit.filter(f=>f!==removedFile);
                else pendingFilesEdit.splice(idx,1);
                syncInputEdit(); renderEdit();
            });
        });
    }
    async function addFilesEdit(newFiles){
        for(let orig of newFiles){
            if(fileTransferEdit.files.length>=MAX_FILES_EDIT){ alert('الحد الأقصى '+MAX_FILES_EDIT+' ملف (حد السيرفر)'); break; }
            let file=orig; // preserved 100% original - no client compression
            const ext=file.name.split('.').pop().toLowerCase();
            const audioExts=['mp3','wav','ogg','oga','m4a','aac','wma','flac','opus','aiff','aif','amr','3ga','awb','mid','midi','au','weba'];
            const isAudio=audioExts.includes(ext)||file.type.startsWith('audio/');
            if(!['jpg','jpeg','png','webp','mp4','webm','mov','avi','3gp','mkv','m4v','mpg','3gpp'].includes(ext) && !file.type.startsWith('image/') && !file.type.startsWith('video/') && !isAudio){
                alert('نوع غير مدعوم: '+file.name); continue;
            }
            if(file.type.startsWith('image/') && file.size>20*1024*1024){ alert('حجم الصورة كبير (الحد 20MB): '+file.name); continue; }
            if(file.type.startsWith('video/') && file.size>100*1024*1024){ alert('حجم الفيديو كبير (الحد 100MB): '+file.name); continue; }
            if(isAudio && file.size>100*1024*1024){ alert('حجم الصوت كبير (الحد 100MB): '+file.name); continue; }
            fileTransferEdit.items.add(file);
            intendedFilesCountEdit++;
            pendingFilesEdit.push(file);
            if(fileTransferEdit.files.length < pendingFilesEdit.length){
                console.warn('[UPLOAD] DataTransfer dropped a file — pendingFilesEdit is source of truth', {dt: fileTransferEdit.files.length, pending: pendingFilesEdit.length, name: file.name});
            }
        }
        syncInputEdit(); renderEdit();
    }
    if(input&&zone&&list){
        input.addEventListener('change', async e=>{
            await addFilesEdit(Array.from(e.target.files));
            input.value=''; syncInputEdit();
        });
        ['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.add('border-[#0e6a38]','bg-[#eef4f0]')}));
        ['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.remove('border-[#0e6a38]','bg-[#eef4f0]')}));
        zone.addEventListener('drop',async e=>{ e.preventDefault(); if(e.dataTransfer?.files?.length) await addFilesEdit(Array.from(e.dataTransfer.files)); });
    }

    // ——— Camera Live for Edit ———
    const openBtnEdit=document.getElementById('open-camera-btn-edit');
    const cameraModalEdit=document.getElementById('camera-modal-edit');
    const cameraVideoEdit=document.getElementById('camera-video-edit');
    const cameraCanvasEdit=document.getElementById('camera-canvas-edit');
    const cameraPlaceholderEdit=document.getElementById('camera-placeholder-edit');
    const cameraErrorEdit=document.getElementById('camera-error-edit');
    const cameraTimerEdit=document.getElementById('camera-timer-edit');
    const cameraTimerTextEdit=document.getElementById('camera-timer-text-edit');
    const captureBtnEdit=document.getElementById('capture-photo-btn-edit');
    const startBtnEdit=document.getElementById('start-record-btn-edit');
    const stopBtnEdit=document.getElementById('stop-record-btn-edit');
    const switchBtnEdit=document.getElementById('switch-camera-btn-edit');
    const closeBtnEdit=document.getElementById('close-camera-btn-edit');
    const backdropEdit=document.getElementById('camera-backdrop-edit');
    const modeLabelEdit=document.getElementById('camera-mode-label-edit');
    const statusElEdit=document.getElementById('camera-status-edit');
    let currentStreamEdit=null, facingModeEdit='environment', mediaRecorderEdit=null, recordedChunksEdit=[], timerIntervalEdit=null, secondsEdit=0, isRecordingEdit=false;

    function showStatusEdit(msg, ok=true){
        if(!statusElEdit) return;
        statusElEdit.textContent=msg;
        statusElEdit.className='mt-2 text-center text-xs font-bold '+(ok?'text-[#0e6a38]':'text-red-500');
        statusElEdit.classList.remove('hidden');
        setTimeout(()=>statusElEdit.classList.add('hidden'), 3000);
    }
    async function startCameraEdit(){
        const fallbackElEdit=document.getElementById('camera-fallback-edit');
        const hostElEdit=document.getElementById('camera-host-edit');
        if(hostElEdit) hostElEdit.textContent=location.host;
        if(!window.isSecureContext){
            cameraErrorEdit.textContent='الكاميرا المباشرة تتطلب HTTPS — أنت على http://'+location.host;
            cameraErrorEdit.classList.remove('hidden');
            if(fallbackElEdit) fallbackElEdit.classList.remove('hidden');
            cameraPlaceholderEdit.classList.add('hidden');
            return;
        }
        if(!navigator.mediaDevices?.getUserMedia){
            cameraErrorEdit.textContent='الكاميرا غير مدعومة — استخدم الأزرار أدناه.';
            cameraErrorEdit.classList.remove('hidden');
            if(fallbackElEdit) fallbackElEdit.classList.remove('hidden');
            return;
        }
        try{
            if(currentStreamEdit) currentStreamEdit.getTracks().forEach(t=>t.stop());
            cameraErrorEdit.classList.add('hidden');
            cameraPlaceholderEdit.querySelector('p').textContent='جاري تشغيل الكاميرا...';
            const stream=await navigator.mediaDevices.getUserMedia({ video:{ facingMode: facingModeEdit, width:{ ideal:1280 }, height:{ ideal:720 } }, audio:true });
            currentStreamEdit=stream;
            cameraVideoEdit.srcObject=stream;
            cameraVideoEdit.classList.remove('hidden');
            cameraPlaceholderEdit.classList.add('hidden');
            await cameraVideoEdit.play();
            modeLabelEdit.textContent='— جاهزة';
        }catch(err){
            cameraErrorEdit.textContent='تعذر الوصول للكاميرا المباشرة — استخدم البديل أدناه (يعمل على كل الشبكات)';
            cameraErrorEdit.classList.remove('hidden');
            cameraPlaceholderEdit.querySelector('p').textContent='الكاميرا المباشرة غير متاحة — البديل أدناه يعمل';
        }
    }
    window.handleFallbackFileEdit = function(input){
        if(input.files && input.files[0]){
            addFilesEdit([input.files[0]]);
            showStatusEdit('تمت إضافة الملف من الكاميرا ✓', true);
            setTimeout(()=> closeCameraEdit(), 400);
            input.value='';
        }
    };
    function stopCameraEdit(){
        if(currentStreamEdit) currentStreamEdit.getTracks().forEach(t=>t.stop());
        currentStreamEdit=null;
        cameraVideoEdit.pause(); cameraVideoEdit.srcObject=null;
        cameraVideoEdit.classList.add('hidden');
        cameraPlaceholderEdit.classList.remove('hidden');
        cameraErrorEdit.classList.add('hidden');
        if(isRecordingEdit){ stopRecordingEdit(); }
    }
    function openCameraEdit(){ cameraModalEdit.classList.remove('hidden'); document.body.style.overflow='hidden'; startCameraEdit(); }
    function closeCameraEdit(){
        cameraModalEdit.classList.add('hidden'); document.body.style.overflow='';
        stopCameraEdit();
        startBtnEdit.classList.remove('hidden'); stopBtnEdit.classList.add('hidden');
        cameraTimerEdit.classList.add('hidden');
        if(timerIntervalEdit){ clearInterval(timerIntervalEdit); timerIntervalEdit=null; }
        modeLabelEdit.textContent='— صورة';
    }
    function switchCameraEdit(){ facingModeEdit=facingModeEdit==='environment'?'user':'environment'; startCameraEdit(); }
    function capturePhotoEdit(){
        if(!currentStreamEdit || !cameraVideoEdit.videoWidth){ showStatusEdit('الكاميرا غير جاهزة', false); return; }
        const canvas=cameraCanvasEdit;
        canvas.width=cameraVideoEdit.videoWidth; canvas.height=cameraVideoEdit.videoHeight;
        const ctx=canvas.getContext('2d');
        if(facingModeEdit==='user'){ ctx.scale(-1,1); ctx.drawImage(cameraVideoEdit, -canvas.width, 0, canvas.width, canvas.height); }
        else ctx.drawImage(cameraVideoEdit, 0, 0);
        canvas.toBlob(blob=>{
            if(!blob){ showStatusEdit('فشل الالتقاط', false); return; }
            const file=new File([blob], `camera-${Date.now()}.jpg`, { type:'image/jpeg' });
            addFilesEdit([file]);
            showStatusEdit('تم التقاط الصورة ✓', true);
            cameraVideoEdit.style.opacity='0.3'; setTimeout(()=>cameraVideoEdit.style.opacity='1', 150);
        }, 'image/jpeg', 1.0);
    }
    function updateTimerEdit(){
        secondsEdit++;
        const m=String(Math.floor(secondsEdit/60)).padStart(2,'0');
        const s=String(secondsEdit%60).padStart(2,'0');
        cameraTimerTextEdit.textContent=`${m}:${s}`;
    }
    function startRecordingEdit(){
        if(!currentStreamEdit){ showStatusEdit('الكاميرا غير جاهزة', false); return; }
        if(!window.MediaRecorder){ showStatusEdit('التسجيل غير مدعوم', false); return; }
        recordedChunksEdit=[];
        let options={ mimeType:'video/webm;codecs=vp9' };
        if(!MediaRecorder.isTypeSupported(options.mimeType)) options={ mimeType:'video/webm' };
        if(!MediaRecorder.isTypeSupported(options.mimeType)) options={};
        try{ mediaRecorderEdit=new MediaRecorder(currentStreamEdit, options); }catch(e){ showStatusEdit('فشل: '+e.message, false); return; }
        mediaRecorderEdit.ondataavailable=e=>{ if(e.data.size>0) recordedChunksEdit.push(e.data); };
        mediaRecorderEdit.onstop=()=>{
            const blob=new Blob(recordedChunksEdit, { type: mediaRecorderEdit.mimeType || 'video/webm' });
            const ext=blob.type.includes('mp4')?'mp4':'webm';
            const file=new File([blob], `camera-video-${Date.now()}.${ext}`, { type: blob.type });
            if(file.size>500*1024*1024){ showStatusEdit('حجم كبير', false); return; }
            addFilesEdit([file]);
            showStatusEdit(`تم التسجيل (${(file.size/1024/1024).toFixed(1)} MB) ✓`, true);
        };
        mediaRecorderEdit.start(100);
        isRecordingEdit=true; secondsEdit=0;
        cameraTimerEdit.classList.remove('hidden');
        timerIntervalEdit=setInterval(updateTimerEdit, 1000);
        startBtnEdit.classList.add('hidden'); stopBtnEdit.classList.remove('hidden');
        captureBtnEdit.disabled=true; captureBtnEdit.classList.add('opacity-50');
        modeLabelEdit.textContent='— تسجيل...';
    }
    function stopRecordingEdit(){
        if(mediaRecorderEdit && isRecordingEdit){
            mediaRecorderEdit.stop();
            isRecordingEdit=false;
            clearInterval(timerIntervalEdit);
            cameraTimerEdit.classList.add('hidden');
            startBtnEdit.classList.remove('hidden'); stopBtnEdit.classList.add('hidden');
            captureBtnEdit.disabled=false; captureBtnEdit.classList.remove('opacity-50');
            modeLabelEdit.textContent='— صورة';
        }
    }
    openBtnEdit?.addEventListener('click', openCameraEdit);
    closeBtnEdit?.addEventListener('click', closeCameraEdit);
    backdropEdit?.addEventListener('click', closeCameraEdit);
    switchBtnEdit?.addEventListener('click', switchCameraEdit);
    captureBtnEdit?.addEventListener('click', capturePhotoEdit);
    startBtnEdit?.addEventListener('click', startRecordingEdit);
    stopBtnEdit?.addEventListener('click', stopRecordingEdit);
    document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !cameraModalEdit.classList.contains('hidden')) closeCameraEdit(); });

    // ——— Preserve new files on validation error (edit) ———
    const formEdit=document.getElementById('edit-form');
    const formErrorsEdit=document.getElementById('form-errors-edit');
    formEdit?.addEventListener('submit', async (e)=>{
        try{ if(typeof sync==='function') sync(); }catch(_){}
        const floorEl=formEdit.querySelector('input[name="floor_number"]');
        const camEl=formEdit.querySelector('input[name="camera_number"]');
        const descEl=formEdit.querySelector('textarea[name="description"]');
        const hiddenEl=document.getElementById('observed_at_edit');
        let clientErrors=[];
        if(!floorEl.value) clientErrors.push('رقم الطابق مطلوب');
        if(!camEl.value) clientErrors.push('رقم الكاميرا مطلوب');
        if(!hiddenEl.value) clientErrors.push('تاريخ ووقت الملاحظة مطلوب — اختر التاريخ ووقت البداية');
        if(!descEl.value.trim()) clientErrors.push('الوصف مطلوب');
        try{
            const obs=document.getElementById('observed_at_edit')?.value;
            const obsEnd=document.getElementById('observed_end_at_edit')?.value;
            if(obs && obsEnd && new Date(obsEnd) < new Date(obs)){
                const d=new Date(obsEnd); d.setDate(d.getDate()+1);
                const pad=n=>String(n).padStart(2,'0');
                document.getElementById('observed_end_at_edit').value=d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
            }
        }catch(_){}
        if(clientErrors.length){
            e.preventDefault();
            formErrorsEdit.innerHTML='<div class="font-bold mb-1">يرجى تصحيح الحقول:</div><ul class="list-disc list-inside space-y-1">'+clientErrors.map(m=>`<li>${m}</li>`).join('')+'</ul><p class="mt-2 text-xs">الملفات الجديدة محفوظة</p>';
            formErrorsEdit.classList.remove('hidden');
            formErrorsEdit.scrollIntoView({behavior:'smooth', block:'center'});
            return;
        }
        if(fileTransferEdit.files.length>0 || recordedAudioBlobE || intendedFilesCountEdit>0){
            e.preventDefault();
            formErrorsEdit.classList.add('hidden');
            const submitBtn=e.submitter || document.activeElement;
            if(submitBtn) submitBtn.disabled=true;
            const fd=new FormData(formEdit);
            fd.delete('files[]');
            // مصدر الحقيقة: pendingFilesEdit (مصفوفة عادية لا تُسقط الفيديو) ثم fileTransfer ثم input
            let filesToSendEdit = pendingFilesEdit.length > 0 ? pendingFilesEdit.slice()
                : (fileTransferEdit.files.length > 0 ? Array.from(fileTransferEdit.files) : Array.from(input.files));
            // فحص ما قبل الإرسال: لا ترسل طلباً محكوماً بالفشل
            if(filesToSendEdit.length < intendedFilesCountEdit){
                formErrorsEdit.innerHTML='<div class="font-bold mb-1 text-red-600">تعذّر تجهيز الملفات في المتصفح</div><p class="text-xs">اخترت '+intendedFilesCountEdit+' ملف لكن المتصفح جهّز '+filesToSendEdit.length+' فقط (يحدث مع الفيديو الكبير في بعض متصفحات الجوال). أعد اختيار الملفات ثم أعد المحاولة — لم يُرسل شيء.</p>';
                formErrorsEdit.classList.remove('hidden');
                formErrorsEdit.scrollIntoView({behavior:'smooth', block:'center'});
                if(submitBtn) submitBtn.disabled=false;
                return;
            }
            filesToSendEdit.forEach(f=> fd.append('files[]', f));
            if(recordedAudioBlobE){
                const ext=audioMimeToExtE(recordedAudioBlobE.type||'audio/webm');
                fd.append('files[]', recordedAudioBlobE, 'recording-'+Date.now()+'.'+ext);
            }
            // UPLOAD INTEGRITY: إعلان النية من عدّاد مستقل لا من مخزن النقل — ROOT CAUSE FIX
            const clientFilesCountEdit = intendedFilesCountEdit + (recordedAudioBlobE ? 1 : 0);
            fd.append('client_files_count', String(clientFilesCountEdit));
            // UPLOAD FORENSIC (تشخيص فقط — لا يغيّر السلوك)
            try{
                console.debug('[UPLOAD FORENSIC] intent vs transport (edit)', {
                    intended: intendedFilesCountEdit,
                    audio: recordedAudioBlobE ? 1 : 0,
                    announced: clientFilesCountEdit,
                    fileTransfer: fileTransferEdit.files.length,
                    pending: pendingFilesEdit.length,
                    inputFiles: input.files ? input.files.length : -1,
                    toSend: filesToSendEdit.length
                });
            }catch(_){}
            try{
                const csrfTokenE = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const formEditActionUrl = formEdit.getAttribute('action');
                console.debug('[EDIT SUBMIT] action='+formEditActionUrl+' method=POST files='+filesToSendEdit.length+' count='+clientFilesCountEdit);
                setUploadProgressEdit(5);
                // Total size early check vs post_max_size (120M)
            let totalEditCheck = 0;
            try{ totalEditCheck = (typeof pendingFilesEdit !== 'undefined' && pendingFilesEdit?pendingFilesEdit.reduce((s,f)=>s+f.size,0):0); }catch(e){}
            if(totalEditCheck > 120*1024*1024){
                const errElEdit2 = document.getElementById('form-errors-edit');
                if(errElEdit2){
                    errElEdit2.innerHTML='<div class="font-bold mb-1 text-red-600">??? ??????? ???? ??? ????? ??????</div><p class="text-xs">?????? '+(totalEditCheck/1024/1024).toFixed(1)+' MB ?????? ?? ?????? 128M.</p>';
                    errElEdit2.classList.remove('hidden');
                }
                const btnE2 = document.querySelector('#edit-form button[type="submit"]');
                if(btnE2) btnE2.disabled=false;
                if(typeof hideUploadProgressEdit === 'function') hideUploadProgressEdit();
                return;
            }
            const res = await xhrUploadEdit(formEditActionUrl, fd, csrfTokenE, (pct, loaded, total)=> setUploadProgressEdit(Math.max(5, Math.min(95, pct)), 'جاري الرفع '+pct+'%'+ (loaded ? ' ('+(loaded/1024/1024).toFixed(1)+' / '+(total/1024/1024).toFixed(1)+' MB)' : '') +' — لا تغلق الصفحة'));
                setUploadProgressEdit(98, 'تم الرفع 100%، جاري التحقق من الحفظ...');
                let data=res.data, rawTextE=res.raw;
                const failLoudEdit = (title, errs) => {
                    const list=(errs&&errs.length?errs:['فشل رفع المرفقات. لم يتم حفظ التعديلات.']).map(e=> typeof e==='string'?e:((e.file?e.file+': ':'')+(e.message||JSON.stringify(e)))).join('<br>');
                    formErrorsEdit.innerHTML='<div class="font-bold mb-1 text-red-600">'+title+'</div><p class="text-xs">'+list+'</p><p class="mt-2 text-xs font-bold">الملفات الجديدة محفوظة — صحح الخطأ ثم أعد المحاولة.</p>';
                    formErrorsEdit.classList.remove('hidden');
                    formErrorsEdit.scrollIntoView({behavior:'smooth', block:'center'});
                    if(submitBtn) submitBtn.disabled=false;
                };
                if(data && data.success === false){
                    failLoudEdit('فشل رفع المرفقات. لم يتم حفظ التعديلات.', data.attachment_errors);
                    return;
                }
                if(data && typeof data.files_received==='number' && typeof data.attachments_saved==='number'){
                    // For update, attachments_saved = newly saved count in this request.
                    if(data.files_received !== data.attachments_saved && data.files_received>0){
                        // Allow case: files_received=0, attachments_saved=total? backend returns new_saved; strict check:
                        if(!(data.files_received===0 && (data.attachment_errors||[]).length===0)){
                            failLoudEdit('فشل رفع المرفقات. لم يتم حفظ التعديلات.', data.attachment_errors || ['عدد الملفات المحفوظة لا يطابق المرسلة.']);
                            return;
                        }
                    }
                    if((data.attachment_errors||[]).length>0){
                        failLoudEdit('فشل رفع المرفقات. لم يتم حفظ التعديلات.', data.attachment_errors);
                        return;
                    }
                }
                if(res.ok && data && data.success !== false){
                    if(recordedAudioUrlE) URL.revokeObjectURL(recordedAudioUrlE);
                    recordedAudioBlobE=null; recordedAudioUrlE=null;
                    window.location.href="{{ route('notes.index', [], false) }}";
                    return;
                }
                if(res.status===422){
                    if(data && (data.attachment_errors || data.success === false)){
                        failLoudEdit('فشل رفع المرفقات. لم يتم حفظ التعديلات.', data.attachment_errors);
                        return;
                    }
                    const errors=(data&&data.errors)||{};
                    let html='<div class="font-bold mb-1">يرجى تصحيح الحقول:</div><ul class="list-disc list-inside space-y-1">';
                    for(const [field,msgs] of Object.entries(errors)){
                        for(const msg of msgs) html+=`<li>${msg}</li>`;
                    }
                    html+='</ul><p class="mt-2 text-xs font-bold text-[#0e6a38]">الملفات الجديدة محفوظة — لا تحتاج لإعادة اختيارها ✓</p>';
                    formErrorsEdit.innerHTML=html;
                    formErrorsEdit.classList.remove('hidden');
                    formErrorsEdit.scrollIntoView({behavior:'smooth', block:'center'});
                } else {
                    let diagE='';
                    try{
                        const bodySnippetE=(rawTextE||'').substring(0,800).replace(/</g,'&lt;');
                        console.error('[EDIT FORENSIC]', {status:res.status, body:rawTextE});
                        diagE='<div class="mt-2 p-2 bg-white border border-red-200 rounded text-[11px] text-left dir-ltr break-all">'
                            +'<div>URL: '+formEditActionUrl+'</div>'
                            +'<div>Method: POST</div>'
                            +(bodySnippetE?'<div class="mt-1">Body: '+bodySnippetE+'</div>':'')
                            +'</div>';
                    }catch(_){}
                    formErrorsEdit.innerHTML='<div class="font-bold mb-1 text-red-600">فشل الإرسال (كود: '+res.status+')</div><p class="text-xs">لم يتم حفظ التعديلات. الملفات محفوظة — أعد المحاولة.</p>'+diagE;
                    formErrorsEdit.classList.remove('hidden');
                    formErrorsEdit.scrollIntoView({behavior:'smooth', block:'center'});
                }
            }catch(err){
                hideUploadProgressEdit();
                if(err.name === 'AbortError'){
                    formErrorsEdit.innerHTML='<div class="font-bold mb-1 text-red-600">انتهت مهلة الإرسال (10 دقائق)</div><p class="text-xs">تحقق من اتصالك أو قلل حجم المرفقات.</p>';
                } else {
                    formErrorsEdit.innerHTML='<div class="font-bold mb-1 text-red-600">خطأ في الشبكة</div><p class="text-xs">'+err.message+'</p>';
                }
                formErrorsEdit.classList.remove('hidden');
                formErrorsEdit.scrollIntoView({behavior:'smooth', block:'center'});
            }finally{
                hideUploadProgressEdit();
                if(submitBtn) submitBtn.disabled=false;
            }
        }
    });
    window.openAttachmentView = function(url, mime, name){
        const modal = document.getElementById("attachment-view-modal");
        if(!modal){ window.open(url, "_blank"); return; }
        const img = document.getElementById("attachment-view-image");
        const video = document.getElementById("attachment-view-video");
        const audio = document.getElementById("attachment-view-audio");
        const fallback = document.getElementById("attachment-view-fallback");
        const title = document.getElementById("attachment-view-title");
        if(title) title.textContent = name;
        img.classList.add("hidden"); img.src="";
        video.classList.add("hidden"); video.pause(); video.src=""; video.load();
        audio.classList.add("hidden"); audio.pause(); audio.src=""; audio.load();
        fallback.classList.add("hidden");
        if(mime.startsWith("image/")){ img.src=url; img.classList.remove("hidden"); }
        else if(mime.startsWith("video/")){ video.src=url; video.classList.remove("hidden"); video.load(); }
        else if(mime.startsWith("audio/")){ audio.src=url; audio.classList.remove("hidden"); audio.load(); }
        else { fallback.classList.remove("hidden"); }
        modal.classList.remove("hidden");
        document.body.style.overflow="hidden";
    };

    // ——— Audio Recorder (Edit) ———
    const audioRecordBtnEdit=document.getElementById('audio-record-btn-edit');
    const audioPreviewEdit=document.getElementById('audio-preview-edit');
    const audioPreviewIconEdit=document.getElementById('audio-preview-icon-edit');
    const audioPreviewStatusEdit=document.getElementById('audio-preview-status-edit');
    const audioPreviewTimerEdit=document.getElementById('audio-preview-timer-edit');
    const audioPreviewPlayerEdit=document.getElementById('audio-preview-player-edit');
    const audioPreviewDeleteEdit=document.getElementById('audio-preview-delete-edit');
    const audioPreviewPendingEdit=document.getElementById('audio-preview-pending-edit');
    const hasGetUserMediaE=!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia);
    let audioRecorderE=null;
    let audioChunksE=[];
    let audioStreamE=null;
    let audioTimerE=null;
    let audioStartTimeE=0;
    let recordedAudioBlobE=null;
    let recordedAudioUrlE=null;

    function showAudioPreviewEdit(msg, showTimer){
        if(!audioPreviewEdit) return;
        audioPreviewEdit.classList.remove('hidden');
        if(audioPreviewStatusEdit) audioPreviewStatusEdit.textContent=msg;
        if(audioPreviewTimerEdit){ audioPreviewTimerEdit.classList.add('hidden'); }
        if(audioPreviewPlayerEdit){ audioPreviewPlayerEdit.classList.add('hidden'); audioPreviewPlayerEdit.src=''; }
        if(audioPreviewDeleteEdit) audioPreviewDeleteEdit.classList.add('hidden');
        if(audioPreviewPendingEdit) audioPreviewPendingEdit.classList.add('hidden');
        if(audioPreviewIconEdit) audioPreviewIconEdit.innerHTML='<svg class="w-5 h-5 text-red-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>';
    }
    function showAudioRecordedEdit(){
        if(audioPreviewIconEdit) audioPreviewIconEdit.innerHTML='<svg class="w-5 h-5 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>';
        if(audioPreviewStatusEdit) audioPreviewStatusEdit.textContent='تم التسجيل';
        if(audioPreviewTimerEdit) audioPreviewTimerEdit.classList.remove('hidden');
        if(audioPreviewPlayerEdit){ audioPreviewPlayerEdit.classList.remove('hidden'); audioPreviewPlayerEdit.src=recordedAudioUrlE; }
        if(audioPreviewDeleteEdit) audioPreviewDeleteEdit.classList.remove('hidden');
        if(audioPreviewPendingEdit) audioPreviewPendingEdit.classList.remove('hidden');
    }
    function hideAudioPreviewEdit(){
        if(audioPreviewEdit) audioPreviewEdit.classList.add('hidden');
    }
    function cleanupAudioRecordingEdit(){
        if(audioStreamE){ audioStreamE.getTracks().forEach(t=>t.stop()); audioStreamE=null; }
        if(audioRecorderE){ audioRecorderE=null; }
        if(audioTimerE){ clearInterval(audioTimerE); audioTimerE=null; }
        audioChunksE=[];
        if(recordedAudioUrlE){ URL.revokeObjectURL(recordedAudioUrlE); recordedAudioUrlE=null; }
        recordedAudioBlobE=null;
        audioRecordBtnEdit.classList.remove('bg-red-50','border-red-300','text-red-600');
        audioRecordBtnEdit.classList.add('bg-white','border-[#e6e9e1]','text-ink-700');
        audioRecordBtnEdit.innerHTML='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>تسجيل صوتي';
        hideAudioPreviewEdit();
    }
    function getSupportedAudioMimeE(){
        const types=['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus','audio/ogg','audio/mp4'];
        for(const t of types){ if(window.MediaRecorder&&MediaRecorder.isTypeSupported(t)) return t; }
        return '';
    }
    function audioMimeToExtE(mime){
        const m=mime.split(';')[0].trim();
        if(m==='audio/webm') return 'webm';
        if(m==='audio/ogg') return 'ogg';
        if(m==='audio/mp4') return 'm4a';
        if(m==='audio/mpeg') return 'mp3';
        return 'webm';
    }
    if(audioPreviewDeleteEdit){
        audioPreviewDeleteEdit.addEventListener('click',()=>{
            cleanupAudioRecordingEdit();
        });
    }
    if(audioRecordBtnEdit){
        if(!hasGetUserMediaE){
            audioRecordBtnEdit.addEventListener('click',()=>{
                const camModal=document.getElementById('camera-modal-edit');
                if(camModal) camModal.classList.remove('hidden');
            });
        } else {
            audioRecordBtnEdit.addEventListener('click', async()=>{
                if(audioRecorderE && audioRecorderE.state==='recording'){
                    audioRecorderE.stop();
                    return;
                }
                if(recordedAudioBlobE){
                    cleanupAudioRecordingEdit();
                    return;
                }
                try{
                    showAudioPreviewEdit('جاري طلب الميكروفون...', false);
                    audioStreamE=await navigator.mediaDevices.getUserMedia({audio:true});
                    audioChunksE=[];
                    const mime=getSupportedAudioMimeE();
                    const opts=mime?{mimeType:mime}:{};
                    audioRecorderE=new MediaRecorder(audioStreamE, opts);
                    audioRecorderE.ondataavailable=e=>{if(e.data.size>0) audioChunksE.push(e.data);};
                    audioRecorderE.onstop=()=>{
                        const blob=new Blob(audioChunksE,{type:audioRecorderE.mimeType||'audio/webm'});
                        recordedAudioBlobE=blob;
                        recordedAudioUrlE=URL.createObjectURL(blob);
                        audioStreamE.getTracks().forEach(t=>t.stop());
                        audioStreamE=null;
                        showAudioRecordedEdit();
                        audioRecordBtnEdit.classList.remove('bg-white','border-[#e6e9e1]','text-ink-700');
                        audioRecordBtnEdit.classList.add('bg-red-50','border-red-300','text-red-600');
                        audioRecordBtnEdit.innerHTML='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>حذف التسجيل';
                    };
                    audioRecorderE.start();
                    audioStartTimeE=Date.now();
                    audioRecordBtnEdit.classList.remove('bg-white','border-[#e6e9e1]','text-ink-700');
                    audioRecordBtnEdit.classList.add('bg-red-50','border-red-300','text-red-600');
                    audioRecordBtnEdit.innerHTML='<span class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></span><span id="audio-timer-edit">00:00</span> — إيقاف';
                    if(audioPreviewStatusEdit) audioPreviewStatusEdit.textContent='جاري التسجيل...';
                    if(audioPreviewTimerEdit) audioPreviewTimerEdit.classList.remove('hidden');
                    audioTimerE=setInterval(()=>{
                        const el=document.getElementById('audio-timer-edit');
                        const tel=document.getElementById('audio-preview-timer-edit');
                        const s=Math.floor((Date.now()-audioStartTimeE)/1000);
                        const t=String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
                        if(el) el.textContent=t;
                        if(tel) tel.textContent=t;
                    },500);
                }catch(err){
                    hideAudioPreviewEdit();
                    const camModal=document.getElementById('camera-modal-edit');
                    if(camModal) camModal.classList.remove('hidden');
                }
            });
        }
    }
</script>
@endpush

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
            <img id="attachment-view-image" class="hidden max-w-full max-h-[70vh] rounded-lg object-contain" oncontextmenu="return false;" draggable="false" alt="معاينة">
            <video id="attachment-view-video" class="hidden max-w-full max-h-[70vh] rounded-lg" controls controlsList="nodownload" oncontextmenu="return false;" disablePictureInPicture></video>
            <audio id="attachment-view-audio" class="hidden w-full max-w-md" controls controlsList="nodownload" oncontextmenu="return false;"></audio>
            <div id="attachment-view-fallback" class="hidden text-center text-white/70 text-sm">لا يمكن معاينة هذا النوع</div>
        </div>
        <div class="px-4 py-3 border-t border-surface-300 bg-surface-50 flex items-center justify-between gap-3 shrink-0">
            <p class="text-xs text-ink-400">العرض فقط — التنزيل لكاتب التقرير فقط</p>
            <button type="button" onclick="closeModal('attachment-view-modal')" class="px-4 py-2 rounded-lg bg-white border border-surface-300 text-ink-600 text-sm font-bold hover:bg-surface-100 transition">إغلاق</button>
        </div>
    </div>
</div>
@endsection
