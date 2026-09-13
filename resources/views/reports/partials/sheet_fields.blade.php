
@if($sheet['fields'])
    <div class="sh-field" data-fs="1.5" style="left:{{ $shCfg['no_rect']['x'] }}%;top:{{ $shCfg['fields_top'] }}%;width:{{ $shCfg['no_rect']['w'] }}%;height:{{ $shCfg['fields_h'] }}%;">{{ $report->id }}</div>
    <div class="sh-field" data-fs="1.5" style="left:{{ $shCfg['date_rect']['x'] }}%;top:{{ $shCfg['fields_top'] }}%;width:{{ $shCfg['date_rect']['w'] }}%;height:{{ $shCfg['fields_h'] }}%;">{{ $report->report_date->toDateString() }}</div>
@endif
@foreach($shNotes as $i => $n)
    @php [$bTop, $bFirst, $bBottom] = $sheet['boxes'][$i]; @endphp
    
    <div class="sh-note sh-fit" data-pitch="{{ $sheet['pitch'] }}" style="left:{{ $shCfg['note_rect']['x'] }}%;top:{{ round($bFirst - 1.3, 2) }}%;width:{{ $shCfg['note_rect']['w'] }}%;height:{{ round($bBottom - $bFirst + 0.9, 2) }}%;">{{ __('ui.camera') }} {{ $n->camera_number }} • {{ __('ui.floor') }} {{ $n->floor_number }} • {{ $n->observed_at?->format('H:i') }}<br>{{ l10n_text('note', $n->id, 'description', $n->description) }}</div>
@endforeach
@if(trim((string) $report->recommendations) !== '')
    <div class="sh-note sh-fit" data-pitch="{{ $shCfg['reco_pitch'] }}" style="left:{{ $shCfg['reco_rect']['x'] }}%;top:{{ round($shCfg['reco_first'] - 1.3, 2) }}%;width:{{ $shCfg['reco_rect']['w'] }}%;height:{{ round($shCfg['reco_bottom'] - $shCfg['reco_first'] + 0.9, 2) }}%;">{{ l10n_text('report', $report->id, 'recommendations', $report->recommendations) }}</div>
@endif
<div class="sh-field" data-fs="1.5" style="left:{{ $shCfg['sig_right']['x'] }}%;top:{{ $shCfg['sig_top'] }}%;width:{{ $shCfg['sig_right']['w'] }}%;height:{{ $shCfg['sig_h'] }}%;">{{ $report->author->name ?? '' }}</div>
