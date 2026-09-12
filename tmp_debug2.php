<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Localization\LocalizedPresenter;
use App\Services\Localization\TranslationService;
use App\Services\Localization\SourceLanguage;
use App\Models\ReportSheetRender;

\Illuminate\Support\Facades\Cache::flush();
LocalizedPresenter::flushRequestCache();

$report = \App\Models\Report::find(1);
$render = ReportSheetRender::where('report_id',1)->orderByDesc('generation_no')->first();
$obs = $render->payload['observations'][0];
echo "obs: $obs\n";
$svc = app(TranslationService::class);
echo "shouldTranslate: ".($svc->shouldTranslate($obs)?'YES':'NO')."\n";
echo "detect: ".SourceLanguage::detect($obs)."\n";
echo "state en: ".$svc->translationState('report',1,"observation:0",$obs,'en')."\n";
echo "resolveStored en: ".var_export($svc->resolveStored('report',1,"observation:0",$obs,'en'), true)."\n";
$presenter = app(LocalizedPresenter::class);
LocalizedPresenter::flushRequestCache();
$textEn = $presenter->text('report',1,"observation:0",$obs,'en');
echo "presenter text en: $textEn\n";
echo "presenter text en length: ".mb_strlen($textEn)."\n";
echo "expected placeholder en: ".(__('ui.note_translation_preparing', [], 'en'))."\n";
echo "placeholder en file: Translation is being prepared.\n";

LocalizedPresenter::flushRequestCache();
$textAr = $presenter->text('report',1,"observation:0",$obs,'ar');
echo "presenter text ar: $textAr\n";

echo "\n--- Check reportPayload direct ---\n";
LocalizedPresenter::flushRequestCache();
$data = ['observations'=>$render->payload['observations'], 'recommendations'=>$render->payload['recommendations']];
$loc = $presenter->reportPayload(1, $data, 'en');
foreach($loc['observations'] as $i=>$o) echo " loc[$i] (".mb_strlen($o)."): ".mb_substr($o,0,120)."\n";

echo "\n--- Check what cache has ---\n";
$ref = new ReflectionClass(LocalizedPresenter::class);
$prop = $ref->getProperty('requestCache');
$prop->setAccessible(true);
$cache = $prop->getValue();
print_r(array_keys($cache));
