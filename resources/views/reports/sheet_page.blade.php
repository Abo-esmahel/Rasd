@php($sheetLocale = app()->getLocale() === 'en' ? 'en' : 'ar')
@php($sheetDir = $sheetLocale === 'en' ? 'ltr' : 'rtl')
<!DOCTYPE html>
<html dir="{{ $sheetDir }}" lang="{{ $sheetLocale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $report->title }} — {{ __('ui.rpt_sheet_preview') }}</title>
<link rel="stylesheet" href="{{ asset('report/css/report-engine.css') }}?v=17">
<style>
  body { margin: 0; background: #525659; font-family: 'Cairo','Segoe UI',Tahoma,sans-serif; }
  .wrap { display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 18px 12px 30px; min-height: 100vh; }
  .bar { display: flex; gap: 8px; align-items: center; color: #fff; font-size: 13px; }
  .bar a, .bar button { background: #2e3532; color: #fff; border: 1px solid #4a544c; border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; }
  @media print {
    body { background: #fff; }
    .bar { display: none; }
    /* 100vh in paged media resolves to a full sheet: with content
       shorter than the viewport the wrapper alone overflows onto a
       blank second physical page. */
    .wrap { padding: 0; background: #fff; display: block; min-height: auto; }
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <a href="{{ route('reports.show', $report) }}">→ {{ __('ui.redirect_back') }}</a>
    @can('export', $report)<button onclick="window.print()">{{ __('ui.rpt_print_pdf') }}</button>@endcan
    <span>{{ \Illuminate\Support\Str::limit($report->title, 45) }}</span>
  </div>
  <div class="report-preview">{!! $html !!}</div>
</div>
</body>
</html>


