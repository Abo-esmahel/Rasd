@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between gap-3 mb-4 sm:mb-6">
    <div class="flex items-center gap-3 min-w-0">
        <a href="{{ route('notes.index') }}" class="w-10 h-10 rounded-2xl bg-white dark:bg-[#252b26] border border-[#e6e9e1] dark:border-[#2e352e] flex items-center justify-center text-ink-400 dark:text-[#9bb0a0] hover:text-[#0e6a38] dark:hover:text-[#4ade80] hover:border-[#0e6a38]/40 hover:shadow-md transition shrink-0" aria-label="{{ __('ui.back_aria') }}">
            <x-phosphor-caret-left-duotone class="w-4 h-4 rtl:rotate-180" />
        </a>
        <div class="min-w-0">
            <h1 class="text-[19px] sm:text-[22px] font-extrabold tracking-tight text-ink-800 dark:text-[#e7ece5] leading-tight">{{ __('ui.new_note_title') }}</h1>
            <p class="text-xs text-ink-400 dark:text-[#8a9a8e] mt-0.5 font-medium">{{ __('ui.my_notes_hint') }}</p>
        </div>
    </div>
    <div id="create-steps-hint" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#eef4f0] dark:bg-[#1e3328] border border-[#cde7d6] dark:border-[#24402e] text-[11px] font-bold text-[#0e6a38] dark:text-[#4ade80] shrink-0">
        <span class="w-1.5 h-1.5 rounded-full bg-[#0e6a38] dark:bg-[#4ade80] animate-pulse"></span>
        <span id="create-steps-text">1 / 4</span>
    </div>
  </div>

<div class="sm:hidden sticky top-[58px] z-30 mb-4">
    <div class="bg-white dark:bg-[#252b26] rounded-2xl border border-[#e6e9e1] dark:border-[#2e352e] shadow-[0_4px_18px_rgba(26,46,31,0.08)] p-2.5">
        <div class="flex gap-1.5 overflow-x-auto scrollbar-hide" style="scrollbar-width:none;" role="navigation" aria-label="أقسام النموذج">
            <a href="#sec-loc" data-mnav="sec-loc" class="shrink-0 inline-flex items-center gap-1.5 px-3.5 min-h-[40px] rounded-full text-xs font-extrabold border transition bg-[#0e6a38] text-white border-[#0e6a38]">1 · {{ __('ui.floor_label') }}</a>
            <a href="#sec-time" data-mnav="sec-time" class="shrink-0 inline-flex items-center gap-1.5 px-3.5 min-h-[40px] rounded-full text-xs font-bold border transition bg-white dark:bg-[#2a302b] text-ink-600 dark:text-[#9bb0a0] border-[#e6e9e1] dark:border-[#343a34]">2 · {{ __('ui.time_label') }}</a>
            <a href="#sec-desc" data-mnav="sec-desc" class="shrink-0 inline-flex items-center gap-1.5 px-3.5 min-h-[40px] rounded-full text-xs font-bold border transition bg-white dark:bg-[#2a302b] text-ink-600 dark:text-[#9bb0a0] border-[#e6e9e1] dark:border-[#343a34]">3 · {{ __('ui.description_label') }}</a>
            <a href="#sec-files" data-mnav="sec-files" class="shrink-0 inline-flex items-center gap-1.5 px-3.5 min-h-[40px] rounded-full text-xs font-bold border transition bg-white dark:bg-[#2a302b] text-ink-600 dark:text-[#9bb0a0] border-[#e6e9e1] dark:border-[#343a34]">4 · {{ __('ui.attachments_label') }}</a>
        </div>
        <div class="mt-2 flex items-center gap-1.5 px-1 text-[11px] font-bold text-ink-500 dark:text-[#9bb0a0] tabular-nums whitespace-nowrap overflow-hidden">
            <x-phosphor-video-camera-duotone class="w-3.5 h-3.5 text-[#0e6a38] dark:text-[#4ade80] shrink-0" />
            <span id="m-sum-loc" class="truncate min-w-0 flex-1">—</span>
            <span class="opacity-40 shrink-0">·</span>
            <x-phosphor-clock-duotone class="w-3.5 h-3.5 text-[#0e6a38] dark:text-[#4ade80] shrink-0" />
            <span id="m-sum-time" class="truncate min-w-0 flex-1">—</span>
            <span class="opacity-40 shrink-0">·</span>
            <span id="m-sum-desc" class="shrink-0">0</span>
        </div>
    </div>
</div>

<div class="max-w-3xl mx-auto">
    <div class="bg-white dark:bg-[#252b26] rounded-[20px] border border-[#e6e9e1] dark:border-[#2e352e] shadow-[0_2px_20px_rgba(26,46,31,0.05)] overflow-hidden">
        <form method="POST" action="{{ route('notes.store', [], false) }}" enctype="multipart/form-data" class="p-4 sm:p-6 space-y-5 sm:space-y-6" id="create-form" novalidate>
            @csrf
            <div id="form-errors" class="hidden p-4 bg-red-50 dark:bg-[#2d1f1f] border border-red-200 dark:border-[#3d2626] rounded-2xl text-sm text-red-700 dark:text-[#f08080]"></div>
            <div id="upload-progress" class="hidden p-4 bg-[#eef4f0] dark:bg-[#1e3328] border border-[#cde7d6] dark:border-[#24402e] rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-bold text-[#0e6a38] dark:text-[#4ade80] flex items-center gap-2"><span class="w-3.5 h-3.5 border-2 border-[#0e6a38] dark:border-[#4ade80] border-t-transparent rounded-full animate-spin"></span>{{ __('ui.uploading') }}</span>
                    <span id="upload-progress-text" class="text-xs font-bold text-ink-500 dark:text-[#9bb0a0] tabular-nums">0%</span>
                </div>
                <div class="w-full bg-white dark:bg-[#1e2320] rounded-full h-2.5 border border-[#e6e9e1] dark:border-[#2e352e] overflow-hidden">
                    <div id="upload-progress-bar" class="h-2.5 rounded-full bg-gradient-to-l from-[#0e6a38] to-[#2e9e5b] transition-all duration-300" style="width:0%"></div>
                </div>
                <div id="upload-progress-detail" class="mt-1.5 text-[11px] text-ink-400 dark:text-[#8a9a8e] font-medium">{{ __('ui.uploading_dont_close') }}</div>
            </div>
            <section id="sec-loc" data-step="1" class="scroll-mt-44 rounded-2xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] p-4 sm:p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-7 h-7 rounded-xl bg-[#0e6a38] text-white text-[13px] font-extrabold flex items-center justify-center shadow-sm shrink-0">1</span>
                    <h2 class="text-sm font-extrabold text-ink-800 dark:text-[#e7ece5]">{{ __('ui.floor_label') }} · {{ __('ui.camera_label') }}</h2>
                </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 min-w-0">
                <div class="min-w-0">
                    <label for="floor_number" class="block text-[13px] sm:text-sm font-bold text-ink-700 dark:text-[#d5ded6] mb-1.5">{{ __('ui.floor_label') }} <span class="text-red-500">*</span></label>
                    <input type="number" id="floor_number" name="floor_number" value="{{ old('floor_number') }}" min="0" required inputmode="numeric"
                        class="block w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-3 px-3 sm:px-4 min-h-[48px] text-[16px] sm:text-sm font-bold text-ink-800 dark:text-[#e7ece5] placeholder:text-ink-300 dark:placeholder:text-[#6b7a6e] placeholder:font-medium focus:border-[#0e6a38] dark:focus:border-[#4ade80] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition @error('floor_number') border-red-400 @enderror"
                        placeholder="3">
                    @error('floor_number') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="min-w-0">
                    <label for="camera_number" class="block text-[13px] sm:text-sm font-bold text-ink-700 dark:text-[#d5ded6] mb-1.5">{{ __('ui.camera_label') }} <span class="text-red-500">*</span></label>
                    <input type="number" id="camera_number" name="camera_number" value="{{ old('camera_number') }}" min="1" required inputmode="numeric"
                        class="block w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-3 px-3 sm:px-4 min-h-[48px] text-[16px] sm:text-sm font-bold text-ink-800 dark:text-[#e7ece5] placeholder:text-ink-300 dark:placeholder:text-[#6b7a6e] placeholder:font-medium focus:border-[#0e6a38] dark:focus:border-[#4ade80] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition @error('camera_number') border-red-400 @enderror"
                        placeholder="12">
                    @error('camera_number') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>
            </section>

            <section id="sec-time" data-step="2" class="scroll-mt-44 rounded-2xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] p-4 sm:p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-7 h-7 rounded-xl bg-[#0e6a38] text-white text-[13px] font-extrabold flex items-center justify-center shadow-sm shrink-0">2</span>
                    <h2 class="text-sm font-extrabold text-ink-800 dark:text-[#e7ece5]">{{ __('ui.time_label') }} <span class="text-red-500">*</span></h2>
                </div>
                @php
                    $oldObserved = old('observed_at');
                    $oldDate = $oldObserved ? date('Y-m-d', strtotime($oldObserved)) : '';
                    $oldTime = $oldObserved ? date('H:i', strtotime($oldObserved)) : '';
                    $oldObservedEnd = old('observed_end_at');
                    $oldEndTime = $oldObservedEnd ? date('H:i', strtotime($oldObservedEnd)) : '';
                @endphp
                <div class="space-y-3 min-w-0">
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.date_label') }}</div>
                        <input type="date" id="observed_date" value="{{ $oldDate }}" required dir="ltr"
                            class="block w-full min-w-0 max-w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-3 px-3 sm:px-4 min-h-[48px] text-left text-sm font-bold text-ink-800 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                            style="color-scheme: light dark;">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.start_label') }}</div>
                            <input type="time" id="observed_time" value="{{ $oldTime }}" required step="60" dir="ltr"
                                class="block w-full min-w-0 max-w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-3 px-3 sm:px-4 min-h-[48px] text-left text-sm font-bold tabular-nums text-ink-800 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                                style="color-scheme: light dark;">
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-ink-500 dark:text-[#9bb0a0] mb-1.5">{{ __('ui.end_label') }} <span class="font-medium opacity-60">{{ __('ui.attachments_optional') }}</span></div>
                            <input type="time" id="observed_end_time" value="{{ $oldEndTime }}" step="60" dir="ltr"
                                class="block w-full min-w-0 max-w-full rounded-xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] py-3 px-3 sm:px-4 min-h-[48px] text-left text-sm font-bold tabular-nums text-ink-800 dark:text-[#e7ece5] focus:border-[#0e6a38] dark:focus:border-[#4ade80] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition cursor-pointer"
                                style="color-scheme: light dark;">
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" data-preset="now" class="preset-btn inline-flex items-center gap-1.5 px-4 py-2 min-h-[40px] rounded-full bg-[#0e6a38] hover:bg-[#0a4d28] active:scale-[0.97] text-white text-xs font-extrabold shadow-[0_2px_10px_rgba(14,106,56,0.25)] transition">{{ __('ui.now_btn') }}</button>
                    <button type="button" data-preset="hour-ago" class="preset-btn inline-flex items-center gap-1.5 px-4 py-2 min-h-[40px] rounded-full bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-[#1a2e1f] dark:text-[#e7ece5] text-xs font-bold hover:border-[#0e6a38]/40 hover:text-[#0e6a38] dark:hover:text-[#4ade80] active:scale-[0.97] transition">{{ __('ui.hour_ago_btn') }}</button>
                    <button type="button" data-preset="today-08" class="preset-btn inline-flex items-center gap-1.5 px-4 py-2 min-h-[40px] rounded-full bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-[#1a2e1f] dark:text-[#e7ece5] text-xs font-bold hover:border-[#0e6a38]/40 hover:text-[#0e6a38] dark:hover:text-[#4ade80] active:scale-[0.97] transition">{{ __('ui.today_08_btn') }}</button>
                    <button type="button" id="clear-datetime" class="inline-flex items-center gap-1 px-3 py-2 min-h-[40px] rounded-full bg-transparent text-ink-400 dark:text-[#8a9a8e] text-xs font-bold hover:bg-black/5 dark:hover:bg-white/5 transition">{{ __('ui.clear_btn') }}</button>
                </div>
                <div class="mt-3 flex items-center gap-2.5 p-3 rounded-2xl bg-[#eef4f0] dark:bg-[#1e3328] border border-[#cde7d6] dark:border-[#24402e]">
                    <span class="w-8 h-8 rounded-xl bg-white dark:bg-[#2a302b] border border-[#cde7d6] dark:border-[#24402e] flex items-center justify-center shrink-0">
                        <x-phosphor-clock-duotone class="w-4 h-4 text-[#0e6a38] dark:text-[#4ade80]" />
                    </span>
                    <div id="observed_preview" class="text-[13px] font-bold text-[#0e4d28] dark:text-[#bfe9cd] truncate">{{ __('ui.choose_time') }}</div>
                </div>
                <input type="hidden" name="observed_at" id="observed_at" value="{{ old('observed_at') }}">
                <input type="hidden" name="observed_end_at" id="observed_end_at" value="{{ old('observed_end_at') }}">
                @error('observed_at') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                @error('observed_end_at') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
            </section>

            <section id="sec-desc" data-step="3" class="scroll-mt-44 rounded-2xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] p-4 sm:p-5">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xl bg-[#0e6a38] text-white text-[13px] font-extrabold flex items-center justify-center shadow-sm shrink-0">3</span>
                        <label for="description" class="text-sm font-extrabold text-ink-800 dark:text-[#e7ece5]">{{ __('ui.description_label') }} <span class="text-red-500">*</span></label>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-ink-400 dark:text-[#8a9a8e] bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] rounded-full px-2.5 py-1 tabular-nums shrink-0">
                        <span id="desc-count">0</span><span class="opacity-50">/ 5000</span>
                    </span>
                </div>
                <div class="relative">
                    <textarea id="description" name="description" rows="6" required maxlength="5000"
                        class="block w-full rounded-2xl border border-[#e6e9e1] dark:border-[#343a34] bg-white dark:bg-[#2a302b] p-4 min-h-[140px] text-[15px] sm:text-sm leading-[1.9] text-ink-800 dark:text-[#e7ece5] placeholder:text-ink-300 dark:placeholder:text-[#6b7a6e] focus:border-[#0e6a38] dark:focus:border-[#4ade80] focus:ring-4 focus:ring-[#0e6a38]/10 outline-none transition resize-y shadow-[inset_0_1px_3px_rgba(26,46,31,0.04)] @error('description') border-red-400 @enderror"
                        placeholder="{{ __('ui.desc_placeholder') }}">{{ old('description') }}</textarea>
                </div>
                @error('description') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3">
                    @include('notes.partials.smart_hint')
                </div>
            </section>

            <section id="sec-files" data-step="4" class="scroll-mt-44 rounded-2xl border border-[#eceee9] dark:border-[#2e352e] bg-[#fdfcfa] dark:bg-[#1e2320] p-4 sm:p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-7 h-7 rounded-xl bg-ink-800 dark:bg-[#2a302b] dark:border dark:border-[#343a34] text-white dark:text-[#e7ece5] text-[13px] font-extrabold flex items-center justify-center shadow-sm shrink-0">4</span>
                    <h2 class="text-sm font-extrabold text-ink-800 dark:text-[#e7ece5]">{{ __('ui.attachments_label') }}</h2>
                    <span class="text-[11px] font-medium text-ink-400 dark:text-[#8a9a8e]">{{ __('ui.attachments_optional') }}</span>
                </div>
                <div class="rounded-2xl border-2 border-dashed border-[#dbe2da] dark:border-[#343a34] bg-white dark:bg-[#2a302b]/50 hover:border-[#0e6a38]/50 dark:hover:border-[#4ade80]/40 transition p-4 sm:p-5 text-center group" id="drop-zone">
                    <div class="mx-auto w-11 h-11 rounded-2xl bg-[#eef4f0] dark:bg-[#1e3328] border border-[#cde7d6] dark:border-[#24402e] flex items-center justify-center transition group-hover:scale-105">
                        <x-phosphor-cloud-arrow-up-duotone class="w-5 h-5 text-[#0e6a38] dark:text-[#4ade80]" />
                    </div>
                    <p class="mt-2.5 text-xs font-medium text-ink-400 dark:text-[#8a9a8e]">{{ __('ui.uploading_originals') }}</p>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 min-w-0">
                        <label for="files" class="min-w-0 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 min-h-[44px] rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] active:scale-[0.98] text-white font-bold text-sm text-center cursor-pointer shadow-[0_2px_10px_rgba(14,106,56,0.25)] transition">
                            <x-phosphor-plus-duotone class="w-4 h-4 shrink-0" />
                            <span class="truncate">{{ __('ui.choose_files') }}</span>
                        </label>
                        <button type="button" id="open-camera-btn" class="min-w-0 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 min-h-[44px] rounded-xl bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-[#1a2e1f] dark:text-[#e7ece5] font-bold text-sm text-center hover:border-[#0e6a38]/40 active:scale-[0.98] transition">
                            <x-phosphor-video-camera-duotone class="w-4 h-4 shrink-0" />
                            <span class="truncate">{{ __('ui.live_camera') }}</span>
                        </button>
                        <button type="button" id="audio-record-btn" class="min-w-0 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 min-h-[44px] rounded-xl bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-[#1a2e1f] dark:text-[#e7ece5] font-bold text-sm text-center hover:border-[#0e6a38]/40 active:scale-[0.98] transition">
                            <x-phosphor-microphone-duotone class="w-4 h-4" />
                            {{ __('ui.audio_recording') }}
                        </button>
                    </div>
                    <div id="audio-preview" class="hidden mt-3 p-3 bg-white border border-[#e6e9e1] rounded-xl">
                        <div class="flex items-center gap-3">
                            <div id="audio-preview-icon" class="w-10 h-10 rounded-lg bg-red-50 border border-red-200 flex items-center justify-center shrink-0">
                                <x-phosphor-microphone-duotone class="w-5 h-5 text-red-500 animate-pulse" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div id="audio-preview-status" class="text-sm font-bold text-ink-700">{{ __('ui.requesting_mic') }}</div>
                                <div id="audio-preview-timer" class="text-xs text-ink-400 mt-0.5 hidden">00:00</div>
                            </div>
                            <button type="button" id="audio-preview-delete" class="hidden shrink-0 w-8 h-8 rounded-lg hover:bg-red-50 flex items-center justify-center text-ink-400 hover:text-red-500 transition" title="{{ __('ui.delete_recording') }}">
                                <x-phosphor-x-duotone class="w-4 h-4" />
                            </button>
                        </div>
                        <audio id="audio-preview-player" class="hidden w-full mt-2 rounded-lg" controls></audio>
                        <div id="audio-preview-pending" class="hidden mt-2 text-[11px] text-[#0e6a38] font-bold">{{ __('ui.will_attach_on_save') }}</div>
                    </div>
                    <input type="file" id="files" name="files[]" multiple accept="image/*,video/*,audio/*,.aac,.m4a,.mp3,.wav,.ogg,.flac,.opus,.wma,.aiff,.amr,.3ga,.weba" class="hidden">
                    <div id="file-list" class="mt-3 hidden text-right space-y-1.5"></div>
                </div>


                <div id="camera-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4">
                    <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" id="camera-backdrop"></div>
                    <div class="relative bg-white rounded-xl sm:rounded-2xl w-full max-w-lg max-h-[96vh] sm:max-h-[90vh] flex flex-col overflow-hidden shadow-2xl mx-1 sm:mx-auto">
                        <div class="px-4 py-3 border-b border-[#e6e9e1] flex items-center justify-between shrink-0">
                            <h3 class="text-sm font-extrabold text-ink-800 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                {{ __('ui.live_camera') }}
                                <span id="camera-mode-label" class="text-xs font-medium text-ink-400 mr-1">{{ __('ui.camera_photo_mode') }}</span>
                            </h3>
                            <div class="flex items-center gap-1.5">
                                <button type="button" id="switch-camera-btn" class="w-8 h-8 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center text-ink-500 hover:text-ink-700 transition" title="{{ __('ui.switch_camera') }}">
                                    <x-phosphor-arrows-left-right-duotone class="w-4 h-4" />
                                </button>
                                <button type="button" id="close-camera-btn" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-400 hover:text-ink-700 transition" title="{{ __('ui.close_camera') }}">
                                    <x-phosphor-x-duotone class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 min-h-0 bg-black flex flex-col items-center justify-center p-3 sm:p-4 relative overflow-hidden">
                            <video id="camera-video" autoplay playsinline muted class="w-full h-auto max-h-[50vh] rounded-xl bg-black object-contain hidden"></video>
                            <canvas id="camera-canvas" class="hidden"></canvas>
                            <div id="camera-placeholder" class="text-center py-12">
                                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mx-auto mb-3">
                                    <x-phosphor-video-camera-duotone class="w-6 h-6 text-white/70" />
                                </div>
                                <p class="text-sm text-white/70">{{ __('ui.camera_starting') }}</p>
                                <p id="camera-error" class="hidden mt-2 text-xs text-red-300 max-w-xs mx-auto leading-5"></p>
                            </div>
                            <div id="camera-fallback" class="w-full max-w-md p-4 border-t border-amber-200 bg-amber-50/50">
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                        <x-phosphor-camera-duotone class="w-7 h-7 text-amber-600" />
                                        <span class="text-sm font-bold text-ink-800">{{ __('ui.photo_capture_fallback') }}</span>
                                        <input type="file" accept="image/*" capture="environment" class="hidden" onchange="handleFallbackFile(this)">
                                    </label>
                                    <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                        <x-phosphor-video-camera-duotone class="w-7 h-7 text-amber-600" />
                                        <span class="text-sm font-bold text-ink-800">{{ __('ui.video_recording_fallback') }}</span>
                                        <input type="file" accept="video/*" capture="environment" class="hidden" onchange="handleFallbackFile(this)">
                                    </label>
                                    <label class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border-2 border-dashed border-amber-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-center shadow-sm">
                                        <x-phosphor-microphone-duotone class="w-7 h-7 text-red-500" />
                                        <span class="text-sm font-bold text-ink-800">{{ __('ui.audio_recording_fallback') }}</span>
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
                                    <x-phosphor-camera-duotone class="w-4 h-4" />
                                    {{ __('ui.capture_photo') }}
                                </button>
                                <button type="button" id="start-record-btn" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-white border-2 border-red-200 text-red-600 font-bold text-sm hover:bg-red-50 transition">
                                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                    {{ __('ui.start_video_recording') }}
                                </button>
                                <button type="button" id="stop-record-btn" class="hidden flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-sm transition">
                                    <x-phosphor-stop-duotone class="w-4 h-4" />
                                    {{ __('ui.stop_and_save') }}
                                </button>
                            </div>
                            <p class="mt-2.5 text-center text-[11px] text-ink-400 leading-4">{{ __('ui.media_note_fallback') }}</p>
                            <p id="camera-status" class="hidden mt-2 text-center text-xs font-bold"></p>
                        </div>
                    </div>
                </div>
            </div>

            </section>

            <div class="m-crud-bar flex flex-col-reverse sm:flex-row gap-2.5 sm:gap-3 pt-4 sm:pt-5 border-t border-[#eceee9] dark:border-[#2e352e]">
                <a href="{{ route('notes.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-3 min-h-[48px] sm:min-h-0 sm:py-2.5 rounded-2xl sm:rounded-xl bg-transparent border border-[#e6e9e1] dark:border-[#343a34] text-[#525252] dark:text-[#9bb0a0] font-bold text-sm hover:bg-black/5 dark:hover:bg-white/5 active:scale-[0.98] transition">{{ __('ui.cancel_btn') }}</a>
                <div class="flex-1 flex flex-col sm:flex-row gap-2 sm:justify-end">
                    @if(auth()->user()->isReportWriter())
                        <button type="submit" name="action" value="save" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3 min-h-[48px] sm:min-h-0 sm:py-2.5 rounded-2xl sm:rounded-xl bg-gradient-to-l from-[#0e6a38] to-[#12934f] hover:from-[#0a4d28] hover:to-[#0e6a38] active:scale-[0.98] text-white font-extrabold text-sm shadow-[0_4px_18px_rgba(14,106,56,0.3)] transition">
                            <x-phosphor-check-duotone class="w-4 h-4" />
                            {{ __('ui.save_note_btn') }}
                        </button>
                    @else
                        <button type="submit" name="action" value="send" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3 min-h-[48px] sm:min-h-0 sm:py-2.5 rounded-2xl sm:rounded-xl bg-gradient-to-l from-[#0e6a38] to-[#12934f] hover:from-[#0a4d28] hover:to-[#0e6a38] active:scale-[0.98] text-white font-extrabold text-sm shadow-[0_4px_18px_rgba(14,106,56,0.3)] transition sm:order-last">
                            <x-phosphor-paper-plane-tilt-duotone class="w-4 h-4 rtl:rotate-180" />
                            {{ __('ui.save_and_send_btn') }}
                        </button>
                        <button type="submit" name="action" value="save" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 min-h-[48px] sm:min-h-0 sm:py-2.5 rounded-2xl sm:rounded-xl bg-white dark:bg-[#2a302b] border border-[#e6e9e1] dark:border-[#343a34] text-[#1a2e1f] dark:text-[#e7ece5] font-bold text-sm hover:border-[#0e6a38]/40 active:scale-[0.98] transition">{{ __('ui.save_draft_btn') }}</button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const FORM_T = {
        floor: @json(__('ui.floor_required')),
        camera: @json(__('ui.camera_required')),
        datetime: @json(__('ui.datetime_required')),
        description: @json(__('ui.description_required')),
        fixFields: @json(__('ui.fix_fields'))
    };
    // Shared create-page UI dictionary (server-rendered per locale — no Gemini).
    const CRT_T = {
        chooseTime: @json(__('ui.choose_time')),
        dateLocale: @json(app()->getLocale() === 'en' ? 'en-US' : 'ar-EG'),
        netErr: @json(__('ui.network_error')),
        timeoutErr: @json(__('ui.timeout_error')),
        fAudio: @json(__('ui.file_audio')),
        fVideo: @json(__('ui.file_video')),
        fImage: @json(__('ui.file_image')),
        fOther: @json(__('ui.file_other')),
        camBadge: @json(__('ui.file_camera_badge')),
        recBadge: @json(__('ui.file_rec')),
        delTitle: @json(__('ui.file_remove')),
        limitMax: @json(__('ui.max_files_exceeded')),
        limitToast: @json(__('ui.upload_limit_files')),
        unsupported: @json(__('ui.unsupported_file_type')),
        imgBig: @json(__('ui.image_too_large')),
        vidBig: @json(__('ui.video_too_large')),
        audBig: @json(__('ui.audio_too_large')),
        camHttps: @json(__('ui.camera_not_available_https')),
        camUnsupported: @json(__('ui.camera_not_supported')),
        camStarting: @json(__('ui.camera_starting')),
        camReady: @json(__('ui.camera_ready')),
        camPhoto: @json(__('ui.camera_photo_mode')),
        camRec: @json(__('ui.camera_recording_mode')),
        camFallbackErr: @json(__('ui.camera_error_fallback')),
        camUnavailable: @json(__('ui.camera_unavailable')),
        fileAdded: @json(__('ui.file_added_from_camera')),
        camNotReady: @json(__('ui.camera_not_ready')),
        captureFail: @json(__('ui.capture_failed')),
        photoDone: @json(__('ui.photo_captured')),
        recUnsupported: @json(__('ui.recording_not_supported')),
        recStartFail: @json(__('ui.recording_start_failed')),
        recStarted: @json(__('ui.recording_started')),
        vidRecorded: @json(__('ui.video_recorded')),
        browserFail: @json(__('ui.browser_files_failed')),
        browserDetail: @json(__('ui.browser_files_detail')),
        totalBig: @json(__('ui.total_attachments_too_large')),
        serverLimit: @json(__('ui.server_limit_exceeded')),
        uploadingN: @json(__('ui.uploading_files_count')),
        dontClose: @json(__('ui.upload_pct_dont_close')),
        verify: @json(__('ui.upload_verify')),
        attachFail: @json(__('ui.attach_fail')),
        filesKept: @json(__('ui.files_kept')),
        mediaKept: @json(__('ui.media_kept')),
        mismatchShort: @json(__('ui.files_mismatch_short')),
        partial: @json(__('ui.server_received_partial')),
        sendFail: @json(__('ui.send_failed_code')),
        notSaved: @json(__('ui.not_saved_retry')),
        timeoutTitle: @json(__('ui.timeout_title')),
        timeoutHint: @json(__('ui.upload_timeout_hint')),
        audioDone: @json(__('ui.audio_recorded')),
        audioBtn: @json(__('ui.audio_recording')),
        micReq: @json(__('ui.requesting_mic')),
        delRec: @json(__('ui.delete_recording')),
        stopLbl: @json(__('ui.audio_stop')),
        recNow: @json(__('ui.audio_recording_now'))
    };
    function crtFill(tpl, map){ var s = String(tpl == null ? '' : tpl); Object.keys(map || {}).forEach(function(k){ s = s.split(k).join(map[k]); }); return s; }
    // إصلاح فهم الوقت: نرسل الوقت الجداري كما اختاره المستخدم (YYYY-MM-DDTHH:MM) بدون offset
    // حتى يُفسر على الخادم دائماً بمنطقة Asia/Damascus بغض النظر عن timezone جهاز المستخدم.
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
            try{ return d.toLocaleDateString(CRT_T.dateLocale,opts);}catch(e){ return date+' '+time; }
        }
        function sync(){
            const d=dateEl?.value, t=timeEl?.value, et=endTimeEl?.value;
            if(d && t){
                const base=d+'T'+t;
                hidden.value = base;
                const txt=toPreview(d,t);
                const endTxt=et ? ' — '+et : '';
                if(preview) preview.textContent = (txt || (d+' — '+t)) + endTxt;
                hidden.setCustomValidity('');
            } else {
                hidden.value='';
                if(preview){ preview.textContent=CRT_T.chooseTime; }
            }
            if(d && et){
                let endVal=d+'T'+et;
                try{ if(t && et < t){ const nd=new Date(d+'T'+et); nd.setDate(nd.getDate()+1); const pad2=n=>String(n).padStart(2,'0'); endVal=nd.getFullYear()+'-'+pad2(nd.getMonth()+1)+'-'+pad2(nd.getDate())+'T'+et; } }catch(_){}
                hiddenEnd.value = endVal;
            }
            else { hiddenEnd.value=''; }
        }
        // إتاحة sync عالمياً لمعالج الإرسال الخارجي (كان يفشل سابقاً لأن sync داخل IIFE)
        window._syncObservedCreate = sync;
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
        // ذكاء افتراضي: إن لم يكن هناك قيمة قديمة، عبّئ التاريخ والوقت الحاليين تلقائياً
        try{
            const hasOld = (hidden?.value || '').trim() !== '' || (dateEl?.value || '').trim() !== '';
            if(!hasOld && dateEl && timeEl){
                const n = new Date();
                const p2 = v => String(v).padStart(2,'0');
                dateEl.value = n.getFullYear()+'-'+p2(n.getMonth()+1)+'-'+p2(n.getDate());
                timeEl.value = p2(n.getHours())+':'+p2(n.getMinutes());
            }
        }catch(_){}
        sync();
        // مؤشر الخطوات + تكبير تلقائي للوصف
        try{
            const stepsTxt = document.getElementById('create-steps-text');
            const updSteps = ()=>{
                let done = 0;
                if(document.getElementById('floor_number')?.value && document.getElementById('camera_number')?.value) done++;
                if(document.getElementById('observed_at')?.value) done++;
                if((document.getElementById('description')?.value || '').trim().length >= 10) done++;
                if((typeof fileTransfer !== 'undefined' && fileTransfer?.files?.length > 0) || (typeof recordedAudioBlob !== 'undefined' && recordedAudioBlob)) done++;
                if(stepsTxt) stepsTxt.textContent = Math.min(4, done + 1) + ' / 4';
            };
            ['floor_number','camera_number','description'].forEach(id=>document.getElementById(id)?.addEventListener('input', updSteps));
            dateEl?.addEventListener('change', updSteps); timeEl?.addEventListener('change', updSteps);
            const ta = document.getElementById('description');
            if(ta){ const grow=()=>{ ta.style.height='auto'; ta.style.height=Math.min(420, Math.max(140, ta.scrollHeight))+'px'; }; ta.addEventListener('input', ()=>{grow(); updSteps();}); grow(); }
            updSteps();
        }catch(_){}
        document.getElementById('create-form')?.addEventListener('submit',e=>{
            sync();
            if(!hidden.value){ e.preventDefault(); hidden.setCustomValidity(FORM_T.datetime); hidden.reportValidity(); }
        });
    })();
    const desc=document.getElementById('description'),cnt=document.getElementById('desc-count');
    if(desc&&cnt){const u=()=>cnt.textContent=desc.value.length;desc.addEventListener('input',u);u();}


    // ضغط صور JPEG/WEBP قبل الرفع — صور الهاتف (5-12MB) تنزل لأقل من 1.5MB غالباً.
    // نفس الامتداد ونفس الـmime تماماً (لا PNG/HEIC/GIF/SVG حتى لا ينكسر فالديشن mimes).
    async function compressImageClient(file){
        try{
            const t=file.type||'';
            const ext=(file.name.split('.').pop()||'').toLowerCase();
            const ok=(t==='image/jpeg'&&(ext==='jpg'||ext==='jpeg'))||(t==='image/webp'&&ext==='webp');
            if(!ok) return file;
            if(file.size<900*1024) return file;
            if(typeof createImageBitmap!=='function') return file;
            const bmp=await createImageBitmap(file).catch(()=>null);
            if(!bmp) return file;
            const MAXD=1920, w=bmp.width, h=bmp.height;
            const scale=Math.min(1,MAXD/Math.max(w,h));
            if(scale>=1&&file.size<2.5*1024*1024){ if(bmp.close) bmp.close(); return file; }
            const c=document.createElement('canvas');
            c.width=Math.max(1,Math.round(w*scale)); c.height=Math.max(1,Math.round(h*scale));
            c.getContext('2d').drawImage(bmp,0,0,c.width,c.height);
            if(bmp.close) bmp.close();
            const blob=await new Promise(r=>{ try{ c.toBlob(r,t,0.82); }catch(_){ r(null); } });
            if(!blob||blob.size>=file.size) return file;
            return new File([blob],file.name,{type:t,lastModified:Date.now()});
        }catch(_){ return file; }
    }
    function setUploadProgress(pct, detail){
        const wrap=document.getElementById('upload-progress'), bar=document.getElementById('upload-progress-bar'), txt=document.getElementById('upload-progress-text'), det=document.getElementById('upload-progress-detail');
        if(!wrap) return;
        wrap.classList.remove('hidden');
        if(bar) bar.style.width=pct+'%';
        if(txt) txt.textContent=Math.round(pct)+'%';
        if(det && detail) det.textContent=detail;
    }
    function hideUploadProgress(){ const w=document.getElementById('upload-progress'); if(w) w.classList.add('hidden'); }
    function xhrUpload(url, formData, csrf, onProgress){
        return new Promise((resolve, reject)=>{
            const xhr=new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Accept','application/json');
            xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
            if(csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.timeout=600000;
            if(xhr.upload && onProgress){
                xhr.upload.onprogress=(e)=>{ if(e.lengthComputable){ const pct=Math.round(e.loaded/e.total*100); const loadedMB=(e.loaded/1024/1024).toFixed(1); const totalMB=(e.total/1024/1024).toFixed(1); onProgress(pct, e.loaded, e.total); } };
            }
            xhr.onload=()=>{ let data=null; try{ data=JSON.parse(xhr.responseText);}catch(_){} resolve({status:xhr.status, ok:xhr.status>=200&&xhr.status<300, data, raw:xhr.responseText, headers:xhr.getAllResponseHeaders()}); };
            xhr.onerror=()=>reject(new Error(CRT_T.netErr));
            xhr.ontimeout=()=>reject(Object.assign(new Error(CRT_T.timeoutErr),{name:'AbortError'}));
            xhr.send(formData);
        });
    }


    const input=document.getElementById('files'),zone=document.getElementById('drop-zone'),list=document.getElementById('file-list');
    let fileTransfer = new DataTransfer();


    let intendedFilesCount = 0;


    let pendingFiles = [];

    const MAX_FILES = {{ min(max(1, (int) ini_get('max_file_uploads') ?: 20), (int) config('attachments.max_per_note', 10)) }};


function syncInput(){ try{ input.files = fileTransfer.files; }catch(e){ } }
    function renderFiles(){
        const files=Array.from(fileTransfer.files);
        if(!files.length){ list.classList.add('hidden'); list.innerHTML=''; return; }
        list.classList.remove('hidden');
        const counters={img:0,vid:0,aud:0,other:0};
        const escAttr=s=>String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
        const friendlyName=f=>{
            if(f.type.startsWith('audio/')) return CRT_T.fAudio+' '+ (++counters.aud);
            if(f.type.startsWith('video/')) return CRT_T.fVideo+' '+ (++counters.vid);
            if(f.type.startsWith('image/')) return CRT_T.fImage+' '+ (++counters.img);
            const ext=(f.name.split('.').pop()||'').toLowerCase();
            return CRT_T.fOther+' '+ (++counters.other) + (ext && ext.length<=5 ? ' (.'+ext+')' : '');
        };
        list.innerHTML=files.map((f,i)=>{
            const isV=f.type.startsWith('video/');
            const isA=f.type.startsWith('audio/');
            const sz=(f.size/1024/1024).toFixed(2)+' MB';
            const camBadge = f.name.startsWith('camera-') ? '<span class="text-[10px] bg-[#eef4f0] text-[#0e6a38] px-1.5 py-0.5 rounded-full font-bold">'+escAttr(CRT_T.camBadge)+'</span>' : '';
            const recBadge = f.name.startsWith('recording-') ? '<span class="text-[10px] bg-red-50 text-red-600 px-1.5 py-0.5 rounded-full font-bold">'+escAttr(CRT_T.recBadge)+'</span>' : '';
            return `<div class="flex items-center gap-2.5 p-2.5 bg-white border border-[#e6e9e1] rounded-lg text-sm group">
                <span class="text-sm">${isA?'🎤':isV?'🎬':'🖼️'}</span>
                <div class="flex-1 min-w-0 text-right">
                    <div class="font-bold text-ink-700 truncate flex items-center gap-1.5" title="${escAttr(f.name)}">${friendlyName(f)} ${camBadge} ${recBadge}</div>
                    <div class="text-xs text-ink-400">${sz} • ${f.type||'—'}</div>
                </div>
                <button type="button" data-remove="${i}" class="shrink-0 w-7 h-7 rounded-lg hover:bg-red-50 text-ink-300 hover:text-red-500 flex items-center justify-center transition" title="${escAttr(CRT_T.delTitle)}">
                    <x-phosphor-x-duotone class="w-4 h-4" />
                </button>
            </div>`;
        }).join('') + (files.length>MAX_FILES?'<p class="text-xs font-bold text-red-500">'+escAttr(crtFill(CRT_T.limitMax,{':max':MAX_FILES}))+'</p>':'');

        list.querySelectorAll('[data-remove]').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                const idx=parseInt(btn.dataset.remove);
                const removedFile=Array.from(fileTransfer.files)[idx];
                const dt=new DataTransfer();
                Array.from(fileTransfer.files).forEach((file,j)=>{ if(j!==idx) dt.items.add(file); });
                fileTransfer=dt; intendedFilesCount=Math.max(0, intendedFilesCount-1);

                if(removedFile) pendingFiles=pendingFiles.filter(f=>f!==removedFile);
                else pendingFiles.splice(idx,1);
                syncInput(); renderFiles();
            });
        });
    }
    async function addFiles(newFiles){
        for(let orig of newFiles){
            if(fileTransfer.files.length>=MAX_FILES){ window.toast(crtFill(CRT_T.limitToast,{':max':MAX_FILES})); break; }

            let file=await compressImageClient(orig);
            const ext=file.name.split('.').pop().toLowerCase();
            const audioExts=['mp3','wav','ogg','oga','m4a','aac','wma','flac','opus','aiff','aif','amr','3ga','awb','mid','midi','au','weba','ac3','dts','alac'];
            const isAudio=audioExts.includes(ext)||file.type.startsWith('audio/');
            if(!['jpg','jpeg','png','webp','mp4','webm','mov','avi','3gp','mkv','m4v','mpg','3gpp'].includes(ext) && !file.type.startsWith('image/') && !file.type.startsWith('video/') && !isAudio){
                window.toast(CRT_T.unsupported); continue;
            }
            if(file.type.startsWith('image/') && file.size>20*1024*1024){ window.toast(CRT_T.imgBig); continue; }
            if(file.type.startsWith('video/') && file.size>100*1024*1024){ window.toast(CRT_T.vidBig); continue; }
            if(isAudio && file.size>100*1024*1024){ window.toast(CRT_T.audBig); continue; }
            fileTransfer.items.add(file);
            intendedFilesCount++;
            pendingFiles.push(file);
            if(fileTransfer.files.length < pendingFiles.length){
                console.warn('[UPLOAD] DataTransfer dropped a file — pendingFiles is source of truth', {dt: fileTransfer.files.length, pending: pendingFiles.length, name: file.name});
            }
        }
        syncInput(); renderFiles();
    }
    if(input&&zone&&list){
        input.addEventListener('change', async e=>{
            await addFiles(Array.from(e.target.files));
            input.value='';
            syncInput();
        });
        ['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.add('border-[#0e6a38]','bg-[#eef4f0]');}));
        ['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.remove('border-[#0e6a38]','bg-[#eef4f0]');}));
        zone.addEventListener('drop',async e=>{
            e.preventDefault();
            if(e.dataTransfer?.files?.length) await addFiles(Array.from(e.dataTransfer.files));
        });
    }


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
            cameraError.textContent=crtFill(CRT_T.camHttps,{':host':location.host});
            cameraError.classList.remove('hidden');
            if(fallbackEl) fallbackEl.classList.remove('hidden');
            cameraPlaceholder.classList.add('hidden');
            return;
        }
        if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
            cameraError.textContent=CRT_T.camUnsupported;
            cameraError.classList.remove('hidden');
            if(fallbackEl) fallbackEl.classList.remove('hidden');
            return;
        }
        try{
            if(currentStream) currentStream.getTracks().forEach(t=>t.stop());
            cameraError.classList.add('hidden');
            cameraPlaceholder.querySelector('p').textContent=CRT_T.camStarting;
            const stream=await navigator.mediaDevices.getUserMedia({
                video:{ facingMode: facingMode, width:{ ideal: 1280 }, height:{ ideal: 720 } },
                audio:true
            });
            currentStream=stream;
            cameraVideo.srcObject=stream;
            cameraVideo.classList.remove('hidden');
            cameraPlaceholder.classList.add('hidden');
            await cameraVideo.play();
            modeLabel.textContent=CRT_T.camReady;
        }catch(err){
            console.error(err);
            cameraError.textContent=CRT_T.camFallbackErr;
            cameraError.classList.remove('hidden');
            cameraPlaceholder.querySelector('p').textContent=CRT_T.camUnavailable;
        }
    }
    window.handleFallbackFile = function(input){
        if(input.files && input.files[0]){
            addFiles([input.files[0]]);
            showStatus(CRT_T.fileAdded, true);
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
        cameraPlaceholder.querySelector('p').textContent=CRT_T.camStarting;
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

        startBtn.classList.remove('hidden');
        stopBtn.classList.add('hidden');
        cameraTimer.classList.add('hidden');
        if(timerInterval){ clearInterval(timerInterval); timerInterval=null; }
        modeLabel.textContent=CRT_T.camPhoto;
    }
    function switchCamera(){
        facingMode = facingMode==='environment' ? 'user' : 'environment';
        startCamera();
    }
    function capturePhoto(){
        if(!currentStream || !cameraVideo.videoWidth){
            showStatus(CRT_T.camNotReady, false); return;
        }
        const canvas=cameraCanvas;
        canvas.width=cameraVideo.videoWidth;
        canvas.height=cameraVideo.videoHeight;
        const ctx=canvas.getContext('2d');

        if(facingMode==='user'){ ctx.scale(-1,1); ctx.drawImage(cameraVideo, -canvas.width, 0, canvas.width, canvas.height); }
        else ctx.drawImage(cameraVideo, 0, 0);
        canvas.toBlob(blob=>{
            if(!blob){ showStatus(CRT_T.captureFail, false); return; }
            const file=new File([blob], `camera-${Date.now()}.jpg`, { type:'image/jpeg' });
            if(fileTransfer.files.length>=MAX_FILES){ showStatus(crtFill(CRT_T.limitToast,{':max':MAX_FILES}), false); return; }
            addFiles([file]);
            showStatus(CRT_T.photoDone, true);

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
        if(!currentStream){ showStatus(CRT_T.camNotReady, false); return; }
        if(!window.MediaRecorder){ showStatus(CRT_T.recUnsupported, false); return; }
        recordedChunks=[];
        let options={ mimeType:'video/webm;codecs=vp9' };
        if(!MediaRecorder.isTypeSupported(options.mimeType)) options={ mimeType:'video/webm' };
        if(!MediaRecorder.isTypeSupported(options.mimeType)) options={};
        try{
            mediaRecorder=new MediaRecorder(currentStream, options);
        }catch(e){ showStatus(crtFill(CRT_T.recStartFail,{':message':e.message}), false); return; }
        mediaRecorder.ondataavailable=e=>{ if(e.data.size>0) recordedChunks.push(e.data); };
        mediaRecorder.onstop=()=>{
            const blob=new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
            const ext = blob.type.includes('mp4') ? 'mp4' : 'webm';
            const file=new File([blob], `camera-video-${Date.now()}.${ext}`, { type: blob.type });
            if(file.size>500*1024*1024){ showStatus(CRT_T.vidBig, false); return; }
            addFiles([file]);
            showStatus(crtFill(CRT_T.vidRecorded,{':size':(file.size/1024/1024).toFixed(1)}), true);
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
        modeLabel.textContent=CRT_T.camRec;
        showStatus(CRT_T.recStarted, true);
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
            modeLabel.textContent=CRT_T.camPhoto;
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


    const formEl = document.getElementById('create-form');
    const formErrorsEl = document.getElementById('form-errors');
    let submitActionVal = {{ auth()->user()->isReportWriter() ? "'save'" : "'send'" }};
    formEl?.querySelectorAll('button[type="submit"][name="action"]').forEach(btn=>{
        btn.addEventListener('click', ()=>{ submitActionVal = btn.value; });
    });
    formEl?.addEventListener('submit', async (e)=>{

        try{ if(window._syncObservedCreate) window._syncObservedCreate(); else if(typeof sync==='function') sync(); }catch(_){}
        const floorEl=document.getElementById('floor_number');
        const camEl=document.getElementById('camera_number');
        const descEl=document.getElementById('description');
        const hiddenEl=document.getElementById('observed_at');
        let clientErrors=[];
        if(!floorEl.value) clientErrors.push(FORM_T.floor);
        if(!camEl.value) clientErrors.push(FORM_T.camera);
        if(!hiddenEl.value) clientErrors.push(FORM_T.datetime);
        if(!descEl.value.trim()) clientErrors.push(FORM_T.description);

        try{
            const obs=document.getElementById('observed_at')?.value;
            const obsEnd=document.getElementById('observed_end_at')?.value;
            if(obs && obsEnd && new Date(obsEnd) < new Date(obs)){

                const d=new Date(obsEnd); d.setDate(d.getDate()+1);
                const pad=n=>String(n).padStart(2,'0');
                document.getElementById('observed_end_at').value=d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
            }
        }catch(_){}
        if(clientErrors.length){
            e.preventDefault();
            formErrorsEl.innerHTML='<div class="font-bold mb-1">'+FORM_T.fixFields+'</div><ul class="list-disc list-inside space-y-1">'+clientErrors.map(m=>`<li>${m}</li>`).join('')+'</ul>';
            formErrorsEl.classList.remove('hidden');
            formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
            return;
        }
        if(fileTransfer.files.length>0 || recordedAudioBlob || intendedFilesCount>0){
            e.preventDefault();
            formErrorsEl.classList.add('hidden');
            const submitBtn=e.submitter || document.activeElement;
            if(submitBtn) submitBtn.disabled=true;
            const fd=new FormData(formEl);
            fd.delete('files[]');

            const filesToSend = pendingFiles.length > 0 ? pendingFiles.slice()
                : (fileTransfer.files.length > 0 ? Array.from(fileTransfer.files) : Array.from(input.files));

            if(filesToSend.length < intendedFilesCount){
                formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">'+CRT_T.browserFail+'</div><p class="text-xs">'+crtFill(CRT_T.browserDetail,{':intended':intendedFilesCount,':prepared':filesToSend.length})+'</p>';
                formErrorsEl.classList.remove('hidden');
                formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
                if(submitBtn) submitBtn.disabled=false;
                return;
            }
            filesToSend.forEach(f=> fd.append('files[]', f));
            if(recordedAudioBlob){
                const ext=audioMimeToExt(recordedAudioBlob.type||'audio/webm');
                fd.append('files[]', recordedAudioBlob, 'recording-'+Date.now()+'.'+ext);
            }


            const clientFilesCount = intendedFilesCount + (recordedAudioBlob ? 1 : 0);
            fd.append('client_files_count', String(clientFilesCount));

            try{
                console.debug('[UPLOAD FORENSIC] intent vs transport', {
                    intended: intendedFilesCount,
                    audio: recordedAudioBlob ? 1 : 0,
                    announced: clientFilesCount,
                    fileTransfer: fileTransfer.files.length,
                    pending: pendingFiles.length,
                    inputFiles: input.files ? input.files.length : -1,
                    toSend: filesToSend.length,
                    fdFiles: Array.from(fd.entries()).filter(([k,v])=>k==='files[]'&&v instanceof File).map(([k,v])=>({name:v.name,size:v.size,type:v.type}))
                });
            }catch(_){}
            fd.set('action', submitActionVal);

            const totalBytesCreate = filesToSend.reduce((s,f)=>s+f.size,0) + (recordedAudioBlob?recordedAudioBlob.size:0);
            if(totalBytesCreate > 120*1024*1024){
                formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">'+CRT_T.totalBig+'</div><p class="text-xs">'+crtFill(CRT_T.serverLimit,{':size':(totalBytesCreate/1024/1024).toFixed(1)})+'</p>';
                formErrorsEl.classList.remove('hidden');
                formErrorsEl.scrollIntoView({behavior:'smooth',block:'center'});
                if(submitBtn) submitBtn.disabled=false;
                hideUploadProgress();
                return;
            }
            try{
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const formActionUrl = formEl.getAttribute('action');
                console.debug('[CREATE SUBMIT] action='+formActionUrl+' method=POST filesToSend='+filesToSend.length+' clientCount='+clientFilesCount);
                setUploadProgress(5, crtFill(CRT_T.uploadingN,{':count':filesToSend.length}));
                document.getElementById('upload-progress')?.scrollIntoView({behavior:'smooth',block:'center'});
                const res = await xhrUpload(formActionUrl, fd, csrfToken, (pct, loaded, total)=> setUploadProgress(Math.max(5, Math.min(95, pct)), pct+'%'+ (loaded ? ' ('+(loaded/1024/1024).toFixed(1)+' / '+(total/1024/1024).toFixed(1)+' MB)' : '') +' '+CRT_T.dontClose));
                setUploadProgress(98, CRT_T.verify);
                let data=res.data, rawText=res.raw;
                const failLoud = (title, errs) => {
                    const list=(errs&&errs.length?errs:[CRT_T.attachFail]).map(e=> typeof e==='string'?e:((e.file?e.file+': ':'')+(e.message||JSON.stringify(e)))).join('<br>');

                    formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">'+title+'</div><p class="text-xs">'+list+'</p><p class="mt-2 text-xs font-bold">'+CRT_T.filesKept+'</p>';
                    formErrorsEl.classList.remove('hidden');
                    formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
                    if(submitBtn) submitBtn.disabled=false;
                };
                if(data && data.success === false){
                    failLoud(CRT_T.attachFail, data.attachment_errors);
                    return;
                }
                if(data && typeof data.files_received==='number' && typeof data.attachments_saved==='number'){
                    if(data.files_received !== data.attachments_saved){
                        failLoud(CRT_T.attachFail, data.attachment_errors || [CRT_T.mismatchShort]);
                        return;
                    }
                    if((data.attachment_errors||[]).length>0){
                        failLoud(CRT_T.attachFail, data.attachment_errors);
                        return;
                    }
                    if(data.files_received < clientFilesCount){
                        failLoud(CRT_T.attachFail, [crtFill(CRT_T.partial,{':sent':clientFilesCount,':received':data.files_received})]);
                        return;
                    }
                }
                if(res.ok && data && data.success !== false){
                    if(recordedAudioUrl) URL.revokeObjectURL(recordedAudioUrl);
                    recordedAudioBlob=null; recordedAudioUrl=null;
                    if(data && data.redirect) window.location.href=data.redirect;
                    else window.location.href="{{ route('notes.index', [], false) }}";
                    return;
                }
                if(res.status===422){

                    if(data && (data.attachment_errors || data.success === false)){
                        failLoud(CRT_T.attachFail, data.attachment_errors);
                        return;
                    }
                    const errors=(data&&data.errors)||{};
                    let html='<div class="font-bold mb-1">'+FORM_T.fixFields+'</div><ul class="list-disc list-inside space-y-1">';
                    for(const [field,msgs] of Object.entries(errors)){
                        for(const msg of msgs) html+=`<li>${msg}</li>`;
                    }
                    html+='</ul><p class="mt-2 text-xs font-bold text-[#0e6a38]">'+CRT_T.mediaKept+'</p>';
                    formErrorsEl.innerHTML=html;
                    formErrorsEl.classList.remove('hidden');
                    formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
                } else {
                    let diag='';
                    try{
                        const bodySnippet=(rawText||'').substring(0,800).replace(/</g,'&lt;');
                        console.error('[CREATE FORENSIC]', {status:res.status, body:rawText});
                        diag='<div class="mt-2 p-2 bg-white border border-red-200 rounded text-[11px] text-left dir-ltr break-all">'
                            +'<div>URL: '+formActionUrl+'</div>'
                            +'<div>Method: POST</div>'
                            +(bodySnippet?'<div class="mt-1">Body: '+bodySnippet+'</div>':'')
                            +'</div>';
                    }catch(_){}
                    formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">'+crtFill(CRT_T.sendFail,{':code':res.status})+'</div><p class="text-xs">'+CRT_T.notSaved+'</p>'+diag;
                    formErrorsEl.classList.remove('hidden');
                    formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
                }
            }catch(err){
                hideUploadProgress();
                if(err.name === 'AbortError'){
                    formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">'+CRT_T.timeoutTitle+'</div><p class="text-xs">'+CRT_T.timeoutHint+'</p>';
                } else {
                    formErrorsEl.innerHTML='<div class="font-bold mb-1 text-red-600">'+CRT_T.netErr+'</div><p class="text-xs">'+err.message+'</p>';
                }
                formErrorsEl.classList.remove('hidden');
                formErrorsEl.scrollIntoView({behavior:'smooth', block:'center'});
            }finally{
                hideUploadProgress();
                if(submitBtn) submitBtn.disabled=false;
            }
        }
    });


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
        if(audioPreviewIcon) audioPreviewIcon.innerHTML='<x-phosphor-microphone-duotone class="w-5 h-5 text-red-500 animate-pulse" />';
    }
    function showAudioRecorded(){
        if(audioPreviewIcon) audioPreviewIcon.innerHTML='<x-phosphor-music-note-duotone class="w-5 h-5 text-[#0e6a38]" />';
        if(audioPreviewStatus) audioPreviewStatus.textContent=CRT_T.audioDone;
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
        audioRecordBtn.innerHTML='<x-phosphor-microphone-duotone class="w-4 h-4" />'+CRT_T.audioBtn;
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
                    showAudioPreview(CRT_T.micReq, false);
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
                        audioRecordBtn.innerHTML='<x-phosphor-x-duotone class="w-4 h-4" />'+CRT_T.delRec;
                    };
                    audioRecorder.start();
                    audioStartTime=Date.now();
                    audioRecordBtn.classList.remove('bg-[#fdfcfa]','border-[#e6e9e1]','text-[#1a2e1f]');
                    audioRecordBtn.classList.add('bg-red-50','border-red-300','text-red-600');
                    audioRecordBtn.innerHTML='<span class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></span><span id="audio-timer">00:00</span> '+CRT_T.stopLbl;
                    if(audioPreviewStatus) audioPreviewStatus.textContent=CRT_T.recNow;
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

    // بلوك الجوال: ملخص حي + تظليل القسم الحالي — للجوال فقط، لا يمس منطق الحفظ
    (function(){
        const loc = document.getElementById('m-sum-loc');
        const tim = document.getElementById('m-sum-time');
        const dsc = document.getElementById('m-sum-desc');
        if(!loc && !tim && !dsc) return;
        const f = document.getElementById('floor_number');
        const c = document.getElementById('camera_number');
        const d = document.getElementById('observed_date');
        const t = document.getElementById('observed_time');
        const ta = document.getElementById('description');
        function upd(){
            try{
                const fv = (f?.value || '').trim(), cv = (c?.value || '').trim();
                if(loc) loc.textContent = (fv || cv) ? ('ط ' + (fv || '—') + ' · ك ' + (cv || '—')) : '—';
                const dv = (d?.value || '').slice(5), tv = (t?.value || '');
                if(tim) tim.textContent = tv ? ((dv ? dv + ' ' : '') + tv) : '—';
                if(dsc) dsc.textContent = String((ta?.value || '').length);
            }catch(_){}
        }
        [f,c,d,t,ta].forEach(el=>{ el?.addEventListener('input', upd); el?.addEventListener('change', upd); });
        upd();
        // تظليل زر القسم المرئي
        try{
            const chips = Array.from(document.querySelectorAll('[data-mnav]'));
            if(!('IntersectionObserver' in window) || !chips.length) return;
            const on = 'bg-[#0e6a38]', onTx = 'text-white', onBd = 'border-[#0e6a38]';
            const off = ['bg-white','dark:bg-[#2a302b]','text-ink-600','dark:text-[#9bb0a0]','border-[#e6e9e1]','dark:border-[#343a34]'];
            const setActive = (id)=>{
                chips.forEach(ch=>{
                    const active = ch.getAttribute('data-mnav') === id;
                    ch.classList.toggle(on, active);
                    ch.classList.toggle(onTx, active);
                    ch.classList.toggle(onBd, active);
                    ch.classList.toggle('font-extrabold', active);
                });
            };
            const io = new IntersectionObserver((entries)=>{
                entries.forEach(en=>{ if(en.isIntersecting) setActive(en.target.id); });
            }, { rootMargin: '-40% 0px -50% 0px', threshold: 0 });
            ['sec-loc','sec-time','sec-desc','sec-files'].forEach(id=>{
                const s = document.getElementById(id);
                if(s) io.observe(s);
            });
        }catch(_){}
    })();
</script>
@endpush
@endsection
