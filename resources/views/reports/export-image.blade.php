@extends('layouts.app')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 min-h-[44px] text-sm text-[#6b7a6e] hover:text-[#0e6a38] transition">{{ back_arrow() }} {{ __('ui.back_to_reports') }}</a>
    <button type="button" id="dl-image-btn" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 min-h-[48px] sm:min-h-[44px] rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-base sm:text-sm font-bold transition">{{ __('ui.download_png') }}</button>
</div>

<div class="bg-white border border-[#e6e9e1] rounded-2xl px-4 sm:px-5 py-4 shadow-sm mb-3">
    <h1 class="text-base sm:text-lg font-extrabold text-[#1a2e1f] leading-snug">{{ l10n_text('report', $report->id, 'title', $report->title) }}</h1>
    <p class="text-[13px] text-[#6b7a6e] mt-1">{{ $report->report_date?->toDateString() }}</p>
</div>

<link rel="stylesheet" href="{{ asset('report/css/report-engine.css') }}?v=17">
<p id="report-l10n-badge" class="hidden text-[12px] font-bold text-[#0e6a38] bg-[#e8f3ec] border border-[#cde7d6] rounded-xl px-3 py-1.5 mb-2 w-fit" role="status"></p>
<div class="bg-white border border-[#e6e9e1] rounded-2xl p-3 sm:p-5 shadow-sm overflow-x-auto" data-report-id="{{ $report->id }}">
    <div class="report-preview" id="report-export-doc">
        {!! $html !!}
    </div>
</div>
<p id="img-err" class="hidden text-xs text-red-600 mt-2"></p>


<script src="{{ asset('js/vendor/html2canvas.min.js') }}" defer></script>
<script>
(function () {
    const EXP_T = {
        encodeFail: @json(__('ui.rpt_img_encode_fail')),
        preparing: @json(__('ui.preparing')),
        libFail: @json(__('ui.rpt_img_lib_fail')),
        notFound: @json(__('ui.rpt_report_missing')),
        createFail: @json(__('ui.rpt_img_create_fail')),
        saveHint: @json(__('ui.rpt_img_save_hint'))
    };
    const btn = document.getElementById('dl-image-btn');
    const err = document.getElementById('img-err');
    const fileName = 'report-{{ $report->id }}.png';

    function fail(msg) {
        if (err) { err.textContent = msg; err.classList.remove('hidden'); }
    }
    function isCoarsePointer() {
        try { return window.matchMedia('(pointer: coarse)').matches; }
        catch (e) { return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || ''); }
    }
    function canvasToBlob(canvas) {
        return new Promise(function (resolve, reject) {
            try {
                if (canvas.toBlob) canvas.toBlob(function (b) { b ? resolve(b) : reject(new Error(EXP_T.encodeFail)); }, 'image/png');
                else resolve(null);
            } catch (e) { reject(e); }
        });
    }

    btn?.addEventListener('click', async function () {
        err?.classList.add('hidden');
        btn.disabled = true;
        const old = btn.textContent;
        btn.textContent = EXP_T.preparing;
        try {
            if (!window.html2canvas) throw new Error(EXP_T.libFail);
            // الخطوط العربية يجب أن تكتمل قبل الالتقاط وإلا خرج النص بخط بديل/مكسور.
            try { if (document.fonts && document.fonts.ready) await document.fonts.ready; } catch (e) {}
            const node = document.getElementById('report-export-doc');
            if (!node) throw new Error(EXP_T.notFound);
            // دقة أقل على الجوال: canvas بمقياس 2 لصفحة طويلة قد يتجاوز ذاكرة المتصفح ويقتله.
            const scale = window.innerWidth < 640 ? 1.5 : 2;
            const canvas = await window.html2canvas(node, { backgroundColor: '#ffffff', scale: scale, useCORS: true });
            const blob = await canvasToBlob(canvas);
            if (!blob) throw new Error(EXP_T.createFail);

            const file = new File([blob], fileName, { type: 'image/png' });
            // الجوال: ورقة المشاركة (واتساب/حفظ) — خاصية download لا تعمل على iOS Safari.
            if (isCoarsePointer() && navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title: fileName });
                return;
            }
            // سطح المكتب: تنزيل مباشر.
            const url = URL.createObjectURL(blob);
            try {
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                a.remove();
                // iOS يتجاهل download بصمت: إن بقي المستخدم هنا بعد لحظة، افتح الصورة بتبويب للحفظ اليدوي.
                if (isCoarsePointer()) {
                    setTimeout(function () {
                        window.open(url, '_blank');
                        fail(EXP_T.saveHint);
                    }, 900);
                }
            } finally {
                setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
            }
        } catch (e) {
            // إلغاء المشاركة من المستخدم ليس خطأً.
            if (e && (e.name === 'AbortError' || /abort|cancel/i.test(e.message || ''))) return;
            fail((e && e.message) || EXP_T.createFail);
        } finally {
            btn.disabled = false;
            btn.textContent = old;
        }
    });
})();
</script>
@endsection

