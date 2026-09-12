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

$enObsMap = [
  "كاميرا 14 • طابق 5 • 06:15 — إضاءة ممر الطابق الخامس مطفأة بالكامل والرؤية شبه معدومة في المقطع الأوسط. تم التحقق من لوحة القواطع وإبلاغ الصيانة التي أعادت التيار خلال ساعة." => "Camera 14 • Floor 5 • 06:15 — Lighting in the Floor 5 corridor was completely off and visibility was almost zero in the middle section. The breaker panel was checked and maintenance was notified, restoring power within an hour.",
  "كاميرا 8 • طابق 3 • 07:30 — انقطاع متقطع في بث الكاميرا الثامنة بالطابق الثالث مع تجمد الصورة لثوانٍ متكررة أثناء المتابعة الصباحية. تم فحص التوصيلات ووحدة التغذية واستقر البث بعد إعادة التثبيت." => "Camera 8 • Floor 3 • 07:30 — Intermittent feed disruption on Camera 8 on Floor 3 with repeated image freezing for several seconds during morning monitoring. Connections and power supply were inspected and the feed stabilized after re-securing.",
];
$plainMap = [
  "إضاءة ممر الطابق الخامس مطفأة بالكامل والرؤية شبه معدومة في المقطع الأوسط. تم التحقق من لوحة القواطع وإبلاغ الصيانة التي أعادت التيار خلال ساعة." => "Lighting in the Floor 5 corridor was completely off and visibility was almost zero in the middle section. The breaker panel was checked and maintenance was notified, restoring power within an hour.",
  "انقطاع متقطع في بث الكاميرا الثامنة بالطابق الثالث مع تجمد الصورة لثوانٍ متكررة أثناء المتابعة الصباحية. تم فحص التوصيلات ووحدة التغذية واستقر البث بعد إعادة التثبيت." => "Intermittent feed disruption on Camera 8 on Floor 3 with repeated image freezing for several seconds during morning monitoring. Connections and power supply were inspected and the feed stabilized after re-securing."
];
$enReco = "1) Prioritize field follow-up to address: Floor 5 corridor lighting outage, verified closed within 48 hours.\n2) Schedule an additional inspection round for the above locations during the evening shift and document with photos.\n3) Archive attached footage with this report for future recurrence evaluation.";

$reports = Report::where('title','التقرير اليومي — 2026-09-12')->get();
foreach($reports as $report){
  echo "\n=== Fix Report {$report->id} ===\n";
  $render = ReportSheetRender::where('report_id',$report->id)->orderByDesc('generation_no')->first();
  if(!$render){
    echo " no render, skipping\n";
    continue;
  }
  $payload = $render->payload;
  $observations = $payload['observations'] ?? [];
  foreach($observations as $i=>$ar){
    $en = $enObsMap[$ar] ?? null;
    if(!$en){
      // Try plain map via without prefix? If not in map, try to find plain version inside
      foreach($plainMap as $plainAr=>$plainEn){
        if(str_contains($ar, $plainAr)){
          $en = str_replace($plainAr, $plainEn, $ar);
          // The prefixed version's English should keep the prefix translated
          // Already handled by enObsMap
        }
      }
    }
    if(!$en){
      echo "  no en for obs $i: ".mb_substr($ar,0,40)." skipping\n";
      continue;
    }
    $field = "observation:$i";
    $hash = TranslationService::sourceHash($ar);
    $ck = TranslationService::cacheKey('report',$report->id,$field,'en',$hash);
    ContentTranslation::updateOrCreate(
      ['translatable_type'=>'report','translatable_id'=>$report->id,'field'=>$field,'locale'=>'en','source_hash'=>$hash],
      ['source_text'=>mb_substr($ar,0,20000),'translated_text'=>$en]
    );
    Cache::put($ck, $en, now()->addDays(30));
    echo "  fixed $field\n";
  }
  // Recommendations
  $arReco = $payload['recommendations'] ?? '';
  if(trim($arReco) !== ''){
    $hash = TranslationService::sourceHash($arReco);
    $ck = TranslationService::cacheKey('report',$report->id,'recommendations','en',$hash);
    ContentTranslation::updateOrCreate(
      ['translatable_type'=>'report','translatable_id'=>$report->id,'field'=>'recommendations','locale'=>'en','source_hash'=>$hash],
      ['source_text'=>mb_substr($arReco,0,20000),'translated_text'=>$enReco]
    );
    Cache::put($ck, $enReco, now()->addDays(30));
    echo "  fixed recommendations\n";
  }
  // Title
  $titleAr = $report->title;
  $titleEn = "Daily Report — 2026-09-12";
  $hash = TranslationService::sourceHash($titleAr);
  $ck = TranslationService::cacheKey('report',$report->id,'title','en',$hash);
  ContentTranslation::updateOrCreate(
    ['translatable_type'=>'report','translatable_id'=>$report->id,'field'=>'title','locale'=>'en','source_hash'=>$hash],
    ['source_text'=>$titleAr,'translated_text'=>$titleEn]
  );
  Cache::put($ck, $titleEn, now()->addDays(30));
  echo "  fixed title\n";

  // Also fix plain systemData for fallback
  $systemData = app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($report);
  foreach($systemData['observations'] as $i=>$ar){
    $en = $plainMap[$ar] ?? null;
    if(!$en) continue;
    $field="observation:$i";
    $hash=TranslationService::sourceHash($ar);
    $ck=TranslationService::cacheKey('report',$report->id,$field,'en',$hash);
    // Don't overwrite if already exists for this hash (prefixed previously inserted with same field but different hash)
    // For plain, field same but hash different, so it's separate row
    $exists = ContentTranslation::where('translatable_type','report')->where('translatable_id',$report->id)->where('field',$field)->where('locale','en')->where('source_hash',$hash)->first();
    if(!$exists){
      ContentTranslation::create([
        'translatable_type'=>'report','translatable_id'=>$report->id,'field'=>$field,'locale'=>'en','source_hash'=>$hash,'source_text'=>mb_substr($ar,0,20000),'translated_text'=>$en
      ]);
      Cache::put($ck,$en, now()->addDays(30));
      echo "  fixed plain observation:$i\n";
    }
  }

  // Verify
  LocalizedPresenter::flushRequestCache();
  $presenter = app(LocalizedPresenter::class);
  $loc = $presenter->reportPayload($report->id, $payload, 'en');
  echo "  verify loc obs0: ".mb_substr($loc['observations'][0]??'',0,50)." (hasAR=". (preg_match('/[\x{0600}-\x{06FF}]/u',$loc['observations'][0]??'')?'YES':'NO').")\n";
  echo "  loc title: ".l10n_text('report',$report->id,'title',$titleAr,'en')."\n";
}
echo "\nAll fixed. Flushing caches.\n";
Cache::flush();
LocalizedPresenter::flushRequestCache();
\Illuminate\Support\Facades\Artisan::call('view:clear');
echo \Illuminate\Support\Facades\Artisan::output();
