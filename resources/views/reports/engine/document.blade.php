{{-- ======================================================================
  ONE HTML REPORT DOCUMENT — rendered only by ReportEngine (backend).
  Variables: $doc (engine document array).
  Same partial for Preview, Print and PDF (browser print → Save as PDF).
  No server PDF lib in this project (see composer.json) — CSS targets
  browser print (@page A4 + @media print), no fixed heights, natural flow.

  Paper-form model: «نموذج تقرير مراقبة الكاميرات اليومي»
  - Header identity once (ministry / emblem / day + date).
  - Form title once.
  - Info strip once (report number + notes count).
  - Dynamic observations 0..100 (text wraps, flows to next pages).
  - Per-observation facts ONLY when truthfully mapped (camera/floor/
    start/end/duration + attachment checkboxes from real Notes).
  - Immediate actions (recommendations data only, never copied).
  - Signatures (real author name; supervisor line stays ink-empty).
  INTERNAL engine data ($doc['internal']) is NEVER rendered here.
  Semantic HTML: header / section / article / ol / li / footer.
====================================================================== --}}
@php
  $doc = $doc ?? [];
  $density = $doc['density'] ?? 'balanced';
  $docDir = $doc['dir'] ?? 'rtl';
  $docLang = $doc['lang'] ?? 'ar';
  $isEnDoc = $docLang === 'en';
  $header = $doc['header'] ?? [];
  $observations = $doc['observations'] ?? [];
  $recommendations = $doc['recommendations'] ?? ['items' => [], 'paragraphs' => []];
  $recoItems = $recommendations['items'] ?? [];
  $recoParagraphs = $recommendations['paragraphs'] ?? [];
  $responsibles = $doc['responsibles'] ?? [];
  $recoTitle = $doc['recommendations_title'] ?? ($isEnDoc ? 'Immediate Actions Taken' : 'الإجراءات التي تم اتخاذها فوراً بناءً على الملاحظة');
  $signCaption = $doc['signature_caption'] ?? ($isEnDoc ? 'Signature' : 'التوقيع');
  $reportNumber = trim((string) ($doc['report_number'] ?? ($doc['meta'][0]['value'] ?? '')));
  $reportDate = trim((string) ($doc['report_date'] ?? ($doc['meta'][1]['value'] ?? ($doc['meta'][0]['value'] ?? ''))));
  // When meta holds only one cell (number xor date), disambiguate via labels.
  if (($doc['report_number'] ?? null) === null || ($doc['report_date'] ?? null) === null) {
    foreach (($doc['meta'] ?? []) as $cell) {
      $lbl = (string) ($cell['label'] ?? '');
      if (in_array($lbl, ['رقم التقرير', 'Report Number'], true)) { $reportNumber = trim((string) ($cell['value'] ?? '')); }
      if (in_array($lbl, ['التاريخ', 'Date'], true)) { $reportDate = trim((string) ($cell['value'] ?? '')); }
    }
  }
  $dayName = trim((string) ($doc['day_name'] ?? ''));
  $dayLabel = $doc['day_label'] ?? ($isEnDoc ? 'Day' : 'اليوم');
  $approvalDate = trim((string) ($doc['approval_date'] ?? ''));
  $notesCount = (int) ($doc['notes_count'] ?? count($observations));
  $monitorName = '';
  if (!empty($responsibles) && isset($responsibles[0]['name'])) { $monitorName = trim((string) $responsibles[0]['name']); }
  $observers = array_values(array_filter(array_map(fn($n) => trim((string) $n), (array) ($doc['observers'] ?? [])), fn($n) => $n !== '' && $n !== '—'));
  $observersText = implode($isEnDoc ? ', ' : '، ', $observers);
  $dash = fn($v) => trim((string) ($v ?? '')) !== '' ? $v : '—';
@endphp
<article class="report" dir="{{ $docDir }}" lang="{{ $docLang }}" data-density="{{ $density }}">
  <header class="report__header">
    <div class="report__header-side report__header-side--ministry">
      <div class="report__ministry">{{ $header['ministry'] ?? ($isEnDoc ? 'Syrian Arab Republic' : 'الجمهورية العربية السورية') }}</div>
      <div class="report__ministry-sub">{{ $header['ministry_sub'] ?? ($isEnDoc ? 'Ministry of Information' : 'وزارة الإعلام') }}</div>
    </div>
    <div class="report__logo">
      <img src="{{ asset(ltrim($doc['logo'] ?? '/report/assets/logo-report.png', '/')) }}" alt="{{ $isEnDoc ? 'Official report emblem' : 'شعار التقرير الرسمي' }}" loading="eager" decoding="async">
    </div>
    <div class="report__header-side report__header-side--doc">
      <div class="report__dayline"><span class="report__dayline-label">{{ $dayLabel }} :</span> <span class="report__dayline-value">{{ $dayName !== '' ? $dayName : '—' }}</span></div>
      <div class="report__dayline"><span class="report__dayline-label">{{ $isEnDoc ? 'Date' : 'التاريخ' }} :</span> <span class="report__dayline-value report__num">{{ $reportDate !== '' ? $reportDate : '—' }}</span></div>
    </div>
  </header>

  <div class="report__divider" aria-hidden="true"><span class="report__divider-mark"></span></div>

  <h2 class="report__form-title">{{ $header['doc_title'] ?? ($isEnDoc ? 'Daily Camera Surveillance Report' : 'نموذج تقرير مراقبة الكاميرات اليومي') }}@if(!$isEnDoc) :@endif</h2>

  <section class="report__infobar" aria-label="{{ $isEnDoc ? 'Report data' : 'بيانات التقرير' }}">
    <div class="report__infobar-cell">
      <span class="report__infobar-label">{{ $isEnDoc ? 'Report Number' : 'رقم التقرير' }} :</span>
      <span class="report__infobar-value report__num">{{ $reportNumber !== '' ? $reportNumber : '—' }}</span>
    </div>
    <div class="report__infobar-cell">
      <span class="report__infobar-label">{{ $isEnDoc ? 'Observations' : 'عدد الملاحظات' }} :</span>
      <span class="report__infobar-value report__num">{{ $notesCount }}</span>
    </div>
  </section>

  <div class="report__body">
    <section class="report__section report__section--observations" aria-label="{{ $isEnDoc ? 'Observations' : 'الملاحظات' }}">
      @if(empty($observations))
        <p class="report__empty">{{ $isEnDoc ? 'No observations in this report.' : 'لا توجد ملاحظات في هذا التقرير.' }}</p>
      @else
      <ol class="report__observations">
        @foreach($observations as $idx => $obs)
        @php
          $m = $obs['meta'] ?? null;
          $hasMeta = is_array($m);
          $yesOn = $hasMeta && !empty($m['has_attachments']);
          $noOn = $hasMeta && empty($m['has_attachments']);
          $vidOn = $hasMeta && !empty($m['has_video']);
          $imgOn = $hasMeta && !empty($m['has_image']);
        @endphp
        <li class="report__observation">
          <div class="report__obs-head">
            <span class="report__obs-diamond" aria-hidden="true"></span>
            <h3 class="report__observation-title">{{ $obs['title'] ?? '' }}</h3>
          </div>
          @if($hasMeta && trim((string) ($m['observer'] ?? '')) !== '')
          <div class="report__obs-monitor">
            <span class="report__obs-label">{{ $isEnDoc ? 'Monitoring officer:' : 'اسم المراقب:' }}</span>
            <span class="report__obs-monitor-value" data-no-translate>{{ $m['observer'] }}</span>
          </div>
          @endif
          @foreach(($obs['paragraphs'] ?? []) as $paragraph)
          <p class="report__observation-body">{{ $paragraph }}</p>
          @endforeach
          @if($hasMeta)
          <div class="report__obs-meta">
            <div class="report__obs-field"><span class="report__obs-label">{{ $isEnDoc ? 'Start:' : 'بدء الملاحظة:' }}</span> <span class="report__obs-value report__num">{{ $dash($m['start'] ?? '') }}</span></div>
            <div class="report__obs-field"><span class="report__obs-label">{{ $isEnDoc ? 'End:' : 'انتهاء الملاحظة:' }}</span> <span class="report__obs-value report__num">{{ $dash($m['end'] ?? '') }}</span></div>
            <div class="report__obs-field"><span class="report__obs-label">{{ $isEnDoc ? 'Duration:' : 'مدة الملاحظة:' }}</span> <span class="report__obs-value report__num">{{ $dash($m['duration'] ?? '') }}</span></div>
            <div class="report__obs-field"><span class="report__obs-label">{{ $isEnDoc ? 'Camera:' : 'رقم الكاميرا:' }}</span> <span class="report__obs-value report__num">{{ $dash($m['camera'] ?? '') }}</span></div>
            <div class="report__obs-field"><span class="report__obs-label">{{ $isEnDoc ? 'Floor:' : 'الطابق:' }}</span> <span class="report__obs-value report__num">{{ $dash($m['floor'] ?? '') }}</span></div>
          </div>
          <div class="report__obs-attach">
            <span class="report__obs-label">{{ $isEnDoc ? 'With photos/videos:' : 'مرفق بصور وفيديوهات:' }}</span>
            <span class="report__checks">
              <span class="report__check"><span class="report__box{{ $yesOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($yesOn)✓@endif</span> {{ $isEnDoc ? 'Yes' : 'نعم' }}</span>
              <span class="report__check"><span class="report__box{{ $noOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($noOn)✓@endif</span> {{ $isEnDoc ? 'No' : 'لا' }}</span>
              <span class="report__check"><span class="report__box{{ $vidOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($vidOn)✓@endif</span> {{ $isEnDoc ? 'Video' : 'فيديو' }}</span>
              <span class="report__check"><span class="report__box{{ $imgOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($imgOn)✓@endif</span> {{ $isEnDoc ? 'Photo' : 'صورة' }}</span>
            </span>
          </div>
          @endif
        </li>
        @endforeach
      </ol>
      @endif
    </section>

    @if(!empty($recoItems) || !empty($recoParagraphs))
    <section class="report__section report__section--recommendations" aria-label="{{ $recoTitle }}">
      <h2 class="report__section-title">{{ $recoTitle }}@if(!$isEnDoc) :@endif</h2>
      @if($observersText !== '')
      <div class="report__monitor">
        <span class="report__monitor-label">{{ $isEnDoc ? 'Monitoring officer(s):' : 'اسم المراقب الذي قام بمتابعة الكاميرات:' }}</span>
        <span class="report__monitor-value" data-no-translate>{{ $observersText }}</span>
      </div>
      @elseif($monitorName !== '' && $monitorName !== '—')
      <div class="report__monitor">
        <span class="report__monitor-label">{{ $isEnDoc ? 'Monitoring officer:' : 'اسم المراقب الذي قام بمتابعة الكاميرات:' }}</span>
        <span class="report__monitor-value" data-no-translate>{{ $monitorName }}</span>
      </div>
      @endif
      @if(!empty($recoItems))
      <ol class="report__recommendations-list">
        @foreach($recoItems as $item)
        <li class="report__recommendations-item">{{ $item }}</li>
        @endforeach
      </ol>
      @else
      <div class="report__recommendations">
        @foreach($recoParagraphs as $paragraph)
        <p class="report__recommendations-body">{{ $paragraph }}</p>
        @endforeach
      </div>
      @endif
    </section>
    @endif
  </div>

  <div class="report__sign">
    <div class="report__person">
      <div class="report__person-role">{{ $isEnDoc ? 'Name & signature of responsible monitor:' : 'اسم وتوقيع المراقب المسؤول :' }}</div>
      <div class="report__person-name" data-no-translate>{{ $monitorName !== '' ? $monitorName : '—' }}</div>
      <div class="report__signature-caption">{{ $signCaption }}</div>
      <div class="report__signature-line" aria-hidden="true"></div>
    </div>
    <div class="report__person report__person--date">
      <div class="report__person-role">{{ $isEnDoc ? 'Approval date:' : 'تاريخ المصادقة :' }}</div>
      <div class="report__person-name report__person-name--date report__num">{{ $approvalDate !== '' ? $approvalDate : '—' }}</div>
    </div>
    <div class="report__person">
      <div class="report__person-role">{{ $isEnDoc ? 'Supervisor signature:' : 'توقيع المشرف المدير :' }}</div>
      <div class="report__person-name" aria-hidden="true">&nbsp;</div>
      <div class="report__signature-caption">{{ $signCaption }}</div>
      <div class="report__signature-line" aria-hidden="true"></div>
    </div>
  </div>

  <footer class="report__footer">
    <div class="report__divider" aria-hidden="true"><span class="report__divider-mark"></span></div>
  </footer>
</article>
