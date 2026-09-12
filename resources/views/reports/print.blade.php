@php
    // Unified block: single-line php directives must not precede a block in the same file.
    $printLocale = app()->getLocale() === 'en' ? 'en' : 'ar';
    $printDir = $printLocale === 'en' ? 'ltr' : 'rtl';
@endphp
<!DOCTYPE html>
<html dir="{{ $printDir }}" lang="{{ $printLocale }}">
<head>
<meta charset="utf-8">
<title>{{ $report->title }}</title>
@php
  $acc = '#0e6a38';
  $pT = 16; $pR = 14; $pB = 18; $pL = 14;
@endphp
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Cairo','Segoe UI',Tahoma,sans-serif; direction: rtl; margin: 0; background: #fff; color: #1c1917; }
  .toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 16px; background: #1c1917; color: #fff; position: sticky; top: 0; z-index: 50; font-size: 13px; }
  .toolbar .t-title { font-weight: 800; margin-right: auto; }
  .toolbar button, .toolbar a.tbtn { background: #2e3532; color: #fff; border: 1px solid #4a544c; border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; min-height: 36px; }
  @media screen and (max-width: 640px) {
    .toolbar { padding: 10px 12px; }
    .toolbar button, .toolbar a.tbtn { min-height: 44px; padding: 10px 16px; font-size: 14px; }
    .toolbar .t-title { width: 100%; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .toolbar .pages-label, .toolbar .zoom-label { display: none; }
  }
  .toolbar button:hover, .toolbar a.tbtn:hover { background: #3a443b; }
  .toolbar button.primary { background: {{ $acc }}; border-color: {{ $acc }}; }
  .toolbar .zoom-label { min-width: 52px; text-align: center; font-variant-numeric: tabular-nums; }
  .toolbar .pages-label { color: #c9d2cb; }
  #preview-root { display: flex; flex-direction: column; align-items: center; gap: 18px; padding: 18px 12px 30px; background: #525659; min-height: 100vh; }
  .p-zoom { transform-origin: top center; overflow: hidden; }
  .p-page { width: 210mm; height: 297mm; background: #fff; position: relative; overflow: hidden; box-shadow: 0 2px 18px rgba(0,0,0,.4); page-break-after: always; text-align: right; }
  .p-inner { position: absolute; top: 0; right: 0; bottom: 0; left: 0; overflow: hidden; }
  .p-inner h1 { font-size: 19px; margin: 0 0 8px; text-align: center; color: {{ $acc }}; }
  .p-meta { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; }
  .p-meta th, .p-meta td { border: 1px solid {{ $acc }}55; padding: 5px 9px; }
  .p-meta th { background: {{ $acc }}1A; color: {{ $acc }}; width: 26%; }
  .p-line { font-size: 13.5px; line-height: 2.1; white-space: pre-wrap; text-align: justify; margin: 0; overflow-wrap: break-word; }
  .p-line:empty::before { content: '\00a0'; }
  .p-foot { position: absolute; right: 0; left: 0; display: flex; justify-content: space-between; font-size: 10.5px; color: #78716c; padding: 0 4mm; }
  .p-default-head { text-align: center; border-bottom: 2.5px double {{ $acc }}; padding-bottom: 8px; margin-bottom: 10px; }
  .p-default-head img { height: 50px; }
  .p-default-head .ministry { font-weight: 800; font-size: 16px; }
  .p-default-head .dept { font-size: 11.5px; color: #57534e; }
  /* ── الورقة الرسمية (احتياطي قديم) ── */
  body.sheet-mode .zoom-only { display: none; }
  .sheet-page { width: 210mm; height: 281.25mm; position: relative; background-size: 100% 100%; background-repeat: no-repeat; box-shadow: 0 2px 18px rgba(0,0,0,.4); flex-shrink: 0; }
  .sheet-page.sheet-filled { height: auto; background: #fff; }
  .sheet-page.sheet-filled img { width: 100%; height: auto; display: block; }
  .sh-field, .sh-note { position: absolute; color: #1c1917; overflow: hidden; }
  .sh-field { text-align: center; white-space: nowrap; font-weight: 700; }
  .sh-note { text-align: right; }
  body.sheet-mode #preview-root { display: none; }
  .sheet-note { background: #fff8e7; border: 1px solid #e0cf9e; color: #7a5c14; border-radius: 10px; padding: 8px 16px; font-size: 13px; }
  .report,
  .report * { font-family: 'ReportNaskh', 'ReportBody', 'Traditional Arabic', 'Simplified Arabic', serif !important; }
  .report__ministry, .report__form-title, .report__observation-title,
  .report__section-title, .report__person-name { font-family: 'ReportBody', 'ReportNaskh', 'Traditional Arabic', 'Simplified Arabic', serif !important; }
  @media print {
    /* Legacy fixed-sheet path only (.p-page is exactly 210×297mm with
       internal padding, so it must map 1:1 onto the sheet). Engine
       documents load report-engine.css later and its @page wins there. */
    @page { size: A4; margin: 0; }
    .toolbar, .sheet-note { display: none !important; }
    body { background: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    body.sheet-mode { background: #f6f1e6 !important; }
    .sheet-page { margin: 7.9mm auto 0; box-shadow: none; }
    .sheet-page.sheet-filled { margin: 0 auto; }
    .sheet-page.sheet-filled img { width: 190mm; margin: 5mm auto 0; }
    #preview-root { display: block; padding: 0; background: #fff; gap: 0; min-height: auto; }
    .p-zoom { transform: none !important; height: auto !important; width: auto !important; }
    .p-page { box-shadow: none; margin: 0; page-break-after: always; }
    /* The unconditional break above ejects a trailing blank sheet on
       the physical printer — the last sheet must end the job. */
    .p-zoom:last-child .p-page { page-break-after: auto; break-after: auto; }
  }
  @if(!empty($htmlPreview ?? null))
  @media print {
    .report-preview { margin: 0 !important; padding: 0 !important; background: #fff !important; border: none !important; overflow: visible !important; }
    .report-preview .report { box-shadow: none !important; border-radius: 0 !important; }
  }
  @endif
</style>
@if(!empty($htmlPreview ?? null))
<link rel="stylesheet" href="{{ asset('report/css/report-engine.css') }}?v=17">
@endif
</head>
<body class="{{ ($sheet ?? null) ? 'sheet-mode' : '' }}">

<div class="toolbar">
    <a class="tbtn" href="{{ route('reports.show', $report) }}">→ {{ __('ui.redirect_back') }}</a>
    <button class="primary" onclick="window.print()">{{ __('ui.rpt_print_pdf') }}</button>
    <span class="t-title">{{ \Illuminate\Support\Str::limit($report->title, 45) }}</span>
    @if(!empty($htmlPreview ?? null))
        <span class="pages-label">{{ __('ui.rpt_engine_doc') }} · {{ $report->notes->count() }} {{ $report->notes->count() === 1 ? __('ui.note_one') : __('ui.notes_many') }}</span>
    @else
        <span class="pages-label" id="pages-label">…</span>
    @endif
    @if(empty($htmlPreview ?? null))
    <button type="button" class="zoom-only" onclick="PV.zoomOut()">−</button>
    <span class="zoom-label zoom-only" id="zoom-label">100%</span>
    <button type="button" class="zoom-only" onclick="PV.zoomIn()">+</button>
    <button type="button" class="zoom-only" onclick="PV.fit()">{{ __('ui.rpt_fit') }}</button>
    @endif
</div>

@if(!empty($htmlPreview ?? null))
        {{-- The official engine document — byte-identical HTML and identical
             shell/CSS as the show-page preview. No banners here: approval
             state is gated on the report page itself. --}}
        <div class="report-preview" id="sheet-page">
            {!! $htmlPreview !!}
        </div>
@elseif($report->notes->count() > (int) config('report_sheets.max_notes', 7))
<div style="display:flex;justify-content:center;padding:0 12px;"><div class="sheet-note">{{ __('ui.rpt_overflow_note', ['n' => $report->notes->count()]) }}</div></div>
@elseif($report->notes->count() === 0)
<div style="display:flex;justify-content:center;padding:0 12px;"><div class="sheet-note">{{ __('ui.rpt_empty_notes_a') }}<a href="{{ route('reports.show', $report) }}" style="color:#0e6a38;font-weight:800;">{{ __('ui.rpt_attach_link') }}</a>{{ __('ui.rpt_empty_notes_b') }}</div></div>
@else
<div style="display:flex;justify-content:center;padding:0 12px;"><div class="sheet-note">{{ __('ui.rpt_no_version_a') }}<a href="{{ route('reports.show', $report) }}" style="color:#0e6a38;font-weight:800;">{{ __('ui.rpt_approve_link') }}</a>{{ __('ui.rpt_no_version_b') }}</div></div>
@endif

{{-- Legacy text fallback ONLY when no official engine document exists.
     When $htmlPreview is present it is the single printed document. --}}
@if(empty($htmlPreview ?? null))
<div id="preview-root">
    <noscript>
        <div class="p-page">
            <div class="p-inner" style="padding:{{ $pT }}mm {{ $pL }}mm {{ $pB }}mm {{ $pR }}mm;">
                <h1>{{ $report->title }}</h1>
                <div style="font-size:13.5px;line-height:2.1;white-space:pre-wrap;">{{ $report->content }}</div>
            </div>
        </div>
    </noscript>
</div>
@endif

<div id="src-data" style="display:none;"
    data-title="{{ $report->title }}"
    data-img=""
    data-tpl="0"
    data-printed="{{ now()->format('Y-m-d H:i') }}"
    data-emblem="{{ asset('images/eagle-emblem.svg') }}"></div>
<pre id="src-meta" style="display:none;">تاريخ التقرير|{{ $report->report_date->toDateString() }}|رقم التقرير|{{ $report->id }}|الكاتب|{{ $report->author->name ?? '—' }}|تاريخ النشر|{{ $report->published_at?->format('Y-m-d H:i') ?? '—' }}</pre>
<pre id="src-content" style="display:none;">{{ $report->content }}</pre>

<script>
var PV_T = {
    printedAt: @json(__('ui.rpt_printed_at')),
    ministry: @json(__('ui.print_ministry')),
    dept: @json(__('ui.rpt_print_dept')),
    date: @json(__('ui.print_date')),
    number: @json(__('ui.report_number')),
    author: @json(__('ui.rpt_author')),
    publishedAt: @json(__('ui.rpt_published_at')),
    pageOf: @json(__('ui.print_page_of')),
    officialCount: @json(__('ui.print_official_count'))
};
function pvPageLabel(a, b){ return String(PV_T.pageOf || '').split(':a').join(a).split(':b').join(b); }
var PV = (function () {
    var MM = 96 / 25.4, PW = 210 * MM, PH = 297 * MM, FOOT = 30;
    var P = { t: {{ $pT }} * MM, r: {{ $pR }} * MM, b: {{ $pB }} * MM, l: {{ $pL }} * MM };
    var root = document.getElementById('preview-root');
    var zooms = [], scale = 1;

    function el(tag, cls, html) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (html !== undefined) e.innerHTML = html;
        return e;
    }
    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function over(inner) { return inner.scrollHeight > inner.clientHeight + 1; }

    function newPage(img, tpl) {
        var wrap = el('div', 'p-zoom');
        var page = el('div', 'p-page' + (tpl ? ' tpl' : ''));
        if (tpl && img) page.style.backgroundImage = "url('" + img + "')";
        var inner = el('div', 'p-inner');
        inner.style.padding = P.t + 'px ' + P.l + 'px ' + (P.b + FOOT) + 'px ' + P.r + 'px';
        inner.style.height = PH + 'px';
        var foot = el('div', 'p-foot');
        var fnum = el('span', 'p-fnum');
        foot.appendChild(fnum);
        foot.appendChild(el('span', null, String(PV_T.printedAt || '').split(':date').join(document.getElementById('src-data').dataset.printed)));
        foot.style.bottom = Math.max(2, (P.b / 2 - 8)) + 'px';
        page.appendChild(inner);
        page.appendChild(foot);
        wrap.appendChild(page);
        root.appendChild(wrap);
        zooms.push(wrap);
        return { inner: inner, fnum: fnum };
    }

    function pushWords(inner, text, newPageFn) {
        // يوزع سطراً أطول من صفحة على كلمات (حالة شاذة)
        var words = text.split(/\s+/), buf = '';
        for (var i = 0; i < words.length; i++) {
            var test = buf ? buf + ' ' + words[i] : words[i];
            var probe = el('div', 'p-line', esc(test));
            inner.appendChild(probe);
            if (over(inner)) {
                inner.removeChild(probe);
                if (buf) {
                    inner.appendChild(el('div', 'p-line', esc(buf)));
                    buf = '';
                    inner = newPageFn().inner;
                    i--; // إعادة نفس الكلمة في الصفحة الجديدة — لا ضياع
                } else {
                    var w = words[i];
                    while (w.length > 60) { inner.appendChild(el('div', 'p-line', esc(w.slice(0, 60)))); w = w.slice(60); }
                    buf = w;
                    continue;
                }
            } else {
                inner.removeChild(probe);
                buf = test;
            }
        }
        if (buf) inner.appendChild(el('div', 'p-line', esc(buf)));
        return inner;
    }

    function build() {
        root.innerHTML = '';
        zooms = [];
        var src = document.getElementById('src-data');
        var img = src.dataset.img, tpl = src.dataset.tpl === '1';
        var meta = document.getElementById('src-meta').textContent.trim().split('|');
        var lines = document.getElementById('src-content').textContent.replace(/\n$/, '').split('\n');
        var pages = [], cur = newPage(img, tpl), inner = cur.inner;
        pages.push(cur);
        if (!tpl) {
            inner.appendChild(el('div', 'p-default-head',
                '<img src="' + src.dataset.emblem + '" alt=""><div class="ministry">' + PV_T.ministry + '</div><div class="dept">' + PV_T.dept + '</div>'));
        }
        inner.appendChild(el('h1', null, esc(src.dataset.title)));
        inner.appendChild(el('div', null,
            '<table class="p-meta"><tr><th>' + PV_T.date + '</th><td>' + esc(meta[1] || '') + '</td><th>' + PV_T.number + '</th><td>' + esc(meta[3] || '') + '</td></tr>' +
            '<tr><th>' + PV_T.author + '</th><td>' + esc(meta[5] || '') + '</td><th>' + PV_T.publishedAt + '</th><td>' + esc(meta[7] || '') + '</td></tr></table>'));
        lines.forEach(function (ln) {
            if (ln.trim() === '') {
                var gap = el('div', 'p-line', '');
                inner.appendChild(gap);
                if (over(inner)) inner.removeChild(gap);
                return;
            }
            var div = el('div', 'p-line', esc(ln));
            inner.appendChild(div);
            if (over(inner)) {
                inner.removeChild(div);
                cur = newPage(img, tpl);
                inner = cur.inner;
                pages.push(cur);
                inner = pushWords(inner, ln, function () { cur = newPage(img, tpl); pages.push(cur); return cur; });
            }
        });
        pages.forEach(function (p, i) { p.fnum.textContent = pvPageLabel(i + 1, pages.length); });
        document.getElementById('pages-label').textContent = String(PV_T.officialCount || '').split(':n').join(pages.length);
        fit();
    }

    function applyZoom() {
        zooms.forEach(function (w) {
            w.style.transform = 'scale(' + scale + ')';
            w.style.height = (PH * scale) + 'px';
            w.style.width = (PW * scale) + 'px';
        });
        document.getElementById('zoom-label').textContent = Math.round(scale * 100) + '%';
    }
    function fit() {
        var w = Math.min(document.documentElement.clientWidth - 24, 900);
        scale = Math.min(1.2, Math.max(0.3, w / PW));
        applyZoom();
    }
    return {
        build: build,
        fit: fit,
        zoomIn: function () { scale = Math.min(1.5, +(scale + 0.1).toFixed(2)); applyZoom(); },
        zoomOut: function () { scale = Math.max(0.3, +(scale - 0.1).toFixed(2)); applyZoom(); }
    };
})();

function fitSheet() {
    var page = document.getElementById('sheet-page');
    if (!page) return;
    var H = page.clientHeight;
    if (!H) return;
    page.querySelectorAll('.sh-field').forEach(function (f) {
        var fs = H * parseFloat(f.dataset.fs || '1.5') / 100;
        f.style.fontSize = fs + 'px';
        f.style.lineHeight = (H * 0.022) + 'px';
        var guard = 60;
        while ((f.scrollWidth > f.clientWidth + 1 || f.scrollHeight > f.clientHeight + 1) && fs > H * 0.008 && guard-- > 0) {
            fs -= 0.5;
            f.style.fontSize = fs + 'px';
        }
    });
    page.querySelectorAll('.sh-fit').forEach(function (box) {
        var pitch = H * parseFloat(box.dataset.pitch || '3') / 100;
        var fs = H * 0.0158;
        box.style.lineHeight = pitch + 'px';
        box.style.fontSize = fs + 'px';
        var guard = 60;
        while (box.scrollHeight > box.clientHeight + 1 && fs > H * 0.0083 && guard-- > 0) {
            fs -= 0.5;
            box.style.fontSize = fs + 'px';
        }
        guard = 60;
        var lh = pitch;
        while (box.scrollHeight > box.clientHeight + 1 && lh > pitch * 0.6 && guard-- > 0) {
            lh -= 0.5;
            box.style.lineHeight = lh + 'px';
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    @if($sheet ?? null)
        fitSheet();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitSheet);
    @elseif(empty($htmlPreview ?? null))
        PV.build();
        /* Pagination is measured against live font metrics: if the official
           fonts swap in after first paint, rebuild once so screen sheets
           and printed sheets break on identical metrics (no fallback-metric
           splits that preview hides and hardware exposes). Idempotent. */
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { PV.build(); });
    @endif
});
window.addEventListener('resize', function () {
    @if($sheet ?? null)
        fitSheet();
    @elseif(empty($htmlPreview ?? null))
        PV.fit();
    @endif
});
window.addEventListener('load', function () {
    @if($sheet ?? null)
        fitSheet();
    @endif
});
</script>
</body>
</html>

