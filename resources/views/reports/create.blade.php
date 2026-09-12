@extends('layouts.app')

@section('content')
<div class="max-w-[560px] mx-auto w-full">
    {{-- header — quiet, no decorative card --}}
    <div class="flex items-center gap-2.5">
        <a href="{{ route('reports.index') }}" aria-label="{{ __('ui.back_to_reports_aria') }}" class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e6e9e1] bg-white text-[#6b7a6e] hover:text-[#0e6a38] hover:border-[#0e6a38] transition">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-[15.5px] font-extrabold text-[#1a2e1f] leading-tight tracking-tight">{{ __('ui.new_report_title') }}</h1>
            <p class="text-[11.5px] text-[#9aa99a] leading-4 mt-0.5">{{ __('ui.new_report_subtitle') }}</p>
        </div>
    </div>

    <div class="mt-5 h-px bg-[#eceee9]"></div>

    <form method="POST" action="{{ route('reports.store') }}" id="report-create" class="mt-5 grid gap-6" novalidate data-translations='{"daily_report_prefix":@json(__("ui.daily_report")),"creating_draft":@json(__("ui.creating_draft")),"review_fields":@json(__("ui.review_fields_alert")),"title_min":@json(__("ui.title_min_error")),"date_invalid":@json(__("ui.date_invalid_error"))}'>
        @csrf
        @if($errors->has('general'))<div class="text-[13px] font-bold text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded-xl px-3.5 py-2.5">{{ $errors->first('general') }}</div>@endif
        <div id="form-alert" class="hidden text-[13px] font-bold text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded-xl px-3.5 py-2.5"></div>

        <div class="grid gap-2">
            <label for="report-title" class="text-[12.5px] font-bold text-[#1a2e1f]">{{ __('ui.report_title_label') }}</label>
            <input id="report-title" name="title" maxlength="255" value="{{ old('title') }}" placeholder="{{ __('ui.auto_title_placeholder') }}" class="w-full min-h-[42px] text-[13.5px] bg-white border border-[#e6e9e1] rounded-xl px-3.5 py-2.5 text-[#1a2e1f] placeholder:text-[#b0bab2] focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition">
            <p class="text-[11.5px] leading-5 text-[#9aa99a]">{{ __('ui.report_title_hint') }}</p>
            <span class="field-error hidden text-[11.5px] font-bold text-[#b91c1c]">{{ __('ui.title_min_error') }}</span>
            @error('title')<span class="text-[11.5px] font-bold text-[#b91c1c]">{{ $message }}</span>@enderror
        </div>

        <div class="grid gap-2">
            <label for="report-date" class="text-[12.5px] font-bold text-[#1a2e1f]">{{ __('ui.report_date_label') }}</label>
            <input id="report-date" type="date" name="report_date" required max="{{ date('Y-m-d') }}" value="{{ old('report_date', date('Y-m-d')) }}" class="w-full min-h-[42px] text-[13.5px] bg-white border border-[#e6e9e1] rounded-xl px-3.5 py-2.5 text-[#1a2e1f] focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition">
            <span class="field-error hidden text-[11.5px] font-bold text-[#b91c1c]">{{ __('ui.date_invalid_error') }}</span>
            @error('report_date')<span class="text-[11.5px] font-bold text-[#b91c1c]">{{ $message }}</span>@enderror
        </div>

        {{-- visibility — inline row, not a card --}}
        <div class="flex items-center justify-between gap-4 py-3 border-y border-[#eceee9]">
            <div class="min-w-0">
                <div class="text-[13px] font-bold text-[#1a2e1f] leading-5">{{ __('ui.visible_to_monitors') }}</div>
                <p class="text-[11.5px] leading-4 text-[#9aa99a] mt-0.5">{{ __('ui.visible_to_monitors_hint') }}</p>
            </div>
            <span class="r-switch"><input type="checkbox" name="visible_to_monitors" value="1" @checked(old('visible_to_monitors', true)) aria-label="{{ __('ui.visible_to_monitors') }}"><span class="track"></span></span>
        </div>

        <div class="flex items-start gap-2.5 px-3 py-3 bg-[#f6f7f5] border border-[#eceee9] border-r-2 border-r-[#0e6a38] rounded-xl">
            <svg class="w-4 h-4 mt-0.5 shrink-0 text-[#6b7a6e]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-[12.5px] leading-6 text-[#4a5a4f]">{{ __('ui.auto_attach_hint') }}</p>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-2.5 pt-1">
            <a href="{{ route('reports.index') }}" class="inline-flex items-center justify-center px-5 min-h-[42px] rounded-xl border border-[#e6e9e1] bg-white text-[13px] font-bold text-[#6b7a6e] hover:border-[#0e6a38] hover:text-[#0e6a38] transition">{{ __('ui.cancel') }}</a>
            <button id="create-btn" class="w-full sm:w-auto sm:ms-auto inline-flex items-center justify-center px-7 min-h-[42px] rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-[13px] transition disabled:opacity-60 shadow-sm">{{ __('ui.create_draft_btn') }}</button>
        </div>
    </form>
</div>
<style>
    .r-switch { position:relative; display:inline-block; width:42px; height:24px; flex-shrink:0; }
    .r-switch input{ opacity:0; width:0; height:0; }
    .r-switch .track{ position:absolute; inset:0; border-radius:999px; background:#e6e9e1; transition:.2s; }
    .r-switch .track::before{ content:''; position:absolute; height:18px; width:18px; top:3px; right:3px; background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 2px rgba(0,0,0,.2); }
    .r-switch input:checked + .track{ background:#0e6a38; }
    .r-switch input:checked + .track::before{ transform:translateX(-18px); }
    .r-switch input:focus-visible + .track{ outline:2px solid #0e6a38; outline-offset:2px; }
    .invalid{ border-color:#ef4444 !important; box-shadow:0 0 0 2px rgba(239,68,68,.1) !important; }
</style>
<script>
(function () {
    const form = document.getElementById('report-create');
    if (!form) return;
    const translations = JSON.parse(form.dataset.translations || '{}');
    const title = form.querySelector('[name=title]');
    const date = form.querySelector('[name=report_date]');
    const btn = document.getElementById('create-btn');
    const alertBox = document.getElementById('form-alert');
    function localToday() {
        const d = new Date();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }
    date.max = localToday();
    function showAlert(msg) {
        alertBox.textContent = msg;
        alertBox.classList.remove('hidden');
    }
    [title, date].forEach(el => el?.addEventListener('input', () => {
        el.classList.remove('invalid');
        el.closest('div.grid')?.querySelector('.field-error')?.classList.add('hidden');
        alertBox.classList.add('hidden');
    }));
    form.addEventListener('submit', function (e) {
        if (title.value.trim() === '' && date.value) {
            title.value = (translations.daily_report_prefix || @json(__('ui.daily_report'))) + ' — ' + date.value;
        }
        const badTitle = title.value.trim().length > 0 && title.value.trim().length < 3;
        const badDate = !date.value || date.value > localToday();
        title.classList.toggle('invalid', badTitle);
        date.classList.toggle('invalid', badDate);
        title.closest('div.grid').querySelector('.field-error').classList.toggle('hidden', !badTitle);
        date.closest('div.grid').querySelector('.field-error').classList.toggle('hidden', !badDate);
        if (badTitle || badDate) {
            e.preventDefault();
            showAlert(translations.review_fields || @json(__('ui.review_fields_alert')));
            return;
        }
        btn.disabled = true;
        btn.textContent = translations.creating_draft || @json(__('ui.creating_draft'));
    });
})();
</script>
@endsection
