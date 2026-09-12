<?php
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$k=$app->make(Illuminate\Contracts\Console\Kernel::class);
$k->bootstrap();
use App\Services\Localization\LocalizedPresenter;
LocalizedPresenter::flushRequestCache();
$p=app(LocalizedPresenter::class);
$src='التقرير اليومي — 2026-09-12';
echo 'AR->EN: '. var_export($p->text('report', 1, 'title', $src, 'en'), true) . PHP_EOL;
echo 'State EN: '. $p->translationState('report',1,'title',$src,'en') . PHP_EOL;
$srcEn='Daily Report — 2026-09-12';
echo 'EN->AR: '. var_export($p->text('report', 1, 'title', $srcEn, 'ar'), true) . PHP_EOL;
echo 'Generic date AR->EN: '. var_export($p->text('report', 999, 'title', 'التقرير اليومي — 2025-01-01', 'en'), true) . PHP_EOL;
echo 'Non-pattern should stay: '. var_export($p->text('report', 1, 'title', 'عنوان مخصص', 'en'), true) . PHP_EOL;
echo 'l10n_text EN: '. l10n_text('report', 1, 'title', $src, 'en') . PHP_EOL;
echo 'l10n_text AR: '. l10n_text('report', 1, 'title', $srcEn, 'ar') . PHP_EOL;
