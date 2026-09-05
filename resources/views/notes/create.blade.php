@extends('layouts.app')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('notes.index') }}" class="w-9 h-9 rounded-lg bg-white border border-[#e6e9e1] flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-[#f5f7f5] transition" aria-label="العودة">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-extrabold text-ink-800 leading-none">ملاحظة جديدة</h1>
            <p class="text-sm text-ink-400 mt-1 hidden sm:block">
                @if(auth()->user()->isReportWriter())
                    دوّن ملاحظة — تُقبل فوراً وتظهر في جميع الملاحظات
                @else
                    وثّق ملاحظة ميدانية — تُحفظ كمسودة حتى تُرسل
                @endif
            </p>
        </div>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-xs font-bold text-ink-400 bg-white border border-[#e6e9e1] rounded-full px-3 py-1.5">
        @if(auth()->user()->isReportWriter())
            <span class="w-1.5 h-1.5 rounded-full bg-sage-600"></span>تُقبل فوراً
        @else
            <span class="w-1.5 h-1.5 rounded-full bg-ink-300"></span>مسودة
        @endif
    </div>
</div>

<div class="max-w-4xl mx-auto">
    <div class="bg-[#fdfcfa] rounded-2xl border border-[#e6e9e1] overflow-hidden">
        <form method="POST" action="{{ route('notes.store') }}" enctype="multipart/form-data" class="p-6 space-y-5" id="create-form" novalidate>
            @csrf
            <div id="form-errors" class="hidden p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="floor_number" class="block text-sm font-bold text-ink-700 mb-1.5">رقم الطابق <span class="text-red-500">*</span></label>
                    <input type="number" id="floor_number" name="floor_number" value="{{ old('floor_number') }}" min="1" required inputmode="numeric"
                        class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-3 px-4 text-sm font-medium text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition @error('floor_number') border-red-400 @enderror"
                        placeholder="مثال: 3">
                    @error('floor_number') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="camera_number" class="block text-sm font-bold text-ink-700 mb-1.5">رقم الكاميرا <span class="text-red-500">*</span></label>
                    <input type="number" id="camera_number" name="camera_number" value="{{ old('camera_number') }}" min="1" required inputmode="numeric"
                        class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-3 px-4 text-sm font-medium text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition @error('camera_number') border-red-400 @enderror"
                        placeholder="مثال: 12">
                    @error('camera_number') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-xl border border-[#e6e9e1] bg-[#f5f7f5] p-4">
                <label class="block text-sm font-bold text-ink-700 mb-1">الرصد <span class="text-red-500">*</span></label>
                <p class="text-xs text-ink-400 mb-3">التاريخ والوقت الفعليين للرصد — وقت البداية ووقت الانتهاء</p>
                @php
                    $oldObserved = old('observed_at');
                    $oldDate = $oldObserved ? date('Y-m-d', strtotime($oldObserved)) : '';
                    $oldTime = $oldObserved ? date('H:i', strtotime($oldObserved)) : '';
                    $oldObservedEnd = old('observed_end_at');
                    $oldEndTime = $oldObservedEnd ? date('H:i', strtotime($oldObservedEnd)) : '';
                @endphp
                <div class="space-y-3">
                    <div>
                        <div class="text-xs font-bold text-ink-500 mb-1.5">التاريخ</div>
                        <input type="date" id="observed_date" value="{{ $oldDate }}" required
                            class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-2.5 px-4 text-sm font-bold text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                            style="color-scheme: light;">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-xs font-bold text-ink-500 mb-1.5">بداية الرصد</div>
                            <input type="time" id="observed_time" value="{{ $oldTime }}" required step="60"
                                class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-2.5 px-4 text-sm font-bold text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                                style="color-scheme: light;">
                        </div>
                        <div>
                            <div class="text-xs font-bold text-ink-500 mb-1.5">انتهاء الرصد</div>
                            <input type="time" id="observed_end_time" value="{{ $oldEndTime }}" step="60"
                                class="block w-full rounded-xl border border-[#e6e9e1] bg-white py-2.5 px-4 text-sm font-bold text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                                style="color-scheme: light;">
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <button type="button" data-preset="now" class="preset-btn px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition">الآن</button>
                    <button type="button" data-preset="hour-ago" class="preset-btn px-3 py-1.5 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#1a2e1f] text-xs font-bold hover:bg-[#f5f7f5] transition">قبل ساعة</button>
                    <button type="button" data-preset="today-08" class="preset-btn px-3 py-1.5 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#1a2e1f] text-xs font-bold hover:bg-[#f5f7f5] transition">اليوم 08:00</button>
                    <button type="button" id="clear-datetime" class="px-3 py-1.5 rounded-lg bg-transparent border border-[#e6e9e1] text-ink-400 text-xs font-bold hover:bg-white transition">مسح</button>
                </div>
                <div class="mt-3 flex items-center gap-2 p-2.5 rounded-lg bg-white border border-[#e6e9e1]">
                    <svg class="w-4 h-4 text-[#0e6a38] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div id="observed_preview" class="text-sm font-bold text-ink-700 truncate">— اختر التاريخ والوقت —</div>
                </div>
                <input type="hidden" name="observed_at" id="observed_at" value="{{ old('observed_at') }}">
                <input type="hidden" name="observed_end_at" id="observed_end_at" value="{{ old('observed_end_at') }}">
                @error('observed_at') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                @error('observed_end_at') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-bold text-ink-700 mb-1.5">الوصف <span class="text-red-500">*</span></label>
                <div class="relative">
                    <textarea id="description" name="description" rows="5" required maxlength="5000"
                        class="block w-full rounded-xl border border-[#e6e9e1] bg-white p-4 text-sm leading-7 text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition resize-none @error('description') border-red-400 @enderror"
                        placeholder="صف ما تم رصده بدقة...">{{ old('description') }}</textarea>
                    <div class="absolute bottom-3 left-3 text-[11px] font-bold text-ink-400 bg-white border border-[#e6e9e1] rounded-full px-2 py-0.5">
                        <span id="desc-count">0</span> / 5000
                    </div>
                </div>
                @error('description') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-ink-700 mb-1.5">المرفقات <span class="text-ink-300 font-medium text-xs">— اختياري</span></label>
                <div class="rounded-xl border-2 border-dashed border-[#e6e9e1] bg-[#f5f7f5] hover:border-[#0e6a38] hover:bg-[#f5f7f5] transition p-5 text-center group" id="drop-zone">
                    <div class="mx-auto w-10 h-10 rounded-xl bg-white border border-[#e6e9e1] flex items-center justify-center group-hover:border-[#0e6a38] transition">
                        <svg class="w-5 h-5 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    </div>
                    <div class="mt-3 flex flex-col sm:flex-row items-center justify-center gap-2">
                        <label for="files" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#0e6a38] text-white font-bold text-sm cursor-pointer hover:bg-[#0a4d28] transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            اختيار ملفات
                        </label>
                        <span class="hidden sm:inline text-sm text-ink-400">أو اسحب وأفلت</span>
                        <span class="sm:hidden text-xs text-ink-400">أو</span>
                        <button type="button" id="open-camera-btn" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#1a2e1f] font-bold text-sm hover:bg-[#f5f7f5] transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 13a3 3 0 100-6 3 3 0 000 6z"/></svg>
                            الكاميرا المباشرة
                        </button>
                        <button type="button" id="audio-record-btn" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#1a2e1f] font-bold text-sm hover:bg-[#f5f7f5] transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                            تسجيل صوتي
                        </button>
                    </div>
                    <div id="audio-preview" class="hidden mt-3 p-3 bg-white border border-[#e6e9e1] rounded-xl">
                        <div class="flex items-center gap-3">
                            <div id="audio-preview-icon" class="w-10 h-10 rounded-lg bg-red-50 border border-red-200 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-red-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div id="audio-preview-status" class="text-sm font-bold text-ink-700">جاري طلب الميكروفون...</div>
                                <div id="audio-preview-timer" class="text-xs text-ink-400 mt-0.5 hidden">00:00</div>
                            </div>
                            <button type="button" id="audio-preview-delete" class="hidden shrink-0 w-8 h-8 rounded-lg hover:bg-red-50 flex items-center justify-center text-ink-400 hover:text-red-500 transition" title="حذف التسجيل">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <audio id="audio-preview-player" class="hidden w-full mt-2 rounded-lg" controls></audio>
                        <div id="audio-preview-pending" class="hidden mt-2 text-[11px] text-[#0e6a38] font-bold">سيتم إرفاقه عند حفظ الملاحظة</div>
                    </div>
                    <p class="mt-1 text-[11px] text-ink-400">على الجوال: تصوير/فيديو مباشر دون حفظ في الجهاز</p>
                    <input type="file" id="files" name="files[]" multiple accept="image/*,video/*,audio/*" class="hidden">
                    <div id="file-list" class="mt-3 hidden text-right space-y-1.5"></div>
                </div>

                {{-- Camera Live Modal --}}
                <div id="camera-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" id="camera-backdrop"></div>
                    <div class="relative bg-white rounded-2xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                        <div class="px-4 py-3 border-b border-[#e6e9e1] flex items-center justify-between shrink-0">
                            <h3 class="text-sm font-extrabold text-ink-800 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                الكاميرا المباشرة
                                <span id="camera-mode-label" class="text-xs font-medium text-ink-400 mr-1">— صورة</span>
                            </h3>
                            <div class="flex items-center gap-1.5">
                                <button type="button" id="switch-camera-btn" class="w-8 h-8 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center text-ink-500 hover:text-ink-700 transition" title="تبديل الكاميرا">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/></svg>
                                </button>
                                <button type="button" id="close-camera-btn" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-400 hover:text-ink-700 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 min-h-0 bg-black flex flex-col items-center justify-center p-3 sm:p-4 relative overflow-hidden">
                            <video id="camera-video" autoplay playsinline muted class="w-full h-auto max-h-[50vh] rounded-xl bg-black object-contain hidden"></video>
                            <canvas id="camera-canvas" class="hidden"></canvas>
                            <div id="camera-placeholder" class="text-center py-12">
                                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </div>
                                <p class="text-sm text-white/70">جاري تشغيل الكاميرا...</p>
                                <p id="camera-error" class="hidden mt-2 text-xs text-red-300 max-w-xs mx-auto leading-5"></p>
                            </div>
                            <div id="camera-fallback" class="w-full max-w-md p-4 border-t border-amber-200 bg-amber-50/50">
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/><circle cx="12" cy="13" r="3"/></svg>
                                        <span class="text-sm font-bold text-ink-800">التقاط صورة</span>
                                        <input type="file" accept="image/*" capture="environment" class="hidden" onchange="handleFallbackFile(this)">
                                    </label>
                                    <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <span class="text-sm font-bold text-ink-800">تسجيل فيديو</span>
                                        <input type="file" accept="video/*" capture="environment" class="hidden" onchange="handleFallbackFile(this)">
                                    </label>
                                    <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                        <span class="text-sm font-bold text-ink-800">تسجيل صوتي</span>
                                        <input type="file" accept="audio/*" capture="user" class="hidden" onchange="handleFallbackFile(this)">
                                    </label>
                                </div>
                            </div>
                            <div id="camera-timer" class="hidden absolute top-4 right-4 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                                <span id="camera-timer-text">00:00</span>
                            </div>
                        </div>
                        <div class="p-4 border-t border-[#e6e9e1] bg-white shrink-0">
                            <div class="flex gap-2 justify-center flex-wrap">
                                <button type="button" id="capture-photo-btn" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h2l2-2h4l2 2h2a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/><circle cx="12" cy="13" r="3"/></svg>
                                    التقاط صورة
                                </button>
                                <button type="button" id="start-record-btn" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-white border-2 border-red-200 text-red-600 font-bold text-sm hover:bg-red-50 transition">
                                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                    بدء تسجيل فيديو
                                </button>
                                <button type="button" id="stop-record-btn" class="hidden flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-sm transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="6" y="6" width="12" height="12" rx="1"/></svg>
                                    إيقاف وحفظ
                                </button>
                            </div>
                            <p class="mt-2.5 text-center text-[11px] text-ink-400 leading-4">الصور والفيديو تُضاف مباشرة للمرفقات دون حفظ في معرض الجهاز</p>
                            <p id="camera-status" class="hidden mt-2 text-center text-xs font-bold"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-5 border-t border-[#e6e9e1]">
                <a href="{{ route('notes.index') }}" class="px-5 py-2.5 rounded-xl bg-transparent border border-[#e6e9e1] text-[#525252] font-bold text-sm hover:bg-[#f5f7f5] transition">إلغاء</a>
                <div class="flex-1 flex flex-col sm:flex-row gap-2 sm:justify-end">
                    @if(auth()->user()->isReportWriter())
                        <button type="submit" name="action" value="save" class="px-6 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition">حفظ الملاحظة</button>
                    @else
                        <button type="submit" name="action" value="save" class="px-5 py-2.5 rounded-xl bg-[#fdfcfa] border border-[#e6e9e1] text-[#1a2e1f] font-bold text-sm hover:bg-[#f5f7f5] transition">حفظ كمسودة</button>
                        <button type="submit" name="action" value="send" class="px-6 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition">حفظ وإرسال للمراجعة</button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function(){
        const dateEl=document.getElementById('observed_date');
        const timeEl=document.getElementById('observed_time');
        const endTimeEl=document.getElementById('observed_end_time');
        const hidden=document.getElementById('observed_at');
        const hiddenEnd=document.getElementById('observed_end_at');
        const preview=document.getElementById('observed_preview');
        function pad(n){return String(n).padStart(2,'0');}
        function toPreview(date,time){
            if(!date||!time) return null;
            const d=new Date(date+'T'+time);
            if(isNaN(d)) return null;
            const opts={weekday:'long', year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit'};
            try{ return d.toLocaleDateString('ar-EG',opts);}catch(e){ return date+' '+time; }
        }
        function sync(){
            const d=dateEl?.value, t=timeEl?.value, et=endTimeEl?.value;
            if(d && t){
                hidden.value = d+'T'+t;
                const txt=toPreview(d,t);
                const endTxt=et ? ' — '+et : '';
                if(preview) preview.textContent = (txt || (d+' — '+t)) + endTxt;
                hidden.setCustomValidity('');
            } else {
                hidden.value='';
                if(preview){ preview.textContent='— اختر التاريخ والوقت —'; }
            }
            if(d && et){ hiddenEnd.value = d+'T'+et; }
            else { hiddenEnd.value=''; }
        }
        dateEl?.addEventListener('change',sync); timeEl?.addEventListener('change',sync); endTimeEl?.addEventListener('change',sync);
        dateEl?.addEventListener('input',sync); timeEl?.addEventListener('input',sync); endTimeEl?.addEventListener('input',sync);
        document.querySelectorAll('.preset-btn').forEach(btn=>{
            btn.addEventListener('click',()=>{
                const now=new Date(); let d=new Date(now); const p=btn.dataset.preset;
                if(p==='hour-ago') d=new Date(now.getTime()-60*60*1000);
                else if(p==='today-08'){ d=new Date(now); d.setHours(8,0,0,0); }
                if(dateEl) dateEl.value=`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
                if(timeEl) timeEl.value=`${pad(d.getHours())}:${pad(d.getMinutes())}`;
                sync();
            });
        });
        document.getElementById('clear-datetime')?.addEventListener('click',()=>{ if(dateEl) dateEl.value=''; if(timeEl) timeEl.value=''; if(endTimeEl) endTimeEl.value=''; sync(); });
        sync();
        document.getElementById('create-form')?.addEventListener('submit',e=>{
            sync();
            if(!hidden.value){ e.preventDefault(); hidden.setCustomValidity('يرجى اختيار التاريخ والوقت'); hidden.reportValidity(); }
        });
    })();
    const desc=document.getElementById('description'),cnt=document.getElementById('desc-count');
    if(desc&&cnt){const u=()=>cnt.textContent=desc.value.length;desc.addEventListener('input',u);u();}

    // ——— File handling with DataTransfer + Camera Live ———
    const input=document.getElementById('files'),zone=document.getElementById('drop-zone'),list=document.getElementById('file-list');
    let fileTransfer = new DataTransfer();

    function syncInput(){ input.files = fileTransfer.files; }
    function renderFiles(){
        const files=Array.from(fileTransfer.files);
        if(!files.length){ list.classList.add('hidden'); list.innerHTML=''; return; }
        list.classList.remove('hidden');
        list.innerHTML=files.map((f,i)=>{
            const isV=f.type.startsWith('video/');
            const isA=f.type.startsWith('audio/');
            const sz=(f.size/1024/1024).toFixed(2)+' MB';
            const camBadge = f.name.startsWith('camera-') ? '<span class="text-[10px] bg-[#eef4f0] text-[#0e6a38] px-1.5 py-0.5 rounded-full font-bold">كاميرا</span>' : '';
            const recBadge = f.name.startsWith('recording-') ? '<span class="text-[10px] bg-red-50 text-red-600 px-1.5 py-0.5 rounded-full font-bold">تسجيل</span>' : '';
            return `<div class="flex items-center gap-2.5 p-2.5 bg-white border border-[#e6e9e1] rounded-lg text-sm group">
                <span class="text-sm">${isA?'🎤':isV?'🎬':'🖼️'}</span>
                <div class="flex-1 min-w-0 text-right">
                    <div class="font-bold text-ink-700 truncate flex items-center gap-1.5">${f.name} ${camBadge} ${recBadge}</div>
                    <div class="text-xs text-ink-400">${sz} • ${f.type||'—'}</div>
                </div>
                <button type="button" data-remove="${i}" class="shrink-0 w-7 h-7 rounded-lg hover:bg-red-50 text-ink-300 hover:text-red-500 flex items-center justify-center transition" title="حذف">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>`;
        }).join('') + (files.length>50?'<p class="text-xs font-bold text-red-500">الحد الأقصى 50 ملف — احذف بعضها</p>':'');
        // bind remove
        list.querySelectorAll('[data-remove]').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                const idx=parseInt(btn.dataset.remove);
                const dt=new DataTransfer();
                Array.from(fileTransfer.files).forEach((file,j)=>{ if(j!==idx) dt.items.add(file); });
                fileTransfer=dt; syncInput(); renderFiles();
            });
        });
    }
    function addFiles(newFiles){
        for(const file of newFiles){
            if(fileTransfer.files.length>=50){ alert('الحد الأقصى 50 ملف'); break; }
            const ext=file.name.split('.').pop().toLowerCase();
            const audioExts=['mp3','wav','ogg','oga','m4a','aac','wma','flac','opus','aiff','aif','amr','3ga','awb','mid','midi','au','weba'];
            const isAudio=audioExts.includes(ext)||file.type.startsWith('audio/');
            if(!['jpg','jpeg','png','webp','mp4','webm','mov','avi','3gp','mkv','m4v','mpg','3gpp'].includes(ext) && !file.type.startsWith('image/') && !file.type.startsWith('video/') && !isAudio){
                alert('نوع غير مدعوم: '+file.name); continue;
            }
            if(file.type.startsWith('image/') && file.size>5*1024*1024){ alert('حجم الصورة كبير (الحد 5MB): '+file.name); continue; }
            if(file.type.startsWith('video/') && file.size>30*1024*1024){ alert('حجم الفيديو كبير (الحد 30MB): '+file.name); continue; }
            if(isAudio && file.size>100*1024*1024){ alert('حجم الصوت كبير (الحد 100MB): '+file.name); continue; }
            fileTransfer.items.add(file);
        }
        syncInput(); renderFiles();
    }
    if(input&&zone&&list){
        input.addEventListener('change', e=>{
            addFiles(Array.from(e.target.files));
            // reset input value so same file can be selected again
            input.value='';
            // need to keep fileTransfer, so re-sync
            syncInput();
        });
        ['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.add('border-[#0e6a38]','bg-[#eef4f0]');}));
        ['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.remove('border-[#0e6a38]','bg-[#eef4f0]');}));
        zone.addEventListener('drop',e=>{
            e.preventDefault();
            if(e.dataTransfer?.files?.length) addFiles(Array.from(e.dataTransfer.files));
        });
    }

    // ——— Camera Live (photo + video) ———
    const openBtn=document.getElementById('open-camera-btn');
    const cameraModal=document.getElementById('camera-modal');
    const cameraVideo=document.getElementById('camera-video');
    const cameraCanvas=document.getElementById('camera-canvas');
    const cameraPlaceholder=document.getElementById('camera-placeholder');
    const cameraError=document.getElementById('camera-error');
    const cameraTimer=document.getElementById('camera-timer');
    const cameraTimerText=document.getElementById('camera-timer-text');
    const captureBtn=document.getElementById('capture-photo-btn');
    const startBtn=document.getElementById('start-record-btn');
    const stopBtn=document.getElementById('stop-record-btn');
    const switchBtn=document.getElementById('switch-camera-btn');
    const closeBtn=document.getElementById('close-camera-btn');
    const backdrop=document.getElementById('camera-backdrop');
    const modeLabel=document.getElementById('camera-mode-label');
    const statusEl=document.getElementById('camera-status');
    let currentStream=null, facingMode='environment', mediaRecorder=null, recordedChunks=[], timerInterval=null, seconds=0, isRecording=false;

    function showStatus(msg, ok=true){
        if(!statusEl) return;
        statusEl.textContent=msg;
        statusEl.className='mt-2 text-center text-xs font-bold '+(ok?'text-[#0e6a38]':'text-red-500');
        statusEl.classList.remove('hidden');
        setTimeout(()=>statusEl.classList.add('hidden'), 3000);
    }
    async function startCamera(){
        const fallbackEl = document.getElementById('camera-fallback');
        const hostEl = document.getElementById('camera-host');
        if(hostEl) hostEl.textContent = location.host;
        if(!window.isSecureContext){
            cameraError.textContent='الكاميرا المباشرة تتطلب اتصال آمن (HTTPS) — أنت على http://' + location.host;
            cameraError.classList.remove('hidden');
            if(fallbackEl) fallbackEl.classList.remove('hidden');
            cameraPlaceholder.classList.add('hidden');
            return;
        }
        if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
            cameraError.textContent='الكاميرا غير مدعومة في هذا المتصفح — استخدم الأزرار أدناه.';
            cameraError.classList.remove('hidden');
            if(fallbackEl) fallbackEl.classList.remove('hidden');
            return;
        }
        try{
            if(currentStream) currentStream.getTracks().forEach(t=>t.stop());
            cameraError.classList.add('hidden');
            cameraPlaceholder.querySelector('p').textContent='جاري تشغيل الكاميرا...';
            const stream=await navigator.mediaDevices.getUserMedia({
                video:{ facingMode: facingMode, width:{ ideal: 1280 }, height:{ ideal: 720 } },
                audio:true
            });
            currentStream=stream;
            cameraVideo.srcObject=stream;
            cameraVideo.classList.remove('hidden');
            cameraPlaceholder.classList.add('hidden');
            await cameraVideo.play();
            modeLabel.textContent='— جاهزة';
        }catch(err){
            console.error(err);
            cameraError.textContent='تعذر الوصول للكاميرا المباشرة — استخدم البديل أدناه (يعمل على كل الشبكات)';
            cameraError.classList.remove('hidden');
            cameraPlaceholder.querySelector('p').textContent='الكاميرا المباشرة غير متاحة — البديل أدناه يعمل';
        }
    }
    window.handleFallbackFile = function(input){
        if(input.files && input.files[0]){
            addFiles([input.files[0]]);
            showStatus('تمت إضافة الملف من الكاميرا ✓', true);
            setTimeout(()=> closeCamera(), 400);
            input.value='';
        }
    };
    function stopCamera(){
        if(currentStream) currentStream.getTracks().forEach(t=>t.stop());
        currentStream=null;
        cameraVideo.pause();
        cameraVideo.srcObject=null;
        cameraVideo.classList.add('hidden');
        cameraPlaceholder.classList.remove('hidden');
        cameraPlaceholder.querySelector('p').textContent='جاري تشغيل الكاميرا...';
        cameraError.classList.add('hidden');
        if(isRecording) stopRecording();
    }
    function openCamera(){
        cameraModal.classList.remove('hidden');
        document.body.style.overflow='hidden';
        startCamera();
    }
    function closeCamera(){
        cameraModal.classList.add('hidden');
        document.body.style.overflow='';
        stopCamera();
        // reset UI
        startBtn.classList.remove('hidden');
        stopBtn.classList.add('hidden');
        cameraTimer.classList.add('hidden');
        if(timerInterval){ clearInterval(timerInterval); timerInterval=null; }
        modeLabel.textContent='— صورة';
    }
    function switchCamera(){
        facingMode = facingMode==='environment' ? 'user' : 'environment';
        startCamera();
    }
    function capturePhoto(){
        if(!currentStream || !cameraVideo.videoWidth){
            showStatus('الكاميرا غير جاهزة', false); return;
        }
        const canvas=cameraCanvas;
        canvas.width=cameraVideo.videoWidth;
        canvas.height=cameraVideo.videoHeight;
        const ctx=canvas.getContext('2d');
        // mirror if front camera
        if(facingMode==='user'){ ctx.scale(-1,1); ctx.drawImage(cameraVideo, -canvas.width, 0, canvas.width, canvas.height); }
        else ctx.drawImage(cameraVideo, 0, 0);
        canvas.toBlob(blob=>{
            if(!blob){ showStatus('فشل الالتقاط', false); return; }
            const file=new File([blob], `camera-${Date.now()}.jpg`, { type:'image/jpeg' });
            if(fileTransfer.files.length>=50){ showStatus('الحد 50 ملف', false); return; }
            addFiles([file]);
            showStatus('تم التقاط الصورة وإضافتها ✓', true);
            // shutter flash effect
            cameraVideo.style.opacity='0.3';
            setTimeout(()=>cameraVideo.style.opacity='1', 150);
        }, 'image/jpeg', 1.0);
    }
    function updateTimer(){
        seconds++;
        const m=String(Math.floor(seconds/60)).padStart(2,'0');
        const s=String(seconds%60).padStart(2,'0');
        cameraTimerText.textContent=`${m}:${s}`;
    }
    function startRecording(){
        if(!currentStream){ showStatus('الكاميرا غير جاهزة', false); return; }
        if(!window.MediaRecorder){ showStatus('التسجيل غير مدعوم — استخدم التقاط صور', false); return; }
        recordedChunks=[];
        let options={ mimeType:'video/webm;codecs=vp9' };
        if(!MediaRecorder.isTypeSupported(options.mimeType)) options={ mimeType:'video/webm' };
        if(!MediaRecorder.isTypeSupported(options.mimeType)) options={};
        try{
            mediaRecorder=new MediaRecorder(currentStream, options);
        }catch(e){ showStatus('فشل بدء التسجيل: '+e.message, false); return; }
        mediaRecorder.ondataavailable=e=>{ if(e.data.size>0) recordedChunks.push(e.data); };
        mediaRecorder.onstop=()=>{
            const blob=new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
            const ext = blob.type.includes('mp4') ? 'mp4' : 'webm';
            const file=new File([blob], `camera-video-${Date.now()}.${ext}`, { type: blob.type });
            if(file.size>30*1024*1024){ showStatus('حجم الفيديو كبير جداً', false); return; }
            addFiles([file]);
            showStatus(`تم تسجيل الفيديو (${(file.size/1024/1024).toFixed(1)} MB) ✓`, true);
        };
        mediaRecorder.start(100);
        isRecording=true;
        seconds=0;
        cameraTimer.classList.remove('hidden');
        cameraTimerText.textContent='00:00';
        timerInterval=setInterval(updateTimer, 1000);
        startBtn.classList.add('hidden');
        stopBtn.classList.remove('hidden');
        captureBtn.disabled=true; captureBtn.classList.add('opacity-50');
        modeLabel.textContent='— تسجيل...';
        showStatus('بدأ التسجيل — اضغط إيقاف عند الانتهاء', true);
    }
    function stopRecording(){
        if(mediaRecorder && isRecording){
            mediaRecorder.stop();
            isRecording=false;
            clearInterval(timerInterval);
            cameraTimer.classList.add('hidden');
            startBtn.classList.remove('hidden');
            stopBtn.classList.add('hidden');
            captureBtn.disabled=false; captureBtn.classList.remove('opacity-50');
            modeLabel.textContent='— صورة';
        }
    }

    openBtn?.addEventListener('click', openCamera);
    closeBtn?.addEventListener('click', closeCamera);
    backdrop?.addEventListener('click', closeCamera);
    switchBtn?.addEventListener('click', switchCamera);
    captureBtn?.addEventListener('click', capturePhoto);
    startBtn?.addEventListener('click', startRecording);
    stopBtn?.addEventListener('click', stopRecording);
    document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !cameraModal.classList.contains('hidden')) closeCamera(); });
    document.addEventListener('visibilitychange', ()=>{ if(document.hidden && !cameraModal.classList.contains('hidden')) stopCamera(); });

    // ——— Preserve files on validation error (AJAX) ———
    const formEl = document.getElementById('create-form');
    const formErrorsEl = document.getElementById('form-errors');
    let submitActionVal = 'save';
    formEl?.querySelectorAll('button[type="submit"][name="action"]').forEach(btn=>{
        btn.addEventListener('click', ()=>{ submitActionVal = btn.value; });
    });
    formEl?.addEventListener('submit', async (e)=>{
        const floorEl=document.getElementById('floor_number');
        const camEl=document.getElementById('camera_number');
        const descEl=document.getElementById('description');
        const hiddenEl=document.getElementById('observed_at');
        let clientErrors=[];
        if(!floorEl.value) clientErrors.push('رقم الطابق مطلوب');
        if(!camEl.value) clientErrors.push('رقم الكاميرا مطلوب');
        if(!hiddenEl.value) clientErrors.push('تاريخ ووقت الرصد مطلوب');
        if(!descEl.value.trim()) clientErrors.push('الوصف مطلوب');
        if(clientErrors.length){
            e.preventDefault();
            formErrorsEl.innerHTML='<div class="font-bold mb-1">يرجى تصحيح الحقول:</div><ul class="list-disc list-inside space-y-1">'+clientErrors.map(m=>`<li>${m}</li>`).join('')+'</ul><p class="mt-2 text-xs">الملفات محفوظة ولن تضيع</p>';
            formErrorsEl.classList.remove('hidden');
            formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
            return;
        }
        if(fileTransfer.files.length>0 || recordedAudioBlob){
            e.preventDefault();
            formErrorsEl.classList.add('hidden');
            const submitBtn=e.submitter || document.activeElement;
            if(submitBtn) submitBtn.disabled=true;
            const fd=new FormData(formEl);
            fd.delete('files[]');
            Array.from(fileTransfer.files).forEach(f=> fd.append('files[]', f));
            if(recordedAudioBlob){
                const ext=audioMimeToExt(recordedAudioBlob.type||'audio/webm');
                fd.append('files[]', recordedAudioBlob, 'recording-'+Date.now()+'.'+ext);
            }
            fd.set('action', submitActionVal);
            try{
                // --- Fetch with timeout ---
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 30000); // 30s timeout
                
                const res = await fetch(formEl.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
                    },
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                
                if(res.ok){
                    if(recordedAudioUrl) URL.revokeObjectURL(recordedAudioUrl);
                    recordedAudioBlob=null; recordedAudioUrl=null;
                    let data=null;
                    try{ data=await res.clone().json(); }catch(_){}
                    if(data && data.redirect) window.location.href=data.redirect;
                    else window.location.href="{{ route('notes.index') }}";
                    return;
                }
                if(res.status===422){
                    const data=await res.json();
                    const errors=data.errors||{};
                    let html='<div class="font-bold mb-1">يرجى تصحيح الحقول:</div><ul class="list-disc list-inside space-y-1">';
                    for(const [field,msgs] of Object.entries(errors)){
                        for(const msg of msgs) html+=`<li>${msg}</li>`;
                    }
                    html+='</ul><p class="mt-2 text-xs font-bold text-[#0e6a38]">الصور والفيديوهات محفوظة — لا تحتاج لإعادة اختيارها ✓</p>';
                    formErrorsEl.innerHTML=html;
                    formErrorsEl.classList.remove('hidden');
                    formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
                } else {
                    // Show error instead of silent fallback
                    formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">فشل الإرسال (كود: '+res.status+')</div><p class="text-xs">يرجى المحاولة مرة أخرى أو التواصل مع الدعم.</p>';
                    formErrorsEl.classList.remove('hidden');
                    formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
                }
            }catch(err){
                if(err.name === 'AbortError'){
                    formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">انتهت مهلة الإرسال (30 ثانية)</div><p class="text-xs">تحقق من اتصالك أو قلل حجم المرفقات.</p>';
                } else {
                    formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">خطأ في الشبكة</div><p class="text-xs">'+err.message+'</p>';
                }
                formErrorsEl.classList.remove('hidden');
                formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
            }finally{
                if(submitBtn) submitBtn.disabled=false;
            }
        }
    });

    // ——— Audio Recorder ———
    const audioRecordBtn=document.getElementById('audio-record-btn');
    const audioPreview=document.getElementById('audio-preview');
    const audioPreviewIcon=document.getElementById('audio-preview-icon');
    const audioPreviewStatus=document.getElementById('audio-preview-status');
    const audioPreviewTimer=document.getElementById('audio-preview-timer');
    const audioPreviewPlayer=document.getElementById('audio-preview-player');
    const audioPreviewDelete=document.getElementById('audio-preview-delete');
    const audioPreviewPending=document.getElementById('audio-preview-pending');
    const hasGetUserMedia=!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia);
    let audioRecorder=null;
    let audioChunks=[];
    let audioStream=null;
    let audioTimer=null;
    let audioStartTime=0;
    let recordedAudioBlob=null;
    let recordedAudioUrl=null;

    function showAudioPreview(msg, showTimer){
        if(!audioPreview) return;
        audioPreview.classList.remove('hidden');
        if(audioPreviewStatus) audioPreviewStatus.textContent=msg;
        if(audioPreviewTimer){ audioPreviewTimer.classList.add('hidden'); }
        if(audioPreviewPlayer){ audioPreviewPlayer.classList.add('hidden'); audioPreviewPlayer.src=''; }
        if(audioPreviewDelete) audioPreviewDelete.classList.add('hidden');
        if(audioPreviewPending) audioPreviewPending.classList.add('hidden');
        if(audioPreviewIcon) audioPreviewIcon.innerHTML='<svg class="w-5 h-5 text-red-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>';
    }
    function showAudioRecorded(){
        if(audioPreviewIcon) audioPreviewIcon.innerHTML='<svg class="w-5 h-5 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>';
        if(audioPreviewStatus) audioPreviewStatus.textContent='تم التسجيل';
        if(audioPreviewTimer) audioPreviewTimer.classList.remove('hidden');
        if(audioPreviewPlayer){ audioPreviewPlayer.classList.remove('hidden'); audioPreviewPlayer.src=recordedAudioUrl; }
        if(audioPreviewDelete) audioPreviewDelete.classList.remove('hidden');
        if(audioPreviewPending) audioPreviewPending.classList.remove('hidden');
    }
    function hideAudioPreview(){
        if(audioPreview) audioPreview.classList.add('hidden');
    }
    function cleanupAudioRecording(){
        if(audioStream){ audioStream.getTracks().forEach(t=>t.stop()); audioStream=null; }
        if(audioRecorder){ audioRecorder=null; }
        if(audioTimer){ clearInterval(audioTimer); audioTimer=null; }
        audioChunks=[];
        if(recordedAudioUrl){ URL.revokeObjectURL(recordedAudioUrl); recordedAudioUrl=null; }
        recordedAudioBlob=null;
        audioRecordBtn.classList.remove('bg-red-50','border-red-300','text-red-600');
        audioRecordBtn.classList.add('bg-[#fdfcfa]','border-[#e6e9e1]','text-[#1a2e1f]');
        audioRecordBtn.innerHTML='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>تسجيل صوتي';
        hideAudioPreview();
    }
    function getSupportedAudioMime(){
        const types=['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus','audio/ogg','audio/mp4'];
        for(const t of types){ if(window.MediaRecorder&&MediaRecorder.isTypeSupported(t)) return t; }
        return '';
    }
    function audioMimeToExt(mime){
        const m=mime.split(';')[0].trim();
        if(m==='audio/webm') return 'webm';
        if(m==='audio/ogg') return 'ogg';
        if(m==='audio/mp4') return 'm4a';
        if(m==='audio/mpeg') return 'mp3';
        return 'webm';
    }
    if(audioPreviewDelete){
        audioPreviewDelete.addEventListener('click',()=>{
            cleanupAudioRecording();
        });
    }
    if(audioRecordBtn){
        if(!hasGetUserMedia){
            audioRecordBtn.addEventListener('click',()=>{
                const camModal=document.getElementById('camera-modal');
                if(camModal) camModal.classList.remove('hidden');
            });
        } else {
            audioRecordBtn.addEventListener('click', async()=>{
                if(audioRecorder && audioRecorder.state==='recording'){
                    audioRecorder.stop();
                    return;
                }
                if(recordedAudioBlob){
                    cleanupAudioRecording();
                    return;
                }
                try{
                    showAudioPreview('جاري طلب الميكروفون...', false);
                    audioStream=await navigator.mediaDevices.getUserMedia({audio:true});
                    audioChunks=[];
                    const mime=getSupportedAudioMime();
                    const opts=mime?{mimeType:mime}:{};
                    audioRecorder=new MediaRecorder(audioStream, opts);
                    audioRecorder.ondataavailable=e=>{if(e.data.size>0) audioChunks.push(e.data);};
                    audioRecorder.onstop=()=>{
                        const blob=new Blob(audioChunks,{type:audioRecorder.mimeType||'audio/webm'});
                        recordedAudioBlob=blob;
                        recordedAudioUrl=URL.createObjectURL(blob);
                        audioStream.getTracks().forEach(t=>t.stop());
                        audioStream=null;
                        showAudioRecorded();
                        audioRecordBtn.classList.remove('bg-[#fdfcfa]','border-[#e6e9e1]','text-[#1a2e1f]');
                        audioRecordBtn.classList.add('bg-red-50','border-red-300','text-red-600');
                        audioRecordBtn.innerHTML='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>حذف التسجيل';
                    };
                    audioRecorder.start();
                    audioStartTime=Date.now();
                    audioRecordBtn.classList.remove('bg-[#fdfcfa]','border-[#e6e9e1]','text-[#1a2e1f]');
                    audioRecordBtn.classList.add('bg-red-50','border-red-300','text-red-600');
                    audioRecordBtn.innerHTML='<span class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></span><span id="audio-timer">00:00</span> — إيقاف';
                    if(audioPreviewStatus) audioPreviewStatus.textContent='جاري التسجيل...';
                    if(audioPreviewTimer) audioPreviewTimer.classList.remove('hidden');
                    audioTimer=setInterval(()=>{
                        const el=document.getElementById('audio-timer');
                        const tel=document.getElementById('audio-preview-timer');
                        const s=Math.floor((Date.now()-audioStartTime)/1000);
                        const t=String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
                        if(el) el.textContent=t;
                        if(tel) tel.textContent=t;
                    },500);
                }catch(err){
                    hideAudioPreview();
                    const camModal=document.getElementById('camera-modal');
                    if(camModal) camModal.classList.remove('hidden');
                }
            });
        }
    }
</script>
@endpush
@endsection
