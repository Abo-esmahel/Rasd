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
<div class="bg-white border border-[#e6e9e1] rounded-2xl p-3 sm:p-5 shadow-sm overflow-x-auto" data-report-id="{{ $report->id }}" data-paper-fit>
    <div class="report-preview" id="report-export-doc" data-paper-fit-inner>
        {!! $html !!}
    </div>
</div>
<p id="img-err" class="hidden text-xs text-red-600 mt-2"></p>
@include('reports.partials.paper_fit')


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

    // التقاط رسمي بمقاس A4 دائمًا: يُبنى المستند داخل iframe معزول بعرض
    // ثابت (900px) فيملك viewport خاصًا به فتُطبق قواعد A4 المكتبية ولا
    // تتسرب إليه استعلامات الجوال (max-width:640px) ولا تمرير الحاوية ولا
    // تحويلات الملاءمة — نفس الناتج على الجوال والويندوز.
    function captureOfficialA4() {
        return new Promise(function (resolve, reject) {
            let node;
            try {
                node = document.getElementById('report-export-doc');
                if (!node) throw new Error(EXP_T.notFound);
            } catch (e) { reject(e); return; }
            const article = node.querySelector('.report');
            const docDir = (article && article.getAttribute('dir')) || document.documentElement.getAttribute('dir') || 'rtl';
            const docLang = (article && article.getAttribute('lang')) || document.documentElement.getAttribute('lang') || 'ar';
            const cssUrl = @json(asset('report/css/report-engine.css') . '?v=17');
            const libUrl = @json(asset('js/vendor/html2canvas.min.js'));
            const page = '<!DOCTYPE html><html><head><meta charset="utf-8">'
                + '<link rel="stylesheet" href="' + cssUrl + '">'
                + '<style>html,body{margin:0;padding:0;background:#ffffff;}'
                + 'body{padding:24px;display:flex;justify-content:center;}'
                + '#h2c-target{width:100%;max-width:210mm;}'
                + '#h2c-target .report{margin:0 auto;}'
                + 'img{max-width:100%;}</style>'
                + '</head><body dir="' + docDir + '" lang="' + docLang + '">'
                + '<div id="h2c-target">' + node.innerHTML + '</div>'
                + '<script src="' + libUrl + '"><\/script>'
                + '</body></html>';

            const iframe = document.createElement('iframe');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.tabIndex = -1;
            iframe.style.cssText = 'position:fixed;top:0;left:-12000px;width:900px;height:600px;border:0;visibility:hidden;pointer-events:none;';
            let finished = false;
            let timer = null;
            const dispose = function () { try { iframe.remove(); } catch (e) {} };
            const fail = function (e) { if (!finished) { finished = true; if (timer) clearTimeout(timer); dispose(); reject(e); } };
            const win = function (v) { if (!finished) { finished = true; if (timer) clearTimeout(timer); resolve(v); } };
            timer = setTimeout(function () { fail(new Error(EXP_T.createFail)); }, 45000);
            iframe.addEventListener('load', async function () {
                try {
                    const w = iframe.contentWindow;
                    const d = iframe.contentDocument;
                    if (!w || !d) throw new Error(EXP_T.createFail);
                    try {
                        if (w.document.fonts && w.document.fonts.ready) {
                            await Promise.race([w.document.fonts.ready, new Promise(function (r) { setTimeout(r, 4000); })]);
                        }
                    } catch (e) {}
                    await new Promise(function (r) { setTimeout(r, 300); });
                    if (typeof w.html2canvas !== 'function') throw new Error(EXP_T.libFail);
                    const target = d.getElementById('h2c-target');
                    if (!target) throw new Error(EXP_T.notFound);
                    try {
                        const sh = d.body ? d.body.scrollHeight : 0;
                        if (sh > 100) iframe.style.height = Math.min(16000, sh + 60) + 'px';
                    } catch (e) {}
                    const h = Math.max(1, target.scrollHeight || target.offsetHeight || 1000);
                    const scale = Math.max(1, Math.min(2, 12000 / h));
                    const canvas = await w.html2canvas(target, { backgroundColor: '#ffffff', scale: scale, useCORS: true, logging: false });
                    win({ canvas: canvas, dispose: dispose });
                } catch (e) { fail(e); }
            });
            iframe.addEventListener('error', function () { fail(new Error(EXP_T.createFail)); });
            document.body.appendChild(iframe);
            try { iframe.srcdoc = page; }
            catch (e) { fail(e); }
        });
    }

    // مسار احتياطي: التقاط العقدة الحية مباشرة (سلوك ما قبل الإصلاح).
    async function captureLiveNode() {
        if (!window.html2canvas) throw new Error(EXP_T.libFail);
        try { if (document.fonts && document.fonts.ready) await document.fonts.ready; } catch (e) {}
        const node = document.getElementById('report-export-doc');
        if (!node) throw new Error(EXP_T.notFound);
        const scale = window.innerWidth < 640 ? 1.5 : 2;
        const canvas = await window.html2canvas(node, { backgroundColor: '#ffffff', scale: scale, useCORS: true });
        return { canvas: canvas, dispose: function () {} };
    }

    btn?.addEventListener('click', async function () {
        err?.classList.add('hidden');
        btn.disabled = true;
        const old = btn.textContent;
        btn.textContent = EXP_T.preparing;
        let shot = null;
        try {
            try {
                shot = await captureOfficialA4();
            } catch (e) {
                shot = await captureLiveNode();
            }
            const blob = await canvasToBlob(shot.canvas);
            if (!blob) throw new Error(EXP_T.createFail);

            const file = new File([blob], fileName, { type: 'image/png' });
            if (isCoarsePointer() && navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title: fileName });
                return;
            }
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
            try { if (shot && typeof shot.dispose === 'function') shot.dispose(); } catch (e) {}
            shot = null;
            btn.disabled = false;
            btn.textContent = old;
        }
    });
})();
</script>
@endsection

