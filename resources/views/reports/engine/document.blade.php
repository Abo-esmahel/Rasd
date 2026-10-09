
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
  // Official form heading is «التوصيات» — the AI narrative title (if any) is dropped
// so the paper matches the ministry model instead of echoing prompt wording.
  // Document label resolver: engine-provided value wins, lang file is the fallback.
  $label = fn (string $key, ?string $from = null) => $from !== null && $from !== '' ? $from : __("report.{$key}", [], $docLang);
  $recoTitle = $label('recommendations');
  $signCaption = $doc['signature_caption'] ?? __('report.signature', [], $docLang);
  $reportNumber = trim((string) ($doc['report_number'] ?? ($doc['meta'][0]['value'] ?? '')));
  $reportDate = trim((string) ($doc['report_date'] ?? ($doc['meta'][1]['value'] ?? ($doc['meta'][0]['value'] ?? ''))));
  // When meta holds only one cell (number xor date), disambiguate via labels.
  if (($doc['report_number'] ?? null) === null || ($doc['report_date'] ?? null) === null) {
    foreach (($doc['meta'] ?? []) as $cell) {
      $lbl = (string) ($cell['label'] ?? '');
      $val = (string) ($cell['value'] ?? '');
      if (trim($val) === '') { continue; }
      if (in_array($lbl, ['رقم التقرير', 'Report Number'], true)) { $reportNumber = trim($val); }
      if (in_array($lbl, ['التاريخ', 'Date'], true)) { $reportDate = trim($val); }
    }
  }
  $dayName = trim((string) ($doc['day_name'] ?? ''));
  $dayLabel = $doc['day_label'] ?? __('report.day', [], $docLang);
  $approvalDate = trim((string) ($doc['approval_date'] ?? ''));
  $notesCount = (int) ($doc['notes_count'] ?? count($observations));
  $monitorName = '';
  if (!empty($responsibles) && isset($responsibles[0]['name'])) { $monitorName = trim((string) $responsibles[0]['name']); }
  $observers = array_values(array_filter(array_map(fn($n) => trim((string) $n), (array) ($doc['observers'] ?? [])), fn($n) => $n !== '' && $n !== '—'));
  $observersText = implode($isEnDoc ? ', ' : '، ', $observers);
  $dash = fn($v) => trim((string) ($v ?? '')) !== '' ? $v : '—';
  // Cache-bust the emblem by file mtime so replacing the asset takes effect immediately.
  $logoFile = public_path('report/assets/logo-report.png');
  $logoUrl = route('report.logo') . (is_file($logoFile) ? '?v=' . filemtime($logoFile) : '');
  $engine = app(\App\Services\Report\ReportEngine::class);
@endphp
<article class="report" dir="{{ $docDir }}" lang="{{ $docLang }}" data-density="{{ $density }}">
  <header class="report__header">
    <div class="report__header-side report__header-side--ministry">
      <div class="report__ministry">{{ $label('ministry_sub', $header['ministry_sub'] ?? null) }}</div>
      <div class="report__ministry-sub">{{ $label('ministry', $header['ministry'] ?? null) }}</div>
    </div>
    <div class="report__logo">
      <img src="{{ $logoUrl }}" alt="{{ $label('emblem_alt') }}" loading="eager" decoding="async">
    </div>
    <div class="report__header-side report__header-side--doc">
      <div class="report__doc-title">{{ $label('doc_title', $header['doc_title'] ?? null) }}</div>
    </div>
  </header>

  <div class="report__divider" aria-hidden="true"><span class="report__divider-mark"></span></div>

  <section class="report__infobar" aria-label="{{ $label('report_data') }}">
    <div class="report__infobar-cell">
      <span class="report__infobar-label">{{ $label('number') }} :</span>
      <span class="report__infobar-value report__num">{{ $reportNumber !== '' ? $reportNumber : '—' }}</span>
    </div>
    <div class="report__infobar-cell">
      <span class="report__infobar-label">{{ $label('date') }} :</span>
      <span class="report__infobar-value report__num">{{ $reportDate !== '' ? $reportDate : '—' }}</span>
      <span class="report__infobar-note">{{ $dayName !== '' ? $dayName : '—' }}</span>
    </div>
  </section>

  <div class="report__body">
    <section class="report__section report__section--observations" aria-label="{{ $label('observations') }}">
      @if(empty($observations))
        <p class="report__empty">{{ $label('observations_empty') }}</p>
      @else
      <ol class="report__observations">
        @foreach($observations as $idx => $obs)
@php
        // Official form numbering (Arabic ordinals) comes from the report engine.
        $obsTitle = $obs['title'] ?? $engine->observationTitle($idx + 1, $docLang);
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
            <h3 class="report__observation-title">{{ $obsTitle }}</h3>
            @if($hasMeta && trim((string) ($m['observer'] ?? '')) !== '')
            <span class="report__obs-monitor">
              <span class="report__obs-label">{{ $label('officer') }}:</span>
              <span class="report__obs-monitor-value" data-no-translate>{{ $m['observer'] }}</span>
            </span>
            @endif
          </div>
          @foreach(($obs['paragraphs'] ?? []) as $paragraph)
          <p class="report__observation-body">{{ $paragraph }}</p>
          @endforeach
          @if($hasMeta)
          <dl class="report__obs-meta">
            <div class="report__obs-field"><dt class="report__obs-label">{{ $label('start') }}</dt><dd class="report__obs-value report__num">{{ $dash($m['start'] ?? '') }}</dd></div>
            <div class="report__obs-field"><dt class="report__obs-label">{{ $label('end') }}</dt><dd class="report__obs-value report__num">{{ $dash($m['end'] ?? '') }}</dd></div>
            <div class="report__obs-field"><dt class="report__obs-label">{{ $label('duration') }}</dt><dd class="report__obs-value report__num">{{ $dash($m['duration'] ?? '') }}</dd></div>
            <div class="report__obs-field"><dt class="report__obs-label">{{ $label('camera') }}</dt><dd class="report__obs-value report__num">{{ $dash($m['camera'] ?? '') }}</dd></div>
            <div class="report__obs-field"><dt class="report__obs-label">{{ $label('floor') }}</dt><dd class="report__obs-value report__num">{{ $dash($m['floor'] ?? '') }}</dd></div>
          </dl>
          <div class="report__obs-attach">
            <span class="report__obs-label">{{ $label('attachments') }}</span>
            <span class="report__checks">
              <span class="report__check"><span class="report__box{{ $yesOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($yesOn)✓@endif</span> {{ $label('yes') }}</span>
              <span class="report__check"><span class="report__box{{ $noOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($noOn)✓@endif</span> {{ $label('no') }}</span>
              <span class="report__check"><span class="report__box{{ $vidOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($vidOn)✓@endif</span> {{ $label('video') }}</span>
              <span class="report__check"><span class="report__box{{ $imgOn ? ' report__box--on' : '' }}" aria-hidden="true">@if($imgOn)✓@endif</span> {{ $label('photo') }}</span>
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
      <div class="report__panel">
      @if($observersText !== '')
      <div class="report__monitor">
        <span class="report__monitor-label">{{ $label('monitoring_officers') }}</span>
        <span class="report__monitor-value" data-no-translate>{{ $observersText }}</span>
      </div>
      @elseif($monitorName !== '' && $monitorName !== '—')
      <div class="report__monitor">
        <span class="report__monitor-label">{{ $label('monitoring_officer') }}</span>
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
      </div>
    </section>
    @endif
  </div>

  <div class="report__sign">
    <div class="report__person">
      <div class="report__person-role">{{ $label('responsible_officer') }}</div>
      <div class="report__person-name" data-no-translate>{{ $monitorName !== '' ? $monitorName : '' }}</div>
      <div class="report__signature-line" aria-hidden="true"></div>
    </div>
    <div class="report__person report__person--date">
      <div class="report__person-role">{{ $label('approval_date') }}</div>
      <div class="report__person-name report__person-name--date report__num">{{ $approvalDate !== '' ? $approvalDate : '—' }}</div>
    </div>
    <div class="report__person">
      <div class="report__person-role">{{ $signCaption }}</div>
      <div class="report__person-name" aria-hidden="true">&nbsp;</div>
      <div class="report__signature-line" aria-hidden="true"></div>
    </div>
  </div>

  <footer class="report__footer">
    <div class="report__divider" aria-hidden="true"><span class="report__divider-mark"></span></div>
  </footer>
</article>
