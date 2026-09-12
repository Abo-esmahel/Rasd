<?php
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$k=$app->make(Illuminate\Contracts\Console\Kernel::class);
$k->bootstrap();
use App\Services\Localization\LocalizedPresenter;
LocalizedPresenter::flushRequestCache();
$p=app(LocalizedPresenter::class);
$srcEn='Daily Report — 2026-09-12';
$t=trim($srcEn);
var_dump($t);
var_dump(preg_match('/^Daily Report\s*[—\-]\s*(\d{4}-\d{2}-\d{2})$/', $t, $m), $m);
echo 'text EN->AR: '. var_export($p->text('report', 2, 'title', $srcEn, 'ar'), true) . PHP_EOL;

LocalizedPresenter::flushRequestCache();
$p2=app(LocalizedPresenter::class);
echo 'non-pattern en with fresh id: '. var_export($p2->text('report', 9999, 'title', 'عنوان مخصص', 'en'), true) . PHP_EOL;
echo 'state non-pattern: '. $p2->translationState('report',9999,'title','عنوان مخصص','en') . PHP_EOL;
