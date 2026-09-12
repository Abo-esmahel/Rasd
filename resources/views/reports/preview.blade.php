@extends('layouts.app')

@section('content')
{{-- Preview رسمي Inline — بلا أي زر تنزيل/طباعة/مشاركة. نفس Official HTML/CSS. --}}
<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 min-h-[44px] text-sm text-[#6b7a6e] hover:text-[#0e6a38] transition">{{ back_arrow() }} {{ __('ui.back_to_reports') }}</a>
    <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#e8f3ec] text-[#0e6a38]">{{ __('ui.preview_badge') }}</span>
</div>

<div class="bg-white border border-[#e6e9e1] rounded-2xl px-4 sm:px-5 py-4 shadow-sm mb-3">
    <h1 class="text-base sm:text-lg font-extrabold text-[#1a2e1f] leading-snug">{{ l10n_text('report', $report->id, 'title', $report->title) }}</h1>
    <p class="text-[13px] text-[#6b7a6e] mt-1">{{ $report->report_date?->toDateString() }}</p>
</div>

<link rel="stylesheet" href="{{ asset('report/css/report-engine.css') }}?v=17">
<p id="report-l10n-badge" class="hidden text-[12px] font-bold text-[#0e6a38] bg-[#e8f3ec] border border-[#cde7d6] rounded-xl px-3 py-1.5 mb-2 w-fit" role="status"></p>
<div class="bg-transparent sm:bg-white sm:border sm:border-[#e6e9e1] rounded-2xl p-0 sm:p-5 shadow-none sm:shadow-sm overflow-hidden" data-paper-fit data-report-id="{{ $report->id }}">
    <div class="report-preview" id="report-preview-doc" data-paper-fit-inner>
        {!! $html !!}
    </div>
</div>

@if(!empty($canExport))
<p class="text-[11px] text-[#6b7a6e] mt-2">{{ __('ui.export_hint') }}</p>
@endif
@include('reports.partials.paper_fit')
@endsection

