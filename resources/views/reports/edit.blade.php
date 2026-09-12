@extends('layouts.app')

@section('content')
<div class="max-w-[560px] mx-auto w-full">
    <div class="flex items-center gap-2.5">
        <a href="{{ route('reports.show', $report) }}" class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e6e9e1] bg-white text-[#6b7a6e] hover:text-[#0e6a38] hover:border-[#0e6a38] transition" aria-label="{{ __('ui.back') }}">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-[15.5px] font-extrabold text-[#1a2e1f] leading-tight tracking-tight">{{ __('ui.edit_data') }}</h1>
            @if($report->isPublished() && $report->editDeadline())<p class="text-[11.5px] text-[#9aa99a] leading-4 mt-0.5">{{ __('ui.edit_until') }} {{ $report->editDeadline()->format('Y-m-d H:i') }}</p>@endif
        </div>
    </div>
    <div class="mt-5 h-px bg-[#eceee9]"></div>
    <form method="POST" action="{{ route('reports.update', $report) }}" class="mt-5 grid gap-6">
        @csrf @method('PUT')
        <div class="grid gap-2">
            <label class="text-[12.5px] font-bold text-[#1a2e1f]">{{ __('ui.report_title_label') }}</label>
            <input name="title" minlength="3" maxlength="255" value="{{ old('title', $report->title) }}" class="w-full min-h-[42px] text-[13.5px] bg-white border border-[#e6e9e1] rounded-xl px-3.5 py-2.5 text-[#1a2e1f] placeholder:text-[#b0bab2] focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition">
            @error('title')<span class="text-[11.5px] font-bold text-[#b91c1c]">{{ $message }}</span>@enderror
        </div>
        <div class="grid gap-2">
            <label class="text-[12.5px] font-bold text-[#1a2e1f]">{{ __('ui.report_date_label') }}</label>
            <input type="date" name="report_date" max="{{ date('Y-m-d') }}" value="{{ old('report_date', $report->report_date->toDateString()) }}" class="w-full min-h-[42px] text-[13.5px] bg-white border border-[#e6e9e1] rounded-xl px-3.5 py-2.5 text-[#1a2e1f] focus:outline-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 transition">
            <span class="text-[11.5px] leading-5 text-[#9aa99a]">{{ __('ui.report_date_match_hint') }}</span>
            @error('report_date')<span class="text-[11.5px] font-bold text-[#b91c1c]">{{ $message }}</span>@enderror
        </div>
        <div class="flex items-center justify-between gap-4 py-3 border-y border-[#eceee9]">
            <span class="text-[13px] font-bold text-[#1a2e1f]">{{ __('ui.visible_to_monitors') }}</span>
            <span class="r-switch"><input type="checkbox" name="visible_to_monitors" value="1" @checked(old('visible_to_monitors', $report->visible_to_monitors))><span class="track"></span></span>
        </div>
        @if($errors->has('general'))<div class="text-[13px] font-bold text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded-xl px-3.5 py-2.5">{{ $errors->first('general') }}</div>@endif
        <div class="flex flex-col-reverse sm:flex-row gap-2 pt-1">
            <a href="{{ route('reports.show', $report) }}" class="inline-flex items-center justify-center px-5 min-h-[42px] rounded-xl border border-[#e6e9e1] bg-white text-[13px] font-bold text-[#6b7a6e] hover:border-[#0e6a38] hover:text-[#0e6a38] transition">{{ __('ui.back') }}</a>
            <button class="inline-flex items-center justify-center px-7 min-h-[42px] rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-[13px] transition shadow-sm sm:ms-auto w-full sm:w-auto">{{ __('ui.save') }}</button>
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
</style>
@endsection
