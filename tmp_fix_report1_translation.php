<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Report;
use App\Models\ReportSheetRender;
use App\Models\ContentTranslation;
use App\Services\Localization\TranslationService;
use Illuminate\Support\Facades\Cache;
use App\Services\Localization\LocalizedPresenter;

$report = Report::find(1);
if(!$report){ echo "report 1 not found\n"; exit; }
$render = ReportSheetRender::where('report_id',1)->orderByDesc('generation_no')->first();
if(!$render){ echo "no render\n"; exit; }

$payload = $render->payload;
echo "Payload observations:\n";
foreach($payload['observations'] as $i=>$obs) echo " [$i] $obs\n";
echo "Recommendations: ".$payload['recommendations']."\n";
echo "Title: ".$report->title."\n";

// Prepare translations
$translations = [];

// Title
$translations[] = [
  'type'=>'report',
  'id'=>1,
  'field'=>'title',
  'source'=>$report->title,
  'translated'=>"Daily Report — 2026-09-12"
];

// Observations - map with prefix
$enObs = [
  0 => "Camera 14 • Floor 5 • 06:15 — Lighting in the Floor 5 corridor was completely off and visibility was almost zero in the middle section. The breaker panel was checked and maintenance was notified, restoring power within an hour.",
  1 => "Camera 8 • Floor 3 • 07:30 — Intermittent feed disruption on Camera 8 on Floor 3 with repeated image freezing for several seconds during morning monitoring. Connections and power supply were inspected and the feed stabilized after re-securing."
];

foreach($payload['observations'] as $i=>$obs){
  if(isset($enObs[$i])){
    $translations[] = [
      'type'=>'report',
      'id'=>1,
      'field'=>"observation:$i",
      'source'=>$obs,
      'translated'=>$enObs[$i]
    ];
  }
}

// Recommendations
$enReco = "1) Prioritize field follow-up to address: Floor 5 corridor lighting outage, verified closed within 48 hours.\n2) Schedule an additional inspection round for the above locations during the evening shift and document with photos.\n3) Archive attached footage with this report for future recurrence evaluation.";
$translations[] = [
  'type'=>'report',
  'id'=>1,
  'field'=>'recommendations',
  'source'=>$payload['recommendations'],
  'translated'=>$enReco
];

// Also need to handle report content/summary translations? But reportPayload only uses observations+recommendations, so fine.
// Also translate plain systemData observations for fallback? The report show also uses systemData for editor fallback? But primary is payload.
// To be safe, also translate systemData plain observations (without prefix) for report 1
$systemData = app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($report);
echo "\nSystemData observations:\n";
foreach($systemData['observations'] as $i=>$obs) echo " [$i] $obs\n";
// For plain, provide translations too
$plainEn = [
  0 => "Lighting in the Floor 5 corridor was completely off and visibility was almost zero in the middle section. The breaker panel was checked and maintenance was notified, restoring power within an hour.",
  1 => "Intermittent feed disruption on Camera 8 on Floor 3 with repeated image freezing for several seconds during morning monitoring. Connections and power supply were inspected and the feed stabilized after re-securing."
];
foreach($systemData['observations'] as $i=>$obs){
  if(isset($plainEn[$i])){
    $translations[] = [
      'type'=>'report',
      'id'=>1,
      'field'=>"observation:$i",
      'source'=>$obs,
      'translated'=>$plainEn[$i]
    ];
  }
}
// But note: field observation:0 for plain vs prefixed will have same field name but different source_hash, so they are distinct translations. Both need to be stored.

// Insert
foreach($translations as $t){
  $hash = TranslationService::sourceHash($t['source']);
  $cacheKey = TranslationService::cacheKey($t['type'], $t['id'], $t['field'], 'en', $hash);
  echo "\nInserting {$t['field']} hash=".substr($hash,0,8)." source=".mb_substr($t['source'],0,40)." -> ".mb_substr($t['translated'],0,40)."\n";
  try{
    ContentTranslation::updateOrCreate(
      [
        'translatable_type'=>$t['type'],
        'translatable_id'=>$t['id'],
        'field'=>$t['field'],
        'locale'=>'en',
        'source_hash'=>$hash,
      ],
      [
        'source_text'=>mb_substr($t['source'],0,20000),
        'translated_text'=>$t['translated']
      ]
    );
    Cache::put($cacheKey, $t['translated'], now()->addDays(30));
    echo "  inserted OK\n";
  } catch(Throwable $e){
    echo "  failed: ".$e->getMessage()."\n";
  }
}

// Clear presenter cache
LocalizedPresenter::flushRequestCache();
Cache::forget('ai:ctx:2026-09-12:1');

echo "\n=== Verify ===\n";
$presenter = app(LocalizedPresenter::class);
$svc = app(TranslationService::class);
foreach($payload['observations'] as $i=>$obs){
  $stored = $svc->resolveStored('report',1,"observation:$i",$obs,'en');
  echo " observation:$i stored: ".($stored?mb_substr($stored,0,60):'NULL')."\n";
  $text = $presenter->text('report',1,"observation:$i",$obs,'en');
  echo "  presenter text en: ".mb_substr($text,0,80)."\n";
}
$loc = $presenter->reportPayload(1, $payload, 'en');
echo "reportPayload en observations:\n";
foreach($loc['observations'] as $i=>$o) echo " [$i] ".mb_substr($o,0,100)."\n";
echo "reco en: ".mb_substr($loc['recommendations'],0,120)."\n";

echo "\nDone. Clear cache.\n";
Cache::flush();
LocalizedPresenter::flushRequestCache();
