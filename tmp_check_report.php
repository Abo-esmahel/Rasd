<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Report;
use App\Models\ReportSheetRender;
use App\Models\ContentTranslation;
use App\Services\Localization\LocalizedPresenter;
use App\Services\Localization\TranslationService;
use Illuminate\Support\Facades\Cache;

$reports = Report::whereDate('report_date','2026-09-12')->with(['notes','author'])->get();
echo "count=". $reports->count()."\n";
foreach($reports as $r){
  echo "\n=== Report ID {$r->id} ===\n";
  echo "title: {$r->title}\n";
  echo "status: {$r->status}\n";
  echo "content len: ".mb_strlen($r->content)."\n";
  echo "summary: ".substr($r->summary??'',0,100)."\n";
  echo "recommendations: ".substr($r->recommendations??'',0,100)."\n";
  echo "notes: ".$r->notes->count()."\n";
  foreach($r->notes as $n){
    echo " note {$n->id} desc: ".mb_substr($n->description,0,80)." ...\n";
  }
  $render = ReportSheetRender::where('report_id',$r->id)->orderByDesc('generation_no')->first();
  if($render){
    echo "render payload_hash: {$render->payload_hash}\n";
    echo "render template: {$render->template}\n";
    echo "payload: ".json_encode($render->payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    $presenter = app(LocalizedPresenter::class);
    $svc = app(TranslationService::class);
    // try to get localized payload for en
    $systemData = app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($r);
    echo "systemData observations: ".json_encode($systemData['observations'], JSON_UNESCAPED_UNICODE)."\n";
    $loc = $presenter->reportPayload($r->id, $systemData, 'en');
    echo "localized en observations: ".json_encode($loc['observations'], JSON_UNESCAPED_UNICODE)."\n";
    echo "localized en recommendations: ".$loc['recommendations']."\n";
    // check content_translations
    $cts = ContentTranslation::where('translatable_type','report')->where('translatable_id', $r->id)->get();
    echo "content_translations for report {$r->id}: ".$cts->count()."\n";
    foreach($cts as $ct){
      echo "  field={$ct->field} locale={$ct->locale} hash=".substr($ct->source_hash,0,8)." txt=".mb_substr($ct->translated_text,0,80)."\n";
    }
    // check note translations
    foreach($r->notes as $n){
      $ct2 = ContentTranslation::where('translatable_type','note')->where('translatable_id',$n->id)->get();
      echo " note {$n->id} translations: ".$ct2->count()."\n";
      foreach($ct2 as $ct){
        echo "   {$ct->field} {$ct->locale} ".mb_substr($ct->translated_text,0,60)."\n";
      }
    }
  } else {
    echo "no render\n";
    $systemData = app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($r);
    echo "systemData: ".json_encode($systemData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
  }
}
echo "\n=== Queue jobs ===\n";
try {
  $jobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
  echo "jobs table count: $jobs\n";
  $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
  echo "failed_jobs: $failed\n";
  if($jobs>0){
    $js = \Illuminate\Support\Facades\DB::table('jobs')->limit(5)->get();
    foreach($js as $j){
      echo " job: ".substr($j->payload,0,300)."\n";
    }
  }
} catch(Throwable $e){ echo "no jobs table? ".$e->getMessage()."\n";}

echo "\n=== Cache check ===\n";
try{
  $keys = Cache::get('test');
  echo "cache works\n";
}catch(Throwable $e){ echo "cache error ".$e->getMessage()."\n";}

echo "\n=== AI config ===\n";
echo "AI_ENABLED=".config('ai.enabled')."\n";
echo "GEMINI_KEY set? ".(trim(config('ai.gemini.api_key'))!==''?'yes':'no')."\n";
echo "locale app: ".app()->getLocale()."\n";
