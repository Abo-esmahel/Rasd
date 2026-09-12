<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Report;
use App\Models\ReportSheetRender;
use App\Services\Localization\LocalizedPresenter;
use App\Services\Localization\TranslationService;
use App\Services\Localization\SourceLanguage;

$report = Report::find(1);
$render = ReportSheetRender::where('report_id',1)->orderByDesc('generation_no')->first();
$payload = $render->payload;
echo "payload observations:\n";
foreach($payload['observations'] as $i=>$obs){
  echo " [$i] ".mb_substr($obs,0,80)." len=".mb_strlen($obs)."\n";
  $svc = app(TranslationService::class);
  $should = $svc->shouldTranslate($obs) ? 'YES' : 'NO';
  $lang = SourceLanguage::detect($obs);
  $state = $svc->translationState('report',1,"observation:$i",$obs,'en');
  echo "  shouldTranslate=$should lang=$lang state=$state\n";
  $stored = $svc->resolveStored('report',1,"observation:$i",$obs,'en');
  echo "  stored: ".($stored===null?'NULL':mb_substr($stored,0,60))."\n";
  $presenter = app(LocalizedPresenter::class);
  $text = $presenter->text('report',1,"observation:$i",$obs,'en');
  echo "  presenter text: ".mb_substr($text,0,80)." \n";
}
echo "\nTitle:\n";
$title = $report->title;
$svc = app(TranslationService::class);
echo " title: $title\n";
echo " should=".($svc->shouldTranslate($title)?'YES':'NO')." lang=".SourceLanguage::detect($title)." state=".$svc->translationState('report',1,'title',$title,'en')."\n";
echo " stored title: ".var_export($svc->resolveStored('report',1,'title',$title,'en'), true)."\n";
echo " presenter title en: ".app(LocalizedPresenter::class)->text('report',1,'title',$title,'en')."\n";

echo "\n--- Direct reportPayload test ---\n";
$presenter = app(LocalizedPresenter::class);
$data = ['observations'=>$payload['observations'], 'recommendations'=>$payload['recommendations']];
$loc = $presenter->reportPayload(1, $data, 'en');
echo "loc observations:\n";
foreach($loc['observations'] as $i=>$o) echo " [$i] ".mb_substr($o,0,100)."\n";
echo "loc reco: ".mb_substr($loc['recommendations'],0,100)."\n";

echo "\n--- Check content_translations table ---\n";
$cts = \App\Models\ContentTranslation::where('translatable_type','report')->where('translatable_id',1)->get();
foreach($cts as $ct){
  echo " field={$ct->field} locale={$ct->locale} hash=".substr($ct->source_hash,0,8)." source=".mb_substr($ct->source_text,0,60)." -> ".mb_substr($ct->translated_text,0,60)."\n";
}
if($cts->isEmpty()) echo " none\n";
