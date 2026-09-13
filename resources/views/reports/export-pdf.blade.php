@extends('layouts.app')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-2 mb-3 no-print">
    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 min-h-[44px] text-sm text-[#6b7a6e] hover:text-[#0e6a38] transition">{{ back_arrow() }} {{ __('ui.back_to_reports') }}</a>
    <div class="flex items-center gap-2 w-full sm:w-auto">
        <button type="button" onclick="window.print()" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 min-h-[48px] sm:min-h-[44px] rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white text-base sm:text-sm font-bold transition">{{ __('ui.print_pdf') }}</button>
    </div>
</div>

<div class="bg-white border border-[#e6e9e1] rounded-2xl px-4 sm:px-5 py-4 shadow-sm mb-3 no-print">
    <h1 class="text-base sm:text-lg font-extrabold text-[#1a2e1f] leading-snug">{{ l10n_text('report', $report->id, 'title', $report->title) }}</h1>
    <p class="text-[13px] text-[#6b7a6e] mt-1">{{ $report->report_date?->toDateString() }}</p>
</div>

<link rel="stylesheet" href="{{ asset('report/css/report-engine.css') }}?v=17">
<p id="report-l10n-badge" class="hidden text-[12px] font-bold text-[#0e6a38] bg-[#e8f3ec] border border-[#cde7d6] rounded-xl px-3 py-1.5 mb-2 w-fit no-print" role="status"></p>
<div class="bg-white border border-[#e6e9e1] rounded-2xl p-3 sm:p-5 shadow-sm overflow-x-auto" data-report-id="{{ $report->id }}">
    <div class="report-preview" id="report-export-doc">
        {!! $html !!}
    </div>
</div>

<style>
.report, .report * { font-family: 'ReportNaskh', 'ReportBody', 'Traditional Arabic', 'Simplified Arabic', serif !important; }
.report__ministry, .report__form-title, .report__observation-title,
.report__section-title, .report__person-name { font-family: 'ReportBody', 'ReportNaskh', 'Traditional Arabic', 'Simplified Arabic', serif !important; }
@media print {
    header, nav, .no-print, footer, aside, #page-loader,
    #notification-drawer, #notification-overlay,
    #rasd-print-document, #printable-a4-doc { display: none !important; }
    /* overflow-x:clip is screen-only (layout/app.css): in paged media it
       alters fragmentation and can eject blank sheets on hardware. */
    html, body { overflow: visible !important; }
    /* body is flex-column + min-height:100vh for screen (layout): in paged
       media that floor + flex fragmentation ejects blank sheets. */
    body { background: #fff !important; margin: 0 !important; padding: 0 !important; max-width: none !important; width: auto !important; display: block !important; min-height: auto !important; }
    html.dark body { background: #fff !important; color: #1c1a15 !important; }
    #main-content, main { background: none !important; padding: 0 !important; margin: 0 !important; min-height: auto !important; max-width: none !important; width: auto !important; }
    /* Screen card chrome around the sheet (border/padding/overflow-x)
       must not enter paged media: it offsets the sheet and its
       non-visible overflow breaks fragmentation into extra sheets. */
    [data-report-id] { background: #fff !important; border: none !important; border-radius: 0 !important; padding: 0 !important; margin: 0 !important; overflow: visible !important; box-shadow: none !important; }
    .report-preview { background: #fff !important; border: none !important; padding: 0 !important; overflow: visible !important; }
    .report-preview .report { box-shadow: none !important; border-radius: 0 !important; }
}
</style>
@endsection

