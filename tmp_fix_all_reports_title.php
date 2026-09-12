<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Report;
use App\Models\ContentTranslation;
use App\Services\Localization\TranslationService;
use Illuminate\Support\Facades\Cache;
use App\Services\Localization\LocalizedPresenter;
use App\Models\ReportSheetRender;

$reports = Report::where('title','التقرير اليومي — 2026-09-12')->get();
echo "Found ". $reports->count() ." reports with that title\n";
foreach($reports as $report){
  echo "\n--- Report ID {$report->id} status {$report->status} ---\n";
  $title = $report->title;
  $hash = TranslationService::sourceHash($title);
  $cacheKey = TranslationService::cacheKey('report',$report->id,'title','en',$hash);
  $existing = ContentTranslation::where('translatable_type','report')->where('translatable_id',$report->id)->where('field','title')->where('locale','en')->first();
  if($existing){
    echo " existing title translation: ".mb_substr($existing->translated_text,0,60)." hash=".substr($existing->source_hash,0,8)."\n";
  } else {
    echo " NO title translation, inserting...\n";
    ContentTranslation::updateOrCreate(
      ['translatable_type'=>'report','translatable_id'=>$report->id,'field'=>'title','locale'=>'en','source_hash'=>$hash],
      ['source_text'=>mb_substr($title,0,20000),'translated_text'=>"Daily Report — 2026-09-12"]
    );
    Cache::put($cacheKey, "Daily Report — 2026-09-12", now()->addDays(30));
    echo " inserted\n";
  }
  // Verify l10n_text
  LocalizedPresenter::flushRequestCache();
  $presenter = app(LocalizedPresenter::class);
  $textEn = $presenter->text('report',$report->id,'title',$title,'en');
  echo " presenter text en: $textEn\n";
  echo " l10n_text helper: ".l10n_text('report',$report->id,'title',$title,'en')."\n";

  // Also check payload observations for this report
  $render = ReportSheetRender::where('report_id',$report->id)->orderByDesc('generation_no')->first();
  if($render){
    $payload = $render->payload;
    echo " payload obs count: ".count($payload['observations']??[])."\n";
    $presenter2 = app(LocalizedPresenter::class);
    LocalizedPresenter::flushRequestCache();
    $loc = $presenter2->reportPayload($report->id, $payload, 'en');
    echo " loc obs[0]: ".mb_substr($loc['observations'][0]??'',0,80)."\n";
    // Check if any still Arabic
    foreach($loc['observations'] as $i=>$o){
      $hasArabic = preg_match('/[\x{0600}-\x{06FF}]/u', $o) ? 'HAS_AR' : 'EN';
      echo "  obs $i: $hasArabic ".mb_substr($o,0,60)."\n";
    }
  }

  // Also ensure systemData translations for fallback
  $systemData = app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($report);
  foreach($systemData['observations'] as $i=>$obs){
    $h = TranslationService::sourceHash($obs);
    $ck = TranslationService::cacheKey('report',$report->id,"observation:$i",'en',$h);
    $ex = ContentTranslation::where('translatable_type','report')->where('translatable_id',$report->id)->where('field',"observation:$i")->where('locale','en')->where('source_hash',$h)->first();
    if(!$ex){
      // Provide English translation for plain
      $plainMap = [
        "إضاءة ممر الطابق الخامس مطفأة بالكامل والرؤية شبه معدومة في المقطع الأوسط. تم التحقق من لوحة القواطع وإبلاغ الصيانة التي أعادت التيار خلال ساعة." => "Lighting in the Floor 5 corridor was completely off and visibility was almost zero in the middle section. The breaker panel was checked and maintenance was notified, restoring power within an hour.",
        "انقطاع متقطع في بث الكاميرا الثامنة بالطابق الثالث مع تجمد الصورة لثوانٍ متكررة أثناء المتابعة الصباحية. تم فحص التوصيلات ووحدة التغذية واستقر البث بعد إعادة التثبيت." => "Intermittent feed disruption on Camera 8 on Floor 3 with repeated image freezing for several seconds during morning monitoring. Connections and power supply were inspected and the feed stabilized after re-securing."
      ];
      if(isset($plainMap[$obs])){
        ContentTranslation::updateOrCreate(
          ['translatable_type'=>'report','translatable_id'=>$report->id,'field'=>"observation:$i",'locale'=>'en','source_hash'=>$h],
          ['source_text'=>mb_substr($obs,0,20000),'translated_text'=>$plainMap[$obs]]
        );
        Cache::put($ck, $plainMap[$obs], now()->addDays(30));
        echo " inserted plain observation:$i\n";
      }
    }
  }
}
echo "\nDone. Flushing cache and views.\n";
Cache::flush();
\Illuminate\Support\Facades\Artisan::call('view:clear');
echo \Illuminate\Support\Facades\Artisan::output();
