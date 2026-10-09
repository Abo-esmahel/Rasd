<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$doc = [
 'dir'=>'rtl','lang'=>'ar','density'=>'balanced',
 'header'=>['ministry'=>'الجمهورية العربية السورية','ministry_sub'=>'وزارة الإعلام','doc_title'=>'التقرير اليومي'],
 'day_name'=>'الأحد','day_label'=>'اليوم','observers'=>['محمد الأحمد','سارة النجار'],
 'approval_date'=>'09/10/2026','notes_count'=>3,'report_number'=>'CAM-2026-0417','report_date'=>'09/10/2026',
 'observations'=>[
  ['title'=>'ملاحظة رقم 1','paragraphs'=>['رُصدت مجموعة من الأشخاص يتجمعون بشكل غير نظامي أمام البوابة الرئيسية للمبنى الإداري، وعند التأكد من هوياتهم تبيّن أن اثنين منهم لا يحملان بطاقات دخول رسمية، مما يستدعي المتابعة مع الجهات الأمنية المختصة واتخاذ الإجراءات اللازمة.'],'meta'=>['observer'=>'محمد الأحمد','start'=>'14:20','end'=>'15:05','duration'=>'45 دقيقة','camera'=>'CAM-03','floor'=>'الطابق الأرضي','has_attachments'=>true,'has_video'=>true,'has_image'=>true]],
  ['title'=>'ملاحظة رقم 2','paragraphs'=>['تم توثيق حالة إهمال في صيانة أحد أجهزة المراقبة في الممر الشمالي، حيث كانت الشاشة معطلة تماماً لفترة تتجاوز أربع ساعات قبل أن يتم الإبلاغ عن العطل.'],'meta'=>['observer'=>'سارة النجار','start'=>'09:00','end'=>'09:30','duration'=>'30 دقيقة','camera'=>'CAM-07','floor'=>'الطابق الثاني','has_attachments'=>true,'has_video'=>false,'has_image'=>true]],
  ['title'=>'ملاحظة رقم 3','paragraphs'=>['لوحظ دخول مركبة غير مسجّلة إلى موقف السيارات المخصص للموظفين بعد ساعات الدوام الرسمي، دون أن يتم اتخاذ أي إجراء من قبل إدارةsecurity.'],'meta'=>['observer'=>'محمد الأحمد','start'=>'19:45','end'=>'20:10','duration'=>'25 دقيقة','camera'=>'CAM-01','floor'=>'الموقف الخارجي','has_attachments'=>false,'has_video'=>false,'has_image'=>false]],
 ],
 'responsibles'=>[['name'=>'محمد الأحمد']],
 'recommendations'=>['items'=>['تم إبلاغ إدارة الأمن الداخلي والتعامل الفوري مع الموقف.','تم إيقاف المركبة والتحقق منdocuments المالك من خلال قسم المرور.','تم تكليف الفريق الفني بإصلاح جهاز الكاميرا المعطل في الممر الشمالي.'],'paragraphs'=>[]],
];
$html = view('reports.engine.document', ['doc' => $doc])->render();
$html = preg_replace('#src="[^"]*/report-assets/logo[^"]*"#', 'src="/report/assets/logo-report.png"', $html);
$css = file_get_contents(public_path('report/css/report-engine.css'));
file_put_contents(public_path('_diag_report.html'), '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>'.$css.'</style><style>body{margin:0;background:#ece7d9;padding:12px}</style></head><body>'.$html.'</body></html>');
echo "ok\n";
