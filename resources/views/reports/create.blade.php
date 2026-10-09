@extends('layouts.app')

@section('content')
<div class="max-w-[560px] mx-auto w-full px-1 sm:px-0">
    <div class="flex items-center gap-2.5">
        <a href="{{ route('reports.index') }}" aria-label="{{ __('ui.back_to_reports_aria') }}" class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e6e9e1] bg-white text-[#6b7a6e] hover:text-[#0e6a38] hover:border-[#0e6a38] transition">
            <x-phosphor-caret-right-duotone class="w-4 h-4 rtl:rotate-180" />
        </a>
        <div class="min-w-0">
            <h1 class="text-[15.5px] font-extrabold text-[#1a2e1f] leading-tight tracking-tight">{{ __('ui.new_report_title') }}</h1>
            <p class="text-[11.5px] text-[#9aa99a] leading-4 mt-0.5">{{ __('ui.new_report_subtitle') }}</p>
        </div>
    </div>

    <div class="mt-4 sm:mt-5 h-px bg-[#eceee9]"></div>

    <form method="POST" action="{{ route('reports.store') }}" id="report-create" class="mt-4 sm:mt-5" novalidate data-translations='{"daily_report_prefix":@json(__("ui.daily_report")),"creating_draft":@json(__("ui.creating_draft")),"review_fields":@json(__("ui.review_fields_alert")),"title_min":@json(__("ui.title_min_error")),"date_invalid":@json(__("ui.date_invalid_error")),"status_loading":@json(__("ui.report_day_status_loading")),"status_error":@json(__("ui.report_day_status_error")),"status_pending":@json(__("ui.report_day_status_pending")),"status_ready":@json(__("ui.report_day_status_ready")),"status_exists":@json(__("ui.report_day_status_exists")),"status_empty":@json(__("ui.report_day_status_empty")),"suggestion_ready":@json(__("ui.ai_suggestion_ready")),"suggestion_pending":@json(__("ui.ai_suggestion_pending"))}'>
        @csrf
        <div id="ai-preview-note-ids">
            @foreach(old('ai_preview_note_ids', []) as $previewNoteId)
                <input type="hidden" name="ai_preview_note_ids[]" value="{{ $previewNoteId }}">
            @endforeach
        </div>
        @if($errors->has('general'))<div class="text-[13px] font-bold text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded-xl px-3.5 py-2.5 mb-4">{{ $errors->first('general') }}</div>@endif
        <div id="form-alert" class="hidden text-[13px] font-bold text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded-xl px-3.5 py-2.5 mb-4"></div>

        <div class="bg-white border border-[#e6e9e1] rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 grid gap-5 sm:gap-6">
                <div class="grid gap-2">
                    <label for="report-title" class="text-[12.5px] font-bold text-[#1a2e1f] tracking-tight">{{ __('ui.report_title_label') }}</label>
                    <input id="report-title" name="title" maxlength="255" value="{{ old('title') }}" placeholder="{{ __('ui.auto_title_placeholder') }}" class="w-full min-h-[44px] text-[16px] sm:text-[13.5px] bg-[#fdfcfa] border border-[#e6e9e1] rounded-xl px-3.5 py-2.5 text-[#1a2e1f] placeholder:text-[#b0bab2] focus:bg-white focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition">
                    <p class="text-[11.5px] leading-5 text-[#9aa99a]">{{ __('ui.report_title_hint') }}</p>
                    <span class="field-error hidden text-[11.5px] font-bold text-[#b91c1c]">{{ __('ui.title_min_error') }}</span>
                    @error('title')<span class="text-[11.5px] font-bold text-[#b91c1c]">{{ $message }}</span>@enderror
                </div>

                <div class="grid gap-2">
                    <label for="report-date" class="text-[12.5px] font-bold text-[#1a2e1f] tracking-tight">{{ __('ui.report_date_label') }}</label>
                    <input id="report-date" type="date" name="report_date" required max="{{ date('Y-m-d') }}" value="{{ old('report_date', $dailyOverview['date']) }}" class="w-full min-h-[44px] text-[16px] sm:text-[13.5px] bg-[#fdfcfa] border border-[#e6e9e1] rounded-xl px-3.5 py-2.5 text-[#1a2e1f] focus:bg-white focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition">
                    <span class="field-error hidden text-[11.5px] font-bold text-[#b91c1c]">{{ __('ui.date_invalid_error') }}</span>
                    @error('report_date')<span class="text-[11.5px] font-bold text-[#b91c1c]">{{ $message }}</span>@enderror
                    <div id="daily-status" data-url="{{ route('reports.daily-status') }}" data-overview='@json($dailyOverview)' role="status" aria-live="polite" class="flex items-start gap-2.5 p-3 rounded-xl border border-[#e6e9e1] bg-[#f6f7f5]">
                        <x-phosphor-info-duotone id="daily-status-icon" class="w-4 h-4 text-[#0e6a38] shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p id="daily-status-text" class="text-[12px] leading-5 text-[#4a5a4f]">{{ __('ui.report_day_status_loading') }}</p>
                            <a id="daily-review-link" data-base-url="{{ route('notes.index') }}" href="{{ route('notes.index', ['status' => 'pending', 'date' => $dailyOverview['date']]) }}" class="hidden mt-1 inline-flex text-[11.5px] font-bold text-[#0e6a38] underline underline-offset-2">{{ __('ui.review_notes') }}</a>
                        </div>
                    </div>
                </div>

                <div class="grid gap-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label for="report-content" class="text-[12.5px] font-bold text-[#1a2e1f] tracking-tight">{{ __('ui.content_optional') }}</label>
                        <button type="button" id="ai-preview-btn" data-url="{{ route('reports.ai-preview') }}" @disabled(!($aiConfigured ?? false)) class="inline-flex items-center justify-center gap-1.5 min-h-9 px-3 rounded-lg border border-[#cde7d6] bg-[#e8f3ec] text-[11.5px] font-bold text-[#0e6a38] hover:bg-[#dceee2] disabled:opacity-50 disabled:cursor-not-allowed transition" title="{{ ($aiConfigured ?? false) ? __('ui.ai_generate_suggestion') : __('ui.ai_unavailable') }}">
                            <x-phosphor-sparkle-duotone class="w-4 h-4" />
                            <span id="ai-preview-label">{{ __('ui.ai_generate_suggestion') }}</span>
                        </button>
                    </div>
                    <textarea id="report-content" name="content" rows="7" maxlength="20000" placeholder="{{ __('ui.content_optional') }}" class="w-full resize-y min-h-[150px] text-[14px] leading-7 bg-[#fdfcfa] border border-[#e6e9e1] rounded-xl px-3.5 py-3 text-[#1a2e1f] placeholder:text-[#b0bab2] focus:bg-white focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition">{{ old('content') }}</textarea>
                    @error('content')<span class="text-[11.5px] font-bold text-[#b91c1c]">{{ $message }}</span>@enderror
                    <p id="ai-preview-status" class="hidden text-[11.5px] leading-5 text-[#0e6a38]" role="status" aria-live="polite"></p>
                    @if(!($aiConfigured ?? false))<p class="text-[11.5px] text-[#9aa99a]">{{ __('ui.ai_unavailable') }}</p>@endif
                </div>

                <div class="flex items-start gap-3 px-3.5 py-3 bg-[#f6f7f5] border border-[#eceee9] rounded-xl">
                    <span class="w-7 h-7 rounded-lg bg-white border border-[#e6e9e1] flex items-center justify-center shrink-0 mt-0.5">
                        <x-phosphor-info-duotone class="w-4 h-4 text-[#0e6a38]" />
                    </span>
                    <p class="text-[12.5px] leading-[1.7] sm:leading-6 text-[#4a5a4f]">{{ __('ui.auto_attach_hint') }}</p>
                </div>
            </div>
            <div class="m-crud-bar flex flex-col-reverse sm:flex-row sm:items-center gap-2.5 p-3 sm:p-4 bg-[#f9faf8] border-t border-[#eceee9]">
                <a href="{{ route('reports.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-5 min-h-[44px] rounded-xl border border-[#e6e9e1] bg-white text-[13px] font-bold text-[#6b7a6e] hover:border-[#0e6a38] hover:text-[#0e6a38] hover:bg-white transition">{{ __('ui.cancel') }}</a>
                <button id="create-btn" @disabled(!$dailyOverview['can_create']) class="w-full sm:w-auto sm:ms-auto inline-flex items-center justify-center gap-2 px-7 min-h-[44px] rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-[13px] transition disabled:opacity-60 shadow-sm hover:shadow-md">{{ __('ui.create_draft_btn') }} <x-phosphor-arrow-right-duotone class="w-3.5 h-3.5 hidden sm:block rtl:rotate-180" /></button>
            </div>
        </div>
    </form>
</div>
<style>
    .invalid{ border-color:#ef4444 !important; box-shadow:0 0 0 2px rgba(239,68,68,.1) !important; }

    html.dark .bg-white{ background:#232926 !important; }
    html.dark .bg-\[\#fdfcfa\]{ background:#2a302b !important; border-color:#333b34 !important; }
    html.dark .bg-\[\#f9faf8\]{ background:#1e2320 !important; border-color:#333b34 !important; }
    html.dark .bg-\[\#f6f7f5\]{ background:#2a302b !important; border-color:#333b34 !important; }
    html.dark .border-\[\#e6e9e1\]{ border-color:#333b34 !important; }
    html.dark .border-\[\#eceee9\]{ border-color:#333b34 !important; }
    html.dark .text-\[\#1a2e1f\]{ color:#e7ece5 !important; }
    html.dark .text-\[\#9aa99a\]{ color:#8a9a8a !important; }
    html.dark .text-\[\#6b7a6e\]{ color:#9bb0a0 !important; }
    html.dark .text-\[\#4a5a4f\]{ color:#b9c6bb !important; }
    html.dark .placeholder\:\[text-\[\#b0bab2\]\]::placeholder{ color:#6a7a6a !important; }
    html.dark .hover\:bg-white:hover{ background:#2a302b !important; }
    html.dark .text-\[\#0e6a38\]{ color:#4ade80 !important; }
    html.dark .border-\[\#cde7d6\]{ border-color:#1e3d25 !important; }
    html.dark .bg-\[\#e8f3ec\]{ background:#1e3328 !important; border-color:#1e3d25 !important; color:#4ade80 !important; }
    html.dark .bg-\[\#fef2f2\]{ background:#2d1f1f !important; border-color:#3d2626 !important; color:#f08080 !important; }
    html.dark .border-\[\#fecaca\]{ border-color:#3d2626 !important; }
    html.dark .text-\[\#b91c1c\]{ color:#f08080 !important; }
    html.dark .hover\:border-\[\#0e6a38\]:hover{ border-color:#4ade80 !important; }
    html.dark .hover\:text-\[\#0e6a38\]:hover{ color:#4ade80 !important; }
    html.dark .focus\:border-\[\#0e6a38\]:focus{ border-color:#4ade80 !important; }
    html.dark .focus\:ring-\[\#0e6a38\]\/10:focus{ --tw-ring-color:rgba(74,222,128,.1) !important; }
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
    const aiBtn = document.getElementById('ai-preview-btn');
    const aiLabel = document.getElementById('ai-preview-label');
    const aiStatus = document.getElementById('ai-preview-status');
    const dailyStatus = document.getElementById('daily-status');
    const dailyStatusText = document.getElementById('daily-status-text');
    const reviewLink = document.getElementById('daily-review-link');
    let dailyOverview = JSON.parse(dailyStatus?.dataset.overview || '{}');
    let statusReady = Boolean(dailyOverview.date);
    let aiContentStale = false;

    function currentAiPreviewIds() {
        return Array.from(document.querySelectorAll('#ai-preview-note-ids input[name="ai_preview_note_ids[]"]'))
            .map(input => Number(input.value)).filter(Number.isFinite).sort((a, b) => a - b);
    }

    function setDailyStatus(kind, message) {
        if (!dailyStatus || !dailyStatusText) return;
        dailyStatusText.textContent = message;
        dailyStatus.className = 'flex items-start gap-2.5 p-3 rounded-xl border';
        dailyStatusText.classList.remove('text-[#9a3412]', 'text-[#315d3d]');
        if (kind === 'error' || kind === 'pending' || kind === 'exists') {
            dailyStatus.classList.add('border-[#fed7aa]', 'bg-[#fff7ed]');
            dailyStatusText.classList.add('text-[#9a3412]');
        } else {
            dailyStatus.classList.add('border-[#cde7d6]', 'bg-[#f0f7f2]');
            dailyStatusText.classList.add('text-[#315d3d]');
        }
        if (reviewLink) {
            reviewLink.classList.toggle('hidden', kind !== 'pending');
            const url = new URL(reviewLink.dataset.baseUrl, window.location.origin);
            url.searchParams.set('status', 'pending');
            url.searchParams.set('date', date.value);
            reviewLink.href = url.toString();
        }
    }

    function applyOverview(data) {
        dailyOverview = data || {};
        statusReady = true;
        if (dailyOverview.report_exists) {
            setDailyStatus('exists', translations.status_exists);
        } else if (Number(dailyOverview.pending_count) > 0) {
            setDailyStatus('pending', translations.status_pending.replace(':n', String(dailyOverview.pending_count)));
        } else if (Number(dailyOverview.accepted_count) > 0) {
            setDailyStatus('ready', translations.status_ready.replace(':n', String(dailyOverview.accepted_count)));
        } else {
            setDailyStatus('empty', translations.status_empty);
        }
        const previewIds = currentAiPreviewIds();
        const acceptedIds = (dailyOverview.accepted_note_ids || []).map(Number).sort((a, b) => a - b);
        const idsMatch = previewIds.length === acceptedIds.length && previewIds.every((id, index) => id === acceptedIds[index]);
        if (previewIds.length > 0 && Number(dailyOverview.pending_count) === 0 && !idsMatch) {
            aiContentStale = true;
            aiStatus.textContent = @json(__('ui.ai_suggestion_stale'));
            aiStatus.classList.remove('hidden', 'text-[#0e6a38]');
            aiStatus.classList.add('text-[#b91c1c]');
        }
        btn.disabled = !dailyOverview.can_create || aiContentStale || form.dataset.creating === '1';
        if (aiBtn && form.dataset.previewing !== '1') aiBtn.disabled = !@json($aiConfigured ?? false) || Boolean(dailyOverview.report_exists);
    }

    async function refreshDailyStatus() {
        statusReady = false;
        btn.disabled = true;
        if (aiBtn) aiBtn.disabled = true;
        setDailyStatus('loading', translations.status_loading);
        const url = new URL(dailyStatus.dataset.url, window.location.origin);
        url.searchParams.set('report_date', date.value);
        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success || !result.data) throw new Error(result.message || translations.status_error);
            applyOverview(result.data);
        } catch (error) {
            statusReady = false;
            dailyOverview = {};
            setDailyStatus('error', translations.status_error);
            btn.disabled = true;
            if (aiBtn) aiBtn.disabled = true;
        }
    }

    applyOverview(dailyOverview);
    date.addEventListener('change', function () {
        if (!aiStatus.classList.contains('hidden')) {
            aiContentStale = true;
            document.getElementById('ai-preview-note-ids').replaceChildren();
            aiStatus.textContent = @json(__('ui.ai_suggestion_stale'));
            aiStatus.classList.remove('text-[#0e6a38]');
            aiStatus.classList.add('text-[#b91c1c]');
        }
        refreshDailyStatus();
    });
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
    aiBtn?.addEventListener('click', async function () {
        if (form.dataset.creating === '1') return;
        const content = document.getElementById('report-content');
        if (content.value.trim() && !window.confirm(@json(__('ui.ai_suggestion_overwrite')))) return;
        if (!date.value || date.value > localToday()) {
            showAlert(translations.date_invalid || @json(__('ui.date_invalid_error')));
            return;
        }
        if (title.value.trim() && title.value.trim().length < 3) {
            showAlert(translations.title_min || @json(__('ui.title_min_error')));
            return;
        }
        if (!statusReady || dailyOverview.report_exists) {
            showAlert(translations.status_error);
            return;
        }
        aiBtn.disabled = true;
        form.dataset.previewing = '1';
        btn.disabled = true;
        aiLabel.textContent = @json(__('ui.ai_generating_suggestion'));
        aiStatus.classList.add('hidden');
        aiStatus.classList.remove('text-[#b91c1c]');
        aiStatus.classList.add('text-[#0e6a38]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const controller = new AbortController();
        const killer = setTimeout(() => controller.abort(), 125000);
        try {
            const payload = { report_date: date.value };
            if (title.value.trim()) payload.title = title.value.trim();
            const response = await fetch(aiBtn.dataset.url, {
                method: 'POST', signal: controller.signal,
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success || !result.data?.content) throw new Error(result.message || @json(__('api.ai_invalid')));
            content.value = result.data.content;
            const noteIds = document.getElementById('ai-preview-note-ids');
            noteIds.replaceChildren();
            (result.data.note_ids || []).forEach(function (id) {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'ai_preview_note_ids[]'; input.value = String(id);
                noteIds.appendChild(input);
            });
            aiContentStale = false;
            if (!title.value.trim() && result.data.title && result.data.title.length <= 255) title.value = result.data.title;
            aiStatus.textContent = result.data.pending_count > 0
                ? translations.suggestion_pending.replace(':n', String(result.data.pending_count))
                : translations.suggestion_ready + ' (' + result.data.note_count + ')';
            aiStatus.classList.remove('hidden');
            content.focus();
            await refreshDailyStatus();
        } catch (error) {
            aiStatus.textContent = error?.name === 'AbortError' ? @json(__('api.ai_hint_retry_later')) : (error?.message || @json(__('api.ai_data_invalid')));
            aiStatus.classList.remove('hidden');
            aiStatus.classList.remove('text-[#0e6a38]');
            aiStatus.classList.add('text-[#b91c1c]');
        } finally {
            clearTimeout(killer);
            form.dataset.previewing = '0';
            if (form.dataset.creating !== '1') {
                aiBtn.disabled = !@json($aiConfigured ?? false) || Boolean(dailyOverview.report_exists);
                btn.disabled = !statusReady || !dailyOverview.can_create || aiContentStale;
            }
            aiLabel.textContent = @json(__('ui.ai_generate_suggestion'));
        }
    });
    [title, date].forEach(el => el?.addEventListener('input', () => {
        el.classList.remove('invalid');
        el.closest('div.grid')?.querySelector('.field-error')?.classList.add('hidden');
        alertBox.classList.add('hidden');
    }));
    form.addEventListener('submit', function (e) {
        if (aiContentStale) {
            e.preventDefault();
            showAlert(@json(__('ui.ai_suggestion_stale')));
            return;
        }
        if (!statusReady || !dailyOverview.can_create) {
            e.preventDefault();
            showAlert(statusReady ? dailyStatusText.textContent : translations.status_error);
            return;
        }
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
        form.dataset.creating = '1';
        if (aiBtn) aiBtn.disabled = true;
        btn.disabled = true;
        btn.textContent = translations.creating_draft || @json(__('ui.creating_draft'));
    });
})();
</script>
@endsection
