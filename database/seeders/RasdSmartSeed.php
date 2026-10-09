<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\Note;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportSheetRender;
use App\Models\User;
use App\Notifications\DispatchAcceptedNotification;
use App\Notifications\DispatchRejectedNotification;
use App\Notifications\DispatchSentNotification;
use App\Notifications\NoteAcceptedNotification;
use App\Notifications\NoteRejectedNotification;
use App\Notifications\NoteSentNotification;
use App\Notifications\ReportPublishedNotification;
use App\Services\Report\ReportEngine;
use App\Services\ReportPreview\ReportDataBuilder;
use App\Services\ReportPreview\ReportHtmlRenderingService;
use App\Services\ReportPreview\ReportRenderPayload;
use App\Services\ReportPreview\ReportTemplateSelector;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * البذرة الذكية لنظام رصد — تُبنى بعد فهم كامل للسياسات:
 *
 *  الامتثال الصارم (يُتحقق منه آلياً في نهاية التشغيل):
 *  - reports.report_date فريد (تقرير واحد يومياً) — migration 000002.
 *  - report_note.note_id فريد (الملاحظة لتقرير واحد فقط).
 *  - كل تقرير منشور: ملاحظاته كلها مقبولة ومن نفس يومه (publish)،
 *    ولا معلّق ولا مقبولة غير مرفقة في يومه — وإلا فشل النشر بالنظام.
 *  - المقبولة/المرفوضة: processed_by كاتب تقارير + سبب رفض ≥ 5 أحرف.
 *  - الإنشاء حصراً للمراقبين، والقبول/الرفض حصراً لكاتب مستلم.
 *  - المرفقات PNG حقيقية مولدة محلياً (image/png مسموح) بأحجام سليمة.
 *  - الإشعارات بنفس بناة النظام وبنفس مستلمي الـ Fanout.
 *
 *  خارطة الأيام (نسبة لآن التشغيل):
 *  - اليوم: تقرير منشور + 5 مقبولة مرفقة + 7 مسودات + 2 مرفوضة (لا معلّق).
 *  - أمس: 10 معلّقة بلا تقرير (عرض مسار الإنشاء المحظور).
 *  - قبل يومين/ثلاثة: مسودتا تقرير قابلتين للنشر.
 *  - قبل 5 أيام: مسودة متروكة فارغة. قبل 4 أيام: يوم حر فارغ.
 *  - تقارير منشورة قديمة: 10 أيام × ملاحظتين من نفس اليوم.
 *
 *  IMPORTANT: لا يمسّ الكاش أبداً (cache / cache_locks).
 */
class RasdSmartSeed extends Seeder
{
    public function run(): void
    {
        $now = now();
        $disk = Storage::disk('attachments');

        $writers = User::where('role', 'report_writer')->orderBy('id')->get();
        $monitors = User::where('role', 'monitor')->orderBy('id')->get();
        $gm = User::where('role', 'general_manager')->orderBy('id')->first();
        if ($writers->isEmpty() || $monitors->count() < 4 || !$gm) {
            throw new \RuntimeException('SEED ABORT: need >=4 monitors + 1 writer + 1 GM — run DatabaseSeeder first');
        }
        $mainWriter = $writers->firstWhere('username', 'writer') ?? $writers->first();
        $monitorByUsername = $monitors->keyBy('username');
        $m = fn(string $username, int $fallback) => $monitorByUsername->get($username)?->id ?? $monitors->firstWhere('id', $fallback)?->id ?? $monitors->first()->id;

        $this->wipeExisting($disk);

        $dayAt = fn(int $daysAgo, int $h, int $min) => $now->copy()->subDays($daysAgo)->setTime($h, $min, 0);
        $todayStr = $now->toDateString();

        $allNotes = [];
        $allReports = [];
        $allSubmissions = [];
        $files = 0;

        $createdNotes = $this->seedOldAcceptedNotes($m, $mainWriter, $dayAt, $disk, $files);
        $allNotes = array_merge($allNotes, $createdNotes);

        $createdRejected = $this->seedOldRejectedNotes($m, $mainWriter, $dayAt, $disk, $files);
        $allNotes = array_merge($allNotes, $createdRejected);

        $createdReports = $this->seedOldPublishedReports($allNotes, $mainWriter, $dayAt, $disk, $files);
        $allReports = array_merge($allReports, $createdReports);

        $createdSubs = $this->seedOldSubmissions($m, $mainWriter, $dayAt, $disk, $files);
        $allSubmissions = array_merge($allSubmissions, $createdSubs);

        $todayBundle = $this->seedTodayNotes($m, $mainWriter, $now, $todayStr, $disk, $files);
        $allNotes = array_merge($allNotes, $todayBundle);

        $pendingBundle = $this->seedYesterdayPending($m, $now, $disk, $files);
        $allNotes = array_merge($allNotes, $pendingBundle);

        $recentReport = $this->seedTodayReport($todayBundle, $mainWriter, $now, $todayStr, $disk, $files);
        if ($recentReport) $allReports[] = $recentReport;

        $draftReports = $this->seedDraftReports($m, $mainWriter, $now, $disk, $files, $allNotes);
        $allReports = array_merge($allReports, $draftReports);

        $todaySubs = $this->seedTodaySubmissions($m, $mainWriter, $now, $todayStr, $disk, $files);
        $allSubmissions = array_merge($allSubmissions, $todaySubs);

        $this->seedNotifications($allNotes, $allReports, $allSubmissions, $writers, $monitors, $gm, $mainWriter, $now);

        $this->seedEngagement($allNotes, $allReports, $recentReport, $writers, $monitors, $gm, $mainWriter, $now);

        $this->assertPolicyCompliance($allReports);

        $localAttachments = Attachment::get()->filter->isLocal()->count();
        $this->command->info(
            'DONE notes=' . Note::count()
            . ' reports=' . Report::count()
            . ' submissions=' . GeneralSubmission::count()
            . ' notif=' . DatabaseNotification::count()
            . ' reactions=' . Reaction::count()
            . ' comments=' . ReportComment::count()
            . " local_attachments=$localAttachments"
        );
    }

    private function wipeExisting($disk): void
    {
        $noteIds = Note::pluck('id')->all();
        $reportIds = Report::pluck('id')->all();
        $subIds = GeneralSubmission::pluck('id')->all();
        $files = 0;

        foreach (Attachment::whereIn('note_id', $noteIds)->get(['file_path']) as $a) {
            try { if ($a->file_path && $disk->exists($a->file_path)) { $disk->delete($a->file_path); $files++; } } catch (\Throwable) {}
        }
        foreach (GeneralSubmissionAttachment::whereIn('general_submission_id', $subIds)->get(['file_path']) as $a) {
            try { if ($a->file_path && $disk->exists($a->file_path)) { $disk->delete($a->file_path); $files++; } } catch (\Throwable) {}
        }
        try {
            foreach (Report::whereIn('id', $reportIds)->get(['ai_sheet_image_path']) as $r) {
                if (!$r->ai_sheet_image_path) continue;
                try { if ($disk->exists($r->ai_sheet_image_path)) { $disk->delete($r->ai_sheet_image_path); $files++; } } catch (\Throwable) {}
            }
            if (Schema::hasTable('report_sheet_renders')) {
                foreach (DB::table('report_sheet_renders')->whereIn('report_id', $reportIds)->get(['image_path']) as $r) {
                    if (empty($r->image_path)) continue;
                    try { if ($disk->exists($r->image_path)) { $disk->delete($r->image_path); $files++; } } catch (\Throwable) {}
                }
            }
        } catch (\Throwable) {}

        DatabaseNotification::query()->delete();

        DB::transaction(function () use ($noteIds, $reportIds, $subIds) {
            if (Schema::hasTable('report_note')) DB::table('report_note')->whereIn('report_id', $reportIds)->orWhereIn('note_id', $noteIds)->delete();
            if (Schema::hasTable('report_revisions')) DB::table('report_revisions')->whereIn('report_id', $reportIds)->delete();
            if (Schema::hasTable('report_sheet_renders')) DB::table('report_sheet_renders')->whereIn('report_id', $reportIds)->delete();
            if (Schema::hasTable('reactions')) DB::table('reactions')->delete();
            if (Schema::hasTable('report_comments')) DB::table('report_comments')->delete();
            if (Schema::hasTable('general_submission_report_writer')) DB::table('general_submission_report_writer')->whereIn('general_submission_id', $subIds)->delete();
            GeneralSubmissionAttachment::whereIn('general_submission_id', $subIds)->delete();
            GeneralSubmission::whereIn('id', $subIds)->delete();
            Attachment::whereIn('note_id', $noteIds)->delete();
            Note::whereIn('id', $noteIds)->delete();
            Report::whereIn('id', $reportIds)->delete();
        });

        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("DELETE FROM sqlite_sequence WHERE name IN ('notes','attachments','reports','report_note','report_revisions','report_sheet_renders','reactions','report_comments','general_submissions','general_submission_attachments','general_submission_report_writer','notifications')");
            }
        } catch (\Throwable) {}

        $this->command->warn('WIPE notes=' . count($noteIds) . ' reports=' . count($reportIds) . ' submissions=' . count($subIds) . " files=$files (cache untouched)");
    }

    private function seedOldAcceptedNotes(callable $m, User $writer, callable $dayAt, $disk, int &$files): array
    {
        $data = [
            ['u' => $m('tariq', 1), 'cam' => 2, 'fl' => 1, 'days' => 30, 'h' => 10, 'min' => 5, 'kind' => 'door', 'end' => null,
             'desc' => 'باب المستودع الرئيسي في الطابق الأول رُصد مفتوحاً بالكامل صباحاً ولا يوجد أي موظف في الجوار. تم إغلاقه فوراً والتحقق من سلامة القفل وإبلاغ الحارس المناوب لتوثيق الواقعة.',
             'cap' => 'باب مفتوح — المستودع الرئيسي'],
            ['u' => $m('hadi', 2), 'cam' => 5, 'fl' => 1, 'days' => 30, 'h' => 14, 'min' => 30, 'kind' => 'crowd', 'end' => 40,
             'desc' => 'ازدحام لافت في بهو الاستقبال عند المدخل الرئيسي وقت الذروة، مع صعوبة في تنظيم حركة المراجعين عند بوابة التدقيق. تم التنسيق مع الأمن لتنظيم الدخول على دفعات.',
             'cap' => 'ازدحام بهو الاستقبال'],
            ['u' => $m('hamza', 3), 'cam' => 8, 'fl' => 2, 'days' => 28, 'h' => 9, 'min' => 15, 'kind' => 'water', 'end' => null,
             'desc' => 'تسرب مياه واضح من سقف ممر الطابق الثاني أدى إلى تبلل مساحة من الأرضية وخطر انزلاق للمارة. تم وضع إشارة تحذيرية وإبلاغ الصيانة التي حضرت وعاينت الموقع.',
             'cap' => 'تسرب مياه — ممر الطابق الثاني'],
            ['u' => $m('rami', 4), 'cam' => 12, 'fl' => 3, 'days' => 28, 'h' => 11, 'min' => 0, 'kind' => 'archive', 'end' => null,
             'desc' => 'حاوية نفايات ممتلئة خلف قسم الأرشيف في الطابق الثالث منذ أكثر من يوم دون تفريغ، مع انتشار رائحة كريهة في الممر. تم إبلاغ خدمات النظافة لمعالجة فورية.',
             'cap' => 'تراكم نفايات — قسم الأرشيف'],
            ['u' => $m('tariq', 1), 'cam' => 15, 'fl' => 3, 'days' => 26, 'h' => 16, 'min' => 20, 'kind' => 'door', 'end' => null,
             'desc' => 'باب قاعة الاجتماعات الرئيسية في الطابق الثالث يعاني من خلل في المفصلة ويصدر صوتاً حاداً عند الفتح والإغلاق. يحتاج إلى صيانة عاجلة قبل تفاقم العطل.',
             'cap' => 'خلل باب — قاعة الاجتماعات'],
            ['u' => $m('hadi', 2), 'cam' => 3, 'fl' => 1, 'days' => 26, 'h' => 8, 'min' => 45, 'kind' => 'person', 'end' => null,
             'desc' => 'شخص غير معروف حاول الدخول عبر الباب الجانبي في الطابق الأول دون إبراز بطاقة هوية. أوقفه الحارس المناوب وتم اصطحابه إلى مكتب الأمن للتحقق من هويته وأسباب حضوره.',
             'cap' => 'محاولة دخول بلا هوية'],
            ['u' => $m('hamza', 3), 'cam' => 7, 'fl' => 2, 'days' => 24, 'h' => 13, 'min' => 10, 'kind' => 'bag', 'end' => 35,
             'desc' => 'حقيبة ظهر متروكة تحت أحد المقاعد في الحديقة الداخلية لأكثر من نصف ساعة دون ظهور صاحبها. تم فحصها ظاهرياً بحذر وإبلاغ الأمن الذي تحفظ عليها لحين مراجعة صاحبها.',
             'cap' => 'حقيبة متروكة — الحديقة الداخلية'],
            ['u' => $m('rami', 4), 'cam' => 1, 'fl' => 1, 'days' => 24, 'h' => 7, 'min' => 30, 'kind' => 'sign', 'end' => null,
             'desc' => 'لوحة الإرشاد عند المدخل الرئيسي مائلة بشكل واضح وتوشك على السقوط بسبب ارتخاء براغي التثبيت. تشكل خطراً على المارة وتحتاج إلى إعادة تثبيت فورية.',
             'cap' => 'لوحة إرشاد مائلة — المدخل'],
            ['u' => $m('tariq', 1), 'cam' => 10, 'fl' => 2, 'days' => 22, 'h' => 15, 'min' => 45, 'kind' => 'static', 'end' => null,
             'desc' => 'الكاميرا العاشرة في الطابق الثاني تعرض صورة متجمدة منذ ساعات الصباح مع تشويش خفيف على الأطراف. تم إبلاغ الفريق الفني لفحص التوصيلات ووحدة التغذية.',
             'cap' => 'تجمد صورة — الكاميرا 10'],
            ['u' => $m('hadi', 2), 'cam' => 14, 'fl' => 4, 'days' => 22, 'h' => 10, 'min' => 30, 'kind' => 'door', 'end' => null,
             'desc' => 'باب الطوارئ الشرقي في الطابق الرابع يصدر صوتاً حاداً عند كل حركة فتح بسبب جفاف المفاصل. يحتاج إلى تشحيم ومعايرة لمنع تفاقم الاهتراء.',
             'cap' => 'باب طوارئ — صوت مفصلة'],
            ['u' => $m('hamza', 3), 'cam' => 6, 'fl' => 1, 'days' => 20, 'h' => 12, 'min' => 0, 'kind' => 'crowd', 'end' => 25,
             'desc' => 'تجمع عدد من الموظفين حول كشك القهوة في الممر الرئيسي يعيق حركة العبور ذهاباً وإياباً. تم الطلب منهم إخلاء الممر والانتقال إلى منطقة الاستراحة المخصصة.',
             'cap' => 'تجمع يعيق الممر — كشك القهوة'],
            ['u' => $m('rami', 4), 'cam' => 18, 'fl' => 5, 'days' => 20, 'h' => 9, 'min' => 20, 'kind' => 'archive', 'end' => null,
             'desc' => 'خزائن التخزين في الطابق الخامس رُصدت مفتوحة وبعض الملفات بارزة للخارج ولا يوجد أي موظف في المنطقة. تم إغلاقها مبدئياً وإبلاغ أمين المستودع للجرد والتوثيق.',
             'cap' => 'خزائن مفتوحة — الطابق الخامس'],
            ['u' => $m('tariq', 1), 'cam' => 9, 'fl' => 2, 'days' => 18, 'h' => 14, 'min' => 0, 'kind' => 'person', 'end' => null,
             'desc' => 'شخص يركض بشكل مريب عبر ممر الطابق الثاني حاملاً حقيبة كبيرة ومتجهاً نحو مخرج الطوارئ. تم تتبعه عبر الكاميرات وإبلاغ الأمن الذي اعترضه للتحقق.',
             'cap' => 'شخص يركض — ممر الطابق الثاني'],
            ['u' => $m('hadi', 2), 'cam' => 4, 'fl' => 1, 'days' => 18, 'h' => 11, 'min' => 40, 'kind' => 'power', 'end' => 50,
             'desc' => 'انقطاع متكرر للتيار عن بوابة الدخول الإلكترونية في الطابق الأول أدى إلى ازدحام عند التحقق اليدوي من الهويات. تم التحويل إلى الإجراء اليدوي وإبلاغ الكهرباء.',
             'cap' => 'عطل بوابة دخول — تحقق يدوي'],
            ['u' => $m('hamza', 3), 'cam' => 11, 'fl' => 3, 'days' => 16, 'h' => 13, 'min' => 5, 'kind' => 'fire', 'end' => null,
             'desc' => 'انبعاث رائحة احتراق خفيفة قرب غرفة الكهرباء في الطابق الثالث مع تصاعد دخان بسيط من لوحة التوزيع. تم فصل التيار احترازياً واستدعاء فريق الصيانة فوراً.',
             'cap' => 'رائحة احتراق — غرفة الكهرباء'],
            ['u' => $m('rami', 4), 'cam' => 13, 'fl' => 4, 'days' => 16, 'h' => 16, 'min' => 10, 'kind' => 'parking', 'end' => null,
             'desc' => 'سيارة خاصة متوقفة بشكل مخالف أمام مخرج الطوارئ في ساحة الطابق الرابع تعيق أي إخلاء محتمل. تم توثيق لوحتها وإبلاغ المرور الداخلي لسحبها.',
             'cap' => 'توقف مخالف — مخرج طوارئ'],
            ['u' => $m('tariq', 1), 'cam' => 17, 'fl' => 5, 'days' => 14, 'h' => 9, 'min' => 50, 'kind' => 'corridor', 'end' => null,
             'desc' => 'كسر في زجاج نافذة ممر الطابق الخامس مع تناثر شظايا صغيرة على الأرضية. تم تطويق المكان بشريط تحذيري وإبلاغ الصيانة للاستبدال والتنظيف.',
             'cap' => 'كسر زجاج — ممر الطابق الخامس'],
            ['u' => $m('hadi', 2), 'cam' => 20, 'fl' => 2, 'days' => 14, 'h' => 10, 'min' => 20, 'kind' => 'elevator', 'end' => 30,
             'desc' => 'توقف مفاجئ للمصعد بين الطابقين الأول والثاني لمدة تجاوزت العشر دقائق مع وجود موظفين في الداخل. تم إخراجهم بسلام عبر فريق الطوارئ وتوقيف المصعد للفحص.',
             'cap' => 'توقف مصعد — إخلاء آمن'],
            ['u' => $m('hamza', 3), 'cam' => 22, 'fl' => 6, 'days' => 12, 'h' => 15, 'min' => 0, 'kind' => 'parking', 'end' => 45,
             'desc' => 'حركة شاحنات غير معتادة في منطقة التحميل بالطابق السادس خارج أوقات التوريد الرسمية. تم تسجيل الأرقام وإبلاغ إدارة المستودعات للتحقق من التصاريح.',
             'cap' => 'حركة شاحنات — منطقة التحميل'],
            ['u' => $m('rami', 4), 'cam' => 5, 'fl' => 1, 'days' => 12, 'h' => 8, 'min' => 30, 'kind' => 'person', 'end' => null,
             'desc' => 'تعطل جهاز البصمة عند مدخل الموظفين أدى إلى تجمع طابور طويل وتأخر في الدخول. تم فتح البوابة اليدوية بإشراف الأمن وتوثيق الدخول يدوياً لحين الإصلاح.',
             'cap' => 'عطل بصمة — طابور موظفين'],
        ];

        $created = [];
        foreach ($data as $i => $d) {
            $obs = $dayAt($d['days'], $d['h'], $d['min']);
            $obsEnd = !empty($d['end']) ? $obs->copy()->addMinutes((int) $d['end']) : null;
            $sent = $obs->copy()->addMinutes(15 + ($i % 4) * 10);
            $processed = $sent->copy()->addHours(1 + ($i % 5));
            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $obs,
                'observed_end_at' => $obsEnd,
                'description' => $d['desc'],
                'status' => 'accepted',
                'rejection_reason' => null,
                'processed_by' => $writer->id,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $sent->copy()->subMinutes(3), 'updated_at' => $processed]);
            $n->refresh();

            $this->createPngAttachment($n, $d['cam'], $d['fl'], $d['kind'], $obs, $disk, $files, $i);
            $created[] = $n;
        }
        $this->command->line('  old accepted notes: ' . count($created) . ' (10 days x 2, same-day)');

        return $created;
    }

    private function seedOldRejectedNotes(callable $m, User $writer, callable $dayAt, $disk, int &$files): array
    {
        $data = [
            ['u' => $m('hamza', 3), 'cam' => 2, 'fl' => 1, 'days' => 13, 'h' => 10, 'min' => 0, 'kind' => 'door',
             'reason' => 'مكررة: سبق توثيق الحالة نفسها (باب المستودع الجانبي) في ملاحظة سابقة وما تزال قيد المعالجة لدى الصيانة. يرجى عدم إعادة الإرسال قبل إغلاق البلاغ الأصلي.',
             'desc' => 'باب المستودع الجانبي في الطابق الأول ما يزال غير محكم الإغلاق صباحاً.'],
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'days' => 11, 'h' => 16, 'min' => 20, 'kind' => 'crowd',
             'reason' => 'اللقطة المرفقة غير واضحة: الصورة مظلمة ولا تُظهر أي تفاصيل مفيدة عن الازدحام المذكور. يرجى إعادة الإرسال مع لقطة أوضح تتضمن التوقيت.',
             'desc' => 'ازدحام عند المدخل الرئيسي وقت الذروة مع صعوبة في تمييز التفاصيل بسبب الإضاءة.'],
            ['u' => $m('hadi', 2), 'cam' => 11, 'fl' => 3, 'days' => 9, 'h' => 9, 'min' => 30, 'kind' => 'corridor',
             'reason' => 'الوصف غير محدد: الملاحظة لا تحدد الموقع بدقة ولا الإجراء المتخذ. حدد رقم الكاميرا والطابق والوقت بدقة وأضف ما قمت به.',
             'desc' => 'لوحظ نشاط غير معتاد في ممر الطابق الثالث صباحاً يحتاج إلى متابعة من المعنيين.'],
            ['u' => $m('tariq', 1), 'cam' => 5, 'fl' => 1, 'days' => 7, 'h' => 11, 'min' => 15, 'kind' => 'person',
             'reason' => 'عدم تطابق: الوصف يشير إلى الكاميرا 5 بينما اللقطة المرفقة مأخوذة من الكاميرا 9. يرجى تصحيح رقم الكاميرا وإعادة الإرسال بالمرفق الصحيح.',
             'desc' => 'شخص يقف مطولاً أمام الكاميرا الخامسة في بهو الطابق الأول بطريقة مثيرة للانتباه.'],
            ['u' => $m('hamza', 3), 'cam' => 6, 'fl' => 2, 'days' => 6, 'h' => 12, 'min' => 40, 'kind' => 'archive',
             'reason' => 'خارج الاختصاص: البلاغ يتعلق بتأخر إداري في تسليم ملفات الأرشيف وليس واقعة مرصودة عبر الكاميرات. يرجى توجيهه عبر القناة الإدارية المختصة.',
             'desc' => 'تأخر قسم الأرشيف في تسليم ملفات مطلوبة منذ يومين دون مبرر واضح.'],
            ['u' => $m('rami', 4), 'cam' => 16, 'fl' => 5, 'days' => 5, 'h' => 15, 'min' => 0, 'kind' => 'static',
             'reason' => 'وقت الرصد غير متطابق: زمن اللقطة المرفقة يختلف بساعتين عن وقت الرصد المسجل. تحقق من توقيت الكاميرا وسجل الوقت الصحيح.',
             'desc' => 'تشويش متقطع على بث الكاميرا السادسة عشرة في الطابق الخامس منذ الظهيرة.'],
        ];

        $created = [];
        foreach ($data as $i => $d) {
            $obs = $dayAt($d['days'], $d['h'], $d['min']);
            $sent = $obs->copy()->addMinutes(10 + ($i % 3) * 10);
            $processed = $sent->copy()->addHours(2 + ($i % 4));
            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $obs,
                'observed_end_at' => null,
                'description' => $d['desc'],
                'status' => 'rejected',
                'rejection_reason' => $d['reason'],
                'processed_by' => $writer->id,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $sent->copy()->subMinutes(3), 'updated_at' => $processed]);
            $n->refresh();

            $this->createPngAttachment($n, $d['cam'], $d['fl'], $d['kind'], $obs, $disk, $files, 100 + $i);
            $created[] = $n;
        }
        $this->command->line('  old rejected notes: ' . count($created));

        return $created;
    }

    private function seedOldPublishedReports(array $oldNotes, User $writer, callable $dayAt, $disk, int &$files): array
    {
        $accepted = collect($oldNotes)->where('status', 'accepted')->sortBy('observed_at')->values();
        $byDay = [];
        foreach ($accepted as $n) {
            $byDay[$n->observed_at->toDateString()][] = $n;
        }
        ksort($byDay);

        $tz = (string) config('app.timezone', 'UTC');
        $reports = [];

        foreach ($byDay as $day => $dayNotesRaw) {
            $dayNotes = collect($dayNotesRaw)->sortBy('observed_at')->values();
            $floors = $dayNotes->pluck('floor_number')->unique()->sort()->values()->join('، ');
            $cams = $dayNotes->pluck('camera_number')->unique()->sort()->values()->join('، ');
            $from = $dayNotes->first()->observed_at->format('H:i');
            $to = $dayNotes->last()->observed_at->format('H:i');

            $kinds = $dayNotes->map(fn ($n) => $this->inferKind($n->description))->unique()->values();
            $summary = "خلال يوم {$day} وثّق المراقبون " . $dayNotes->count() . " ملاحظات مقبولة بين الساعة {$from} والساعة {$to}، في الطوابق ({$floors}) عبر الكاميرات ({$cams}). "
                . $this->smartSummaryTail($kinds);
            $recs = $this->smartRecommendations($kinds);

            $publishedAt = Carbon::parse($day, $tz)->setTime(17, 30);
            $report = Report::create([
                'author_id' => $writer->id,
                'title' => 'التقرير اليومي — ' . $day,
                'summary' => $summary,
                'recommendations' => $recs,
                'generation_mode' => Report::MODE_MANUAL,
                'status' => Report::STATUS_PUBLISHED,
                'visible_to_monitors' => true,
                'report_date' => $day,
                'published_at' => $publishedAt,
            ]);

            $order = 0;
            foreach ($dayNotes as $n) {
                $report->notes()->attach($n->id, ['order_index' => $order++]);
            }

            $report->refresh();
            $content = $this->composeReport($report->fresh(['notes', 'author']), $dayNotes, $tz);
            $report->update(['content' => $content]);
            $report->revisions()->create(['editor_id' => $writer->id, 'content_snapshot' => $content]);
            Report::where('id', $report->id)->update(['created_at' => $publishedAt->copy()->subHour(), 'updated_at' => $publishedAt]);

            $reports[] = $report->refresh();
        }
        $this->command->line('  old published reports: ' . count($reports));

        $builder = app(ReportDataBuilder::class);
        $selector = app(ReportTemplateSelector::class);
        $htmlSvc = app(ReportHtmlRenderingService::class);
        foreach ($reports as $r) {
            $this->renderSheet($r, $builder, $selector, $htmlSvc, $disk, $files);
        }

        return $reports;
    }

    private function seedOldSubmissions(callable $m, User $writer, callable $dayAt, $disk, int &$files): array
    {
        $data = [
            ['u' => $m('tariq', 1), 'cam' => 4, 'fl' => 1, 'days' => 20, 'h' => 9, 'min' => 0, 'st' => 'accepted', 'kind' => 'crowd', 'end' => 60,
             'desc' => 'توثيق شامل لحركة المدخل الرئيسي خلال ساعة الذروة الصباحية: تجمعات متقطعة عند بوابة التدقيق مع بطء في التحقق من الهويات. تم التنسيق مع الأمن لفتح مسار ثانٍ.'],
            ['u' => $m('hadi', 2), 'cam' => 6, 'fl' => 2, 'days' => 15, 'h' => 14, 'min' => 15, 'st' => 'accepted', 'kind' => 'person', 'end' => null,
             'desc' => 'رصد شخص بزي عمل غير مألوف يتجول لفترات طويلة قرب المستودع الفرعي في الطابق الثاني دون مهمة ظاهرة. تم تتبعه وإبلاغ المشرف الذي تحقق منه.'],
            ['u' => $m('hamza', 3), 'cam' => 9, 'fl' => 2, 'days' => 11, 'h' => 11, 'min' => 30, 'st' => 'rejected', 'kind' => 'static', 'end' => null,
             'reason' => 'المرفق غير كافٍ: اللقطة المرفقة ثابتة ولا تُظهر الحركة المذكورة في الوصف. أعد الإرسال مع تسلسل لقطات يوثق الحركة.',
             'desc' => 'رصد حركة نشطة في الطابق الثاني خلال فترة الصباح مع تنقلات متكررة بين الممرات.'],
            ['u' => $m('rami', 4), 'cam' => 13, 'fl' => 4, 'days' => 8, 'h' => 16, 'min' => 0, 'st' => 'accepted', 'kind' => 'parking', 'end' => null,
             'desc' => 'توثيق وضع ساحة التحميل في الطابق الرابع: تكدس مركبات يعيق حركة المناورة. تم إشعار السائقين وإعادة تنظيم الاصطفاف.'],
            ['u' => $m('tariq', 1), 'cam' => 2, 'fl' => 1, 'days' => 5, 'h' => 10, 'min' => 0, 'st' => 'rejected', 'kind' => 'door', 'end' => null,
             'reason' => 'مكرر مع ملاحظة سابقة: نفس باب المستودع موثق مسبقاً وما يزال قيد المعالجة. الإرسالات العامة ليست مساراً لتكرار البلاغات.',
             'desc' => 'الباب الجانبي للمستودع ما يزال بحاجة إلى إصلاح رغم البلاغات السابقة.'],
        ];

        $created = [];
        foreach ($data as $i => $d) {
            $obs = $dayAt($d['days'], $d['h'], $d['min']);
            $obsEnd = !empty($d['end']) ? $obs->copy()->addMinutes((int) $d['end']) : null;
            $sent = $obs->copy()->addMinutes(15);
            $processed = $sent->copy()->addHours(2);
            $sub = GeneralSubmission::create([
                'user_id' => $d['u'],
                'floor_number' => (string) $d['fl'],
                'camera_number' => (string) $d['cam'],
                'observed_at' => $obs,
                'observed_end_at' => $obsEnd,
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => $d['st'] !== 'draft' ? $writer->id : null,
                'sent_at' => $sent,
                'processed_at' => $d['st'] !== 'draft' ? $processed : null,
            ]);
            GeneralSubmission::where('id', $sub->id)->update(['created_at' => $sent->copy()->subMinutes(2), 'updated_at' => $processed]);
            $sub->refresh();

            $sub->reportWriters()->attach($writer->id);

            $this->createSubmissionPng($sub, $d['cam'], $d['fl'], $d['kind'], $obs, $disk, $files, $i);
            $created[] = $sub;
        }
        $this->command->line('  old submissions: ' . count($created));

        return $created;
    }

    private function seedTodayNotes(callable $m, User $writer, Carbon $now, string $todayStr, $disk, int &$files): array
    {
        $at = function (int $minutesAgo, int $slot) use ($now, $todayStr) {
            $obs = $now->copy()->subMinutes($minutesAgo);
            if ($obs->toDateString() !== $todayStr) {
                $obs = $now->copy()->startOfDay()->addMinutes(30 + $slot * 45);
                if ($obs->greaterThan($now)) $obs = $now->copy()->subMinutes(5);
            }
            return $obs;
        };

        $data = [
            ['u' => $m('tariq', 1), 'cam' => 7, 'fl' => 2, 'st' => 'accepted', 'kind' => 'door', 'ago' => 90, 'end' => null,
             'desc' => 'باب غرفة التحكم في الطابق الثاني رُصد مفتوحاً خلال فترة الاستراحة ولا يوجد أحد في الداخل. تم إغلاقه والتأكد من سلامة الأجهزة وإبلاغ المشرف.',
             'cap' => 'باب مفتوح — غرفة التحكم'],
            ['u' => $m('hadi', 2), 'cam' => 11, 'fl' => 3, 'st' => 'accepted', 'kind' => 'bag', 'ago' => 120, 'end' => 30,
             'desc' => 'حقيبة يد سوداء صغيرة متروكة على مقعد الانتظار في الطابق الثالث لأكثر من خمس عشرة دقيقة. تم فحصها بحذر مع الأمن وتبين أنها تحوي أوراق عمل لأحد المراجعين الذي استلمها.',
             'cap' => 'حقيبة متروكة — الطابق الثالث'],
            ['u' => $m('hamza', 3), 'cam' => 14, 'fl' => 4, 'st' => 'accepted', 'kind' => 'power', 'ago' => 45, 'end' => null,
             'desc' => 'انطفاء جزئي للإضاءة في ممر الطابق الرابع: ثلاثة مصابيح من أصل أربعة مطفأة والمنطقة شبه مظلمة. تم إبلاغ الصيانة الكهربائية للمعالجة الفورية.',
             'cap' => 'انطفاء إضاءة — ممر الطابق الرابع'],
            ['u' => $m('rami', 4), 'cam' => 4, 'fl' => 1, 'st' => 'accepted', 'kind' => 'crowd', 'ago' => 180, 'end' => 40,
             'desc' => 'تعطل البوابة الإلكترونية عند المدخل الرئيسي أدى إلى تجمع المراجعين. تم تفعيل التدقيق اليدوي بإشراف الأمن وتنظيم الدخول على دفعات حتى عودة النظام.',
             'cap' => 'عطل بوابة — تنظيم يدوي'],
            ['u' => $m('tariq', 1), 'cam' => 9, 'fl' => 2, 'st' => 'accepted', 'kind' => 'water', 'ago' => 240, 'end' => null,
             'desc' => 'تسرب مياه خفيف أسفل رفوف المستودع الفرعي في الطابق الثاني مصدره وصلة صرف مرتخية. تم احتواء التسرب بوسائل أولية وإبلاغ الصيانة لإحكام الوصلة.',
             'cap' => 'تسرب مياه — المستودع الفرعي'],

            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'st' => 'rejected', 'kind' => 'person', 'ago' => 180, 'end' => null,
             'reason' => 'الوصف قصير جداً ولا يتضمن أي تفاصيل: يرجى ذكر المكان الدقيق والوقت والسلوك المرصود والإجراء المتخذ قبل إعادة الإرسال.',
             'desc' => 'رُصدت حركة مشبوهة قرب المدخل الرئيسي تستدعي الانتباه والمتابعة الدقيقة من عناصر الأمن.',
             'cap' => 'حركة مشبوهة — وصف ناقص'],
            ['u' => $m('tariq', 1), 'cam' => 16, 'fl' => 5, 'st' => 'rejected', 'kind' => 'door', 'ago' => 120, 'end' => null,
             'reason' => 'اللقطة المرفقة لا تتوافق مع الوصف: الملف يوثق ممراً مختلفاً تماماً عن النافذة المذكورة في النص. تحقق من الكاميرا الصحيحة.',
             'desc' => 'شخص يحاول فتح نافذة خارجية في الطابق الخامس بطريقة غير معتادة تستدعي التحقق الفوري.',
             'cap' => 'محاولة فتح نافذة — عدم تطابق'],

            ['u' => $m('hadi', 2), 'cam' => 8, 'fl' => 2, 'st' => 'draft', 'kind' => 'static', 'ago' => 20, 'end' => null,
             'desc' => 'مسودة: تشويش متقطع على بث الكاميرا الثامنة يبدو ناتجاً عن عطل مؤقت في كابل الاتصال. سأستكمل التوثيق بعد التأكد.',
             'cap' => 'تشويش — الكاميرا الثامنة'],
            ['u' => $m('rami', 4), 'cam' => 19, 'fl' => 5, 'st' => 'draft', 'kind' => 'sign', 'ago' => 10, 'end' => null,
             'desc' => 'مسودة: لوحة تعليمات السلامة عند مخرج الطوارئ في الطابق الخامس مفقودة منذ يوم أمس. بحاجة لصورة أوضح قبل الإرسال.',
             'cap' => 'لوحة سلامة مفقودة'],
            ['u' => $m('tariq', 1), 'cam' => 18, 'fl' => 5, 'st' => 'draft', 'kind' => 'fire', 'ago' => 35, 'end' => null,
             'desc' => 'مسودة: رائحة احتراق خفيفة قرب غرفة الخوادم في الطابق الخامس. ما تزال قيد التحقق ولم يتم التأكد من المصدر بعد.',
             'cap' => 'رائحة احتراق — قيد التحقق'],
            ['u' => $m('hamza', 3), 'cam' => 6, 'fl' => 2, 'st' => 'draft', 'kind' => 'corridor', 'ago' => 50, 'end' => null,
             'desc' => 'مسودة: كرسي متحرك متروك وسط ممر الطابق الثاني يعيق حركة العبور. سأوثق رقم الكاميرا الدقيق وأرفق لقطة أوضح.',
             'cap' => 'عائق وسط الممر'],
            ['u' => $m('hadi', 2), 'cam' => 12, 'fl' => 1, 'st' => 'draft', 'kind' => 'person', 'ago' => 75, 'end' => null,
             'desc' => 'مسودة: ظل شخص ظهر بشكل عابر على طرف الكاميرا الثانية عشرة ثم اختفى. الصورة غير حاسمة وأحتاج لمراجعة التسجيل.',
             'cap' => 'ظل ليلي — غير مؤكد'],
            ['u' => $m('rami', 4), 'cam' => 4, 'fl' => 1, 'st' => 'draft', 'kind' => 'water', 'ago' => 200, 'end' => null,
             'desc' => 'مسودة: حنفية مكسورة في دورة مياه الطابق الأول تسبب هدراً مستمراً للمياه. بانتظار التقاط صورة نهارية أوضح.',
             'cap' => 'حنفية مكسورة — هدر مياه'],
            ['u' => $m('hamza', 3), 'cam' => 7, 'fl' => 2, 'st' => 'draft', 'kind' => 'archive', 'ago' => 300, 'end' => null,
             'desc' => 'مسودة: صوت إنذار متقطع من غرفة الأرشيف سُمع صباحاً. قيد الاستكمال والتحقق قبل الإرسال للمراجعة.',
             'cap' => 'مسودة — صوت إنذار الأرشيف'],
        ];

        $created = [];
        foreach ($data as $slot => $d) {
            $obs = $at($d['ago'], $slot);
            $obsEnd = !empty($d['end']) ? $obs->copy()->addMinutes(abs((int) $d['end'])) : null;
            if ($obsEnd && ($obsEnd->greaterThan($now) || $obsEnd->lessThanOrEqualTo($obs))) $obsEnd = null;

            $sent = null;
            $processed = null;
            if ($d['st'] !== 'draft') {
                $sent = $obs->copy()->addMinutes(5 + ($slot % 4) * 5);
                if ($sent->greaterThan($now)) $sent = $now->copy()->subMinutes(2);
            }
            if (in_array($d['st'], ['accepted', 'rejected'], true)) {
                $processed = $sent->copy()->addMinutes(30 + ($slot % 3) * 30);
                if ($processed->greaterThan($now)) $processed = $now->copy();
            }

            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $obs,
                'observed_end_at' => $obsEnd,
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected'], true) ? $writer->id : null,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            $createdAt = ($sent ?? $obs)->copy()->subMinutes(2);
            if ($createdAt->greaterThan($now)) $createdAt = $now->copy();
            Note::where('id', $n->id)->update(['created_at' => $createdAt, 'updated_at' => $processed ?? $createdAt]);
            $n->refresh();

            $this->createPngAttachment($n, $d['cam'], $d['fl'], $d['kind'], $obs, $disk, $files, 200 + $slot);
            $created[] = $n;
        }
        $this->command->line('  today notes: ' . count($created) . ' (5 accepted + 2 rejected + 7 drafts, zero pending)');

        return $created;
    }

    private function seedYesterdayPending(callable $m, Carbon $now, $disk, int &$files): array
    {
        $y = fn(int $h, int $min) => $now->copy()->subDay()->setTime($h, $min, 0);
        $data = [
            ['u' => $m('hamza', 3), 'cam' => 20, 'fl' => 5, 'kind' => 'elevator', 'obs' => $y(8, 20), 'end' => null,
             'desc' => 'باب المصعد في الطابق الخامس يصدر صوت طقطقة معدنية عند كل عملية فتح وإغلاق. يبدو أن سكة الباب بحاجة إلى معايرة وتشحيم قبل تفاقم العطل.'],
            ['u' => $m('tariq', 1), 'cam' => 21, 'fl' => 4, 'kind' => 'crowd', 'obs' => $y(9, 45), 'end' => 50,
             'desc' => 'تجمع مؤقت لعدد من المراجعين عند مدخل استوديو التصوير في الطابق الرابع بسبب تأخر الموظف المسؤول عن فتح القاعة. بانتظار توجيه كاتب التقارير.'],
            ['u' => $m('hadi', 2), 'cam' => 8, 'fl' => 2, 'kind' => 'static', 'obs' => $y(11, 10), 'end' => null,
             'desc' => 'انقطاع متقطع في بث الكاميرا الثامنة مع تجمد الصورة لثوانٍ متكررة ثم عودتها. يرجح أن السبب توصيلات مرتخية أو خلل في وحدة التغذية.'],
            ['u' => $m('rami', 4), 'cam' => 12, 'fl' => 1, 'kind' => 'person', 'obs' => $y(13, 30), 'end' => 25,
             'desc' => 'رصد حركة غير معتادة قرب البوابة الجانبية بعد انتهاء الدوام: شخص يتجول بمحاذاة السور لأكثر من عشر دقائق ثم غادر باتجاه الشارع الفرعي. بانتظار المراجعة.'],
            ['u' => $m('hamza', 3), 'cam' => 23, 'fl' => 6, 'kind' => 'parking', 'obs' => $y(15, 0), 'end' => null,
             'desc' => 'سيارة مجهولة من دون لوحات أمامية متوقفة منذ أكثر من ساعة أمام بوابة الشحن في الطابق السادس. لم يظهر سائقها حتى لحظة الإرسال.'],
            ['u' => $m('tariq', 1), 'cam' => 5, 'fl' => 3, 'kind' => 'water', 'obs' => $y(16, 40), 'end' => null,
             'desc' => 'تسرب مياه من سقف دورة المياه في الطابق الثالث مع تجمع بسيط عند المدخل. تم وضع تحذير للمارة بانتظار قرار الصيانة العاجلة.'],
            ['u' => $m('hadi', 2), 'cam' => 2, 'fl' => 0, 'kind' => 'archive', 'obs' => $y(18, 10), 'end' => null,
             'desc' => 'صوت إنذار متقطع يصدر من غرفة الأرشيف في الطابق الأرضي دون ظهور دخان أو حريق. يحتمل خلل في حساس الإنذار ويحتاج إلى فحص فني.'],
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'kind' => 'crowd', 'obs' => $y(19, 30), 'end' => 45,
             'desc' => 'ازدحام مسائي كثيف عند المدخل الرئيسي مع وصول دفعة مراجعين دفعة واحدة. تم تنظيم الدخول مبدئياً وما يزال البلاغ بانتظار الاعتماد.'],
            ['u' => $m('hamza', 3), 'cam' => 19, 'fl' => 0, 'kind' => 'door', 'obs' => $y(21, 5), 'end' => null,
             'desc' => 'باب القبو الخدمي في الطابق الأرضي رُصد مفتوحاً ولا يوجد أي فني في الجوار. تم إغلاقه مبدئياً وتوثيقه بانتظار مراجعة كاتب التقارير.'],
            ['u' => $m('tariq', 1), 'cam' => 24, 'fl' => 6, 'kind' => 'power', 'obs' => $y(22, 40), 'end' => null,
             'desc' => 'إضاءة موقف السيارات في الطابق السادس مطفأة بالكامل والرؤية شبه معدومة ليلاً. الوضع يشكل خطراً أمنياً ويستدعي تدخل الصيانة العاجل.'],
        ];

        $created = [];
        foreach ($data as $i => $d) {
            $sent = $d['obs']->copy()->addMinutes(5 + ($i % 4) * 5);
            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'],
                'observed_end_at' => !empty($d['end']) ? $d['obs']->copy()->addMinutes((int) $d['end']) : null,
                'description' => $d['desc'],
                'status' => 'pending',
                'rejection_reason' => null,
                'processed_by' => null,
                'sent_at' => $sent,
                'processed_at' => null,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $sent->copy()->subMinutes(2), 'updated_at' => $sent]);
            $n->refresh();

            $this->createPngAttachment($n, $d['cam'], $d['fl'], $d['kind'], $d['obs'], $disk, $files, 300 + $i);
            $created[] = $n;
        }
        $this->command->line('  yesterday pending notes: ' . count($created) . ' (no report — creation blocked by policy)');

        return $created;
    }

    private function seedTodayReport(array $todayNotes, User $writer, Carbon $now, string $todayStr, $disk, int &$files): ?Report
    {
        $accepted = collect($todayNotes)->where('status', 'accepted')->sortBy('observed_at')->values();
        if ($accepted->isEmpty()) return null;
        if (Report::whereDate('report_date', $todayStr)->exists()) {
            throw new \RuntimeException('SEED ABORT: a report already exists for today — uniqueness violated');
        }

        $floors = $accepted->pluck('floor_number')->unique()->sort()->values()->join('، ');
        $cams = $accepted->pluck('camera_number')->unique()->sort()->values()->join('، ');
        $from = $accepted->first()->observed_at->format('H:i');
        $to = $accepted->last()->observed_at->format('H:i');

        $kinds = $accepted->map(fn ($n) => $this->inferKind($n->description))->unique()->values();
        $summary = "خلال يوم {$todayStr} وثّق المراقبون " . $accepted->count() . " ملاحظات مقبولة بين {$from} و{$to}، في الطوابق ({$floors}) عبر الكاميرات ({$cams}). " . $this->smartSummaryTail($kinds);
        $recs = $this->smartRecommendations($kinds);

        $publishedAt = $now->copy()->subMinutes(45);
        if ($publishedAt->toDateString() !== $todayStr) $publishedAt = $now->copy();
        $tz = (string) config('app.timezone', 'UTC');
        $report = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $todayStr,
            'summary' => $summary,
            'recommendations' => $recs,
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_PUBLISHED,
            'visible_to_monitors' => true,
            'report_date' => $todayStr,
            'published_at' => $publishedAt,
        ]);

        $order = 0;
        foreach ($accepted as $n) {
            $report->notes()->attach($n->id, ['order_index' => $order++]);
        }

        $report->refresh();
        $content = $this->composeReport($report->fresh(['notes', 'author']), $accepted, $tz);
        $report->update(['content' => $content]);
        $report->revisions()->create(['editor_id' => $writer->id, 'content_snapshot' => $content]);
        Report::where('id', $report->id)->update(['created_at' => $publishedAt->copy()->subHour(), 'updated_at' => $publishedAt]);

        $builder = app(ReportDataBuilder::class);
        $selector = app(ReportTemplateSelector::class);
        $htmlSvc = app(ReportHtmlRenderingService::class);
        $this->renderSheet($report->refresh(), $builder, $selector, $htmlSvc, $disk, $files);

        $this->command->line("  today report #{$report->id} date={$todayStr} notes=" . $accepted->count() . ' published (policy-valid)');
        return $report->refresh();
    }

    private function seedDraftReports(callable $m, User $writer, Carbon $now, $disk, int &$files, array &$extraNotes): array
    {
        $out = [];
        $tz = (string) config('app.timezone', 'UTC');

        $mkAccepted = function (array $d) use ($writer, $disk, &$files) {
            $sent = $d['obs']->copy()->addMinutes(10 + ($d['cam'] % 3) * 5);
            $processed = $sent->copy()->addHours(1 + ($d['cam'] % 3));
            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'],
                'observed_end_at' => $d['end'] ?? null,
                'description' => $d['desc'],
                'status' => 'accepted',
                'rejection_reason' => null,
                'processed_by' => $writer->id,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $sent->copy()->subMinutes(3), 'updated_at' => $processed]);
            $n->refresh();
            $this->createPngAttachment($n, $d['cam'], $d['fl'], $d['kind'], $d['obs'], $disk, $files, 400 + $n->id);
            return $n;
        };

        $twoDaysAgo = $now->copy()->subDays(2)->toDateString();
        $yNotes = [];
        $yNotes[] = $mkAccepted(['u' => $m('tariq', 1), 'cam' => 3, 'fl' => 1, 'kind' => 'crowd',
            'obs' => $now->copy()->subDays(2)->setTime(18, 10, 0), 'end' => $now->copy()->subDays(2)->setTime(18, 55, 0),
            'desc' => 'ازدحام مسائي عند المدخل الرئيسي مع تزامن خروج الموظفين ودخول دفعة مراجعين. تم تنظيم الحركة على مسارين بإشراف الأمن حتى انحسار الذروة.']);
        $yNotes[] = $mkAccepted(['u' => $m('rami', 4), 'cam' => 12, 'fl' => 1, 'kind' => 'person',
            'obs' => $now->copy()->subDays(2)->setTime(21, 5, 0),
            'desc' => 'رصد شخص يتجول بمحاذاة البوابة الجانبية بعد انتهاء الدوام لأكثر من عشر دقائق ثم غادر باتجاه الشارع الفرعي. تم تتبعه عبر الكاميرات وتوثيق أوصافه وإبلاغ الأمن.']);
        $extraNotes = array_merge($extraNotes, $yNotes);
        $r2 = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $twoDaysAgo . ' (قيد التجهيز)',
            'summary' => 'مسودة قيد التجهيز: واقعتان مسائيتان موثقتان بانتظار المراجعة النهائية والصياغة قبل النشر.',
            'recommendations' => '',
            'content' => '',
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => $twoDaysAgo,
            'published_at' => null,
        ]);
        $order = 0;
        foreach (collect($yNotes)->sortBy('observed_at')->values() as $n) {
            $r2->notes()->attach($n->id, ['order_index' => $order++]);
        }
        $r2->refresh();
        $r2->update(['content' => $this->composeReport($r2->fresh(['notes', 'author']), $r2->notes()->orderByPivot('order_index')->get(), $tz)]);
        $r2->revisions()->create(['editor_id' => $writer->id, 'content_snapshot' => $r2->content ?? 'مسودة قيد التجهيز']);
        Report::where('id', $r2->id)->update(['created_at' => $now->copy()->subDays(2)->setTime(16, 0), 'updated_at' => $now->copy()->subHours(5)]);
        $out[] = $r2->refresh();
        $this->command->line("  draft report #{$r2->id} (in-preparation, publishable)");

        $threeDaysAgo = $now->copy()->subDays(3)->toDateString();
        $wNotes = [];
        $wNotes[] = $mkAccepted(['u' => $m('tariq', 1), 'cam' => 6, 'fl' => 2, 'kind' => 'door',
            'obs' => $now->copy()->subDays(3)->setTime(10, 15, 0),
            'desc' => 'باب غرفة الاجتماعات الفرعية في الطابق الثاني رُصد موارباً صباحاً مع عدم وجود موظفين في الجوار. تم إغلاقه بإحكام وإبلاغ المشرف لتوثيق الحالة ومتابعة المفصلة.']);
        $wNotes[] = $mkAccepted(['u' => $m('hadi', 2), 'cam' => 12, 'fl' => 3, 'kind' => 'power',
            'obs' => $now->copy()->subDays(3)->setTime(14, 30, 0),
            'desc' => 'انطفاء كامل لإضاءة ممر الطابق الثالث بعد الظهر بسبب خلل في قاطع التغذية. تم تأمين الممر مؤقتاً بمصابيح طوارئ وإبلاغ الكهرباء التي أعادت التيار.']);
        $wNotes[] = $mkAccepted(['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'kind' => 'crowd',
            'obs' => $now->copy()->subDays(3)->setTime(18, 5, 0), 'end' => $now->copy()->subDays(3)->setTime(18, 40, 0),
            'desc' => 'ازدحام مسائي عند المدخل الرئيسي مع تزامن خروج الموظفين ودخول دفعة مراجعين. تم تنظيم الحركة على مسارين بإشراف الأمن حتى انحسار الذروة.']);
        $extraNotes = array_merge($extraNotes, $wNotes);
        $r3 = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $threeDaysAgo,
            'summary' => "خلال يوم {$threeDaysAgo} رُصدت عدة وقائع تشغيلية وفنية تستدعي المتابعة، وقد اكتملت الصياغة الأولية للملخص والتوصيات وبانتظار المراجعة النهائية قبل النشر.",
            'recommendations' => "1) اعتماد الملاحظات المعلقة ذات الأولوية الأمنية خلال 24 ساعة.\n2) جدولة صيانة وقائية للإضاءة والأبواب خلال 48 ساعة.\n3) تثبيت دورية مسائية إضافية على المواقف والمخارج.",
            'content' => '',
            'generation_mode' => Report::MODE_HYBRID,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => $threeDaysAgo,
            'published_at' => null,
        ]);
        $order = 0;
        foreach (collect($wNotes)->sortBy('observed_at')->values() as $n) {
            $r3->notes()->attach($n->id, ['order_index' => $order++]);
        }
        $r3->refresh();
        $r3->update(['content' => $this->composeReport($r3->fresh(['notes', 'author']), $r3->notes()->orderByPivot('order_index')->get(), $tz)]);
        $r3->revisions()->create(['editor_id' => $writer->id, 'content_snapshot' => $r3->content]);
        $r3->revisions()->create(['editor_id' => $writer->id, 'content_snapshot' => $r3->content . "\n[مراجعة ثانية: دققت الصياغة]"]);
        Report::where('id', $r3->id)->update(['created_at' => $now->copy()->subDays(3)->setTime(15, 0), 'updated_at' => $now->copy()->subHours(10)]);
        $out[] = $r3->refresh();
        $this->command->line("  draft report #{$r3->id} (work-in-progress, publishable)");

        $fiveDaysAgo = $now->copy()->subDays(5)->toDateString();
        $r4 = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $fiveDaysAgo . ' (متأخر)',
            'summary' => '',
            'recommendations' => '',
            'content' => '',
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => false,
            'report_date' => $fiveDaysAgo,
            'published_at' => null,
        ]);
        Report::where('id', $r4->id)->update(['created_at' => $now->copy()->subDays(5)->setTime(16, 30), 'updated_at' => $now->copy()->subDays(5)->setTime(16, 30)]);
        $out[] = $r4->refresh();
        $this->command->line("  draft report #{$r4->id} (stale empty)");

        return $out;
    }

    private function seedTodaySubmissions(callable $m, User $writer, Carbon $now, string $todayStr, $disk, int &$files): array
    {
        $at = function (int $minutesAgo, int $slot) use ($now, $todayStr) {
            $obs = $now->copy()->subMinutes($minutesAgo);
            if ($obs->toDateString() !== $todayStr) {
                $obs = $now->copy()->startOfDay()->addMinutes(60 + $slot * 60);
                if ($obs->greaterThan($now)) $obs = $now->copy()->subMinutes(5);
            }
            return $obs;
        };
        $data = [
            ['u' => $m('tariq', 1), 'cam' => 22, 'fl' => 6, 'st' => 'pending', 'kind' => 'parking', 'ago' => 30,
             'desc' => 'توثيق موسع لمنطقة التحميل والتفريغ في الطابق السادس: حركة شاحنات غير معتادة خارج مواعيد العمل الرسمية مع وقوف مطول دون تفريغ ظاهر. بانتظار مراجعة كاتب التقارير.'],
            ['u' => $m('hadi', 2), 'cam' => 10, 'fl' => 2, 'st' => 'pending', 'kind' => 'static', 'ago' => 75,
             'desc' => 'إرسال عام: تجمد متكرر لصورة الكاميرا العاشرة مع انقطاعات قصيرة. مرفق تسلسل لقطات يوضح لحظات التجمد لمساعدة الفريق الفني.'],
            ['u' => $m('hamza', 3), 'cam' => 15, 'fl' => 3, 'st' => 'pending', 'kind' => 'door', 'ago' => 130,
             'desc' => 'إرسال عام: باب قاعة الاجتماعات الفرعية في الطابق الثالث لا ينغلق بإحكام ويبقى موارباً. يحتاج إلى معاينة نجارة عاجلة.'],
            ['u' => $m('rami', 4), 'cam' => 7, 'fl' => 2, 'st' => 'pending', 'kind' => 'bag', 'ago' => 200,
             'desc' => 'إرسال عام: حقيبة متروكة قرب مقاعد الانتظار في الطابق الثاني رُصدت لأكثر من عشرين دقيقة. تم إبلاغ الأمن ميدانياً وهذا توثيق مرفق.'],
            ['u' => $m('tariq', 1), 'cam' => 18, 'fl' => 5, 'st' => 'pending', 'kind' => 'power', 'ago' => 300,
             'desc' => 'إرسال عام من مناوبة الأمس: وميض متكرر في إضاءة ممر الطابق الخامس يوحي بخلل في المحول. بانتظار الاعتماد والتوجيه للصيانة.'],
            ['u' => $m('hadi', 2), 'cam' => 5, 'fl' => 1, 'st' => 'draft', 'kind' => 'crowd', 'ago' => 15,
             'desc' => 'مسودة إرسال: ازدحام صباحي عند البوابة الرئيسية. ما تزال الصياغة أولية وسأضيف التوقيت الدقيق قبل الإرسال.'],
            ['u' => $m('rami', 4), 'cam' => 9, 'fl' => 2, 'st' => 'draft', 'kind' => 'corridor', 'ago' => 55,
             'desc' => 'مسودة إرسال: ملاحظات أولية عن حالة ممر الطابق الثاني بعد أعمال التنظيف. قيد استكمال الصور.'],
            ['u' => $m('hamza', 3), 'cam' => 11, 'fl' => 3, 'st' => 'accepted', 'kind' => 'fire', 'ago' => 400,
             'desc' => 'إرسال عام موثق: انبعاث رائحة احتراق خفيفة قرب لوحة الكهرباء تم التعامل معها بفصل احترازي وحضور الصيانة. وُثقت الواقعة كاملة.'],
       ];

        $created = [];
        foreach ($data as $slot => $d) {
            $obs = $at($d['ago'], $slot);
            $sent = $d['st'] === 'draft' ? null : $obs->copy()->addMinutes(5);
            if ($sent && $sent->greaterThan($now)) $sent = $now->copy()->subMinutes(1);
            $processed = $d['st'] === 'accepted' ? ($sent ? $sent->copy()->addHour() : null) : null;
            if ($processed && $processed->greaterThan($now)) $processed = $now->copy();
            $sub = GeneralSubmission::create([
                'user_id' => $d['u'],
                'floor_number' => (string) $d['fl'],
                'camera_number' => (string) $d['cam'],
                'observed_at' => $obs,
                'observed_end_at' => null,
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => null,
                'processed_by' => $d['st'] === 'accepted' ? $writer->id : null,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            GeneralSubmission::where('id', $sub->id)->update(['created_at' => ($sent ?? $obs)->copy()->subMinutes(2), 'updated_at' => $processed ?? ($sent ?? $obs)]);
            $sub->refresh();

            $sub->reportWriters()->attach($writer->id);

            $this->createSubmissionPng($sub, $d['cam'], $d['fl'], $d['kind'], $obs, $disk, $files, 500 + $slot);
            $created[] = $sub;
        }
        $this->command->line('  today submissions: ' . count($created) . ' (5 pending + 2 drafts + 1 accepted)');

        return $created;
    }

    private function seedNotifications(array $allNotes, array $allReports, array $allSubs, $writers, $monitors, User $gm, User $mainWriter, Carbon $now): void
    {
        $recentCutoff = $now->copy()->subDay()->startOfDay();
        $made = 0;
        $usersById = User::all()->keyBy('id');

        $mkNotif = function (User $recipient, object $notif, Carbon $at, ?Carbon $readAt) use (&$made) {
            $payload = $notif->toArray($recipient);
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => get_class($notif),
                'notifiable_type' => User::class,
                'notifiable_id' => $recipient->id,
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'read_at' => $readAt,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $made++;
        };

        foreach ($allNotes as $n) {
            if (!$n->sent_at) continue;
            $isRecent = $n->sent_at->greaterThanOrEqualTo($recentCutoff);

            foreach ($writers as $w) {
                if ($w->id === $n->user_id) continue;
                $mkNotif($w, new NoteSentNotification($n), $n->sent_at->copy(), $isRecent ? null : $n->sent_at->copy()->addHours(2));
            }

            $owner = $usersById->get($n->user_id);
            if ($owner && $n->status === 'accepted' && $n->processed_at) {
                $recent = $n->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new NoteAcceptedNotification($n, $mainWriter->localized_name), $n->processed_at->copy(), $recent ? null : $n->processed_at->copy()->addHours(3));
            }
            if ($owner && $n->status === 'rejected' && $n->processed_at) {
                $recent = $n->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new NoteRejectedNotification($n, (string) $n->rejection_reason, $mainWriter->localized_name), $n->processed_at->copy(), $recent ? null : $n->processed_at->copy()->addHours(3));
            }
        }

        foreach ($allReports as $r) {
            if (!$r->isPublished() || !$r->visible_to_monitors || !$r->published_at) continue;
            $isRecent = $r->published_at->greaterThanOrEqualTo($recentCutoff);
            $recipients = $monitors->concat([$gm])->concat($writers->where('id', '!=', $r->author_id));
            foreach ($recipients as $user) {
                $mkNotif($user, new ReportPublishedNotification($r), $r->published_at->copy(), $isRecent ? null : $r->published_at->copy()->addDay());
            }
        }

        foreach ($allSubs as $s) {
            if (!$s->sent_at) continue;
            $senderName = $usersById->get($s->user_id)?->localized_name ?? 'مراقب';
            $isRecent = $s->sent_at->greaterThanOrEqualTo($recentCutoff);
            foreach ($s->reportWriters as $w) {
                if ($w->id === $s->user_id) continue;
                $mkNotif($w, new DispatchSentNotification($s, $senderName), $s->sent_at->copy(), $isRecent ? null : $s->sent_at->copy()->addHours(2));
            }
            $owner = $usersById->get($s->user_id);
            if ($owner && $s->status === 'accepted' && $s->processed_at) {
                $recent = $s->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new DispatchAcceptedNotification($s, $mainWriter->localized_name), $s->processed_at->copy(), $recent ? null : $s->processed_at->copy()->addHours(3));
            }
            if ($owner && $s->status === 'rejected' && $s->processed_at) {
                $recent = $s->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new DispatchRejectedNotification($s, (string) $s->rejection_reason, $mainWriter->localized_name), $s->processed_at->copy(), $recent ? null : $s->processed_at->copy()->addHours(3));
            }
        }

        $unread = DB::table('notifications')->whereNull('read_at')->count();
        $this->command->info("NOTIFICATIONS made=$made unread=$unread");
    }

    private function seedEngagement(array $allNotes, array $allReports, ?Report $recentReport, $writers, $monitors, User $gm, User $mainWriter, Carbon $now): void
    {
        $react = function ($target, int $userId, string $type) {
            $target->reactions()->firstOrCreate(['user_id' => $userId], ['reaction' => $type]);
        };

        $accepted = collect($allNotes)->where('status', 'accepted')->values();
        $types = ['like', 'love', 'insightful', 'thanks'];
        foreach ($accepted->take(10) as $i => $n) {
            $react($n, (int) $n->user_id, $types[$i % 2]);
            $react($n, $mainWriter->id, $types[2 + ($i % 2)]);
        }
        foreach ($accepted->take(2) as $n) {
            $react($n, $gm->id, 'thanks');
        }

        foreach (collect($allReports)->where(fn ($r) => $r->isPublished())->take(3) as $i => $r) {
            $react($r, $mainWriter->id, $types[$i % 4]);
        }
        if ($recentReport) $react($recentReport, $gm->id, 'like');

        if ($recentReport) {
            $c1 = ReportComment::create([
                'report_id' => $recentReport->id,
                'user_id' => $gm->id,
                'parent_id' => null,
                'body' => 'تقرير وافٍ ومنظم. اعتمدوا التوصيات الخاصة بالإضاءة والأبواب ضمن 48 ساعة مع توثيق الإغلاق بالصور.',
            ]);
            $c2 = ReportComment::create([
                'report_id' => $recentReport->id,
                'user_id' => $mainWriter->id,
                'parent_id' => $c1->id,
                'body' => 'تم، سأتابع الصيانة ميدانياً وأوثق الإغلاق بالصور وأرفقها في المتابعة.',
            ]);
            $c3 = ReportComment::create([
                'report_id' => $recentReport->id,
                'user_id' => $mainWriter->id,
                'parent_id' => null,
                'body' => 'أرفقت اللقطات مصنفة بالتاريخ والكاميرا للرجوع إليها عند تقييم تكرار الحالات.',
            ]);
            $react($c3, $gm->id, 'like');
            $react($c2, $gm->id, 'thanks');
        }

        $this->command->line('  engagement: reactions=' . Reaction::count() . ' comments=' . ReportComment::count());
    }

    private function assertPolicyCompliance(array $allReports): void
    {
        $fail = function (string $msg): void {
            throw new \RuntimeException('SEED POLICY VIOLATION: ' . $msg);
        };

        $dupDay = DB::table('reports')->select('report_date')->groupBy('report_date')->havingRaw('COUNT(*) > 1')->value('report_date');
        if ($dupDay !== null) $fail("duplicate report_date $dupDay");
        $dupNote = DB::table('report_note')->select('note_id')->groupBy('note_id')->havingRaw('COUNT(*) > 1')->value('note_id');
        if ($dupNote !== null) $fail("note #$dupNote linked to multiple reports");

        foreach (Report::with('notes')->get() as $r) {
            $day = $r->report_date->toDateString();
            if ($r->isPublished()) {
                if ($r->notes->isEmpty()) $fail("published report #{$r->id} has no notes");
                foreach ($r->notes as $n) {
                    if ($n->status !== 'accepted') $fail("published report #{$r->id} holds non-accepted note #{$n->id}");
                    if ($n->observed_at->toDateString() !== $day) $fail("published report #{$r->id} holds note #{$n->id} from another day");
                }
                if (Note::where('status', 'pending')->whereDate('observed_at', $day)->exists()) {
                    $fail("published report #{$r->id} coexists with pending notes of $day");
                }
                if (Note::where('status', 'accepted')->whereDate('observed_at', $day)->whereNotIn('id', fn ($q) => $q->select('note_id')->from('report_note'))->exists()) {
                    $fail("published report #{$r->id} leaves unattached accepted notes of $day");
                }
            } else {
                foreach ($r->notes as $n) {
                    if ($n->status !== 'accepted') $fail("draft report #{$r->id} holds non-accepted note #{$n->id}");
                    if ($n->observed_at->toDateString() !== $day) $fail("draft report #{$r->id} holds note #{$n->id} from another day");
                }
            }
        }

        foreach (Note::whereIn('status', ['accepted', 'rejected'])->get() as $n) {
            if (!$n->processed_by || !User::where('id', $n->processed_by)->where('role', 'report_writer')->exists()) {
                $fail("note #{$n->id} ({$n->status}) has no writer processor");
            }
            if ($n->status === 'rejected' && mb_strlen(trim((string) $n->rejection_reason)) < 5) {
                $fail("note #{$n->id} rejected without reason");
            }
            if ($n->sent_at && $n->sent_at->greaterThan(now()->addHour())) $fail("note #{$n->id} sent in future");
            if ($n->processed_at && $n->sent_at && $n->processed_at->lessThan($n->sent_at)) $fail("note #{$n->id} processed before sent");
        }

        $missing = Attachment::get()->reject(fn ($a) => $a->file_path && Storage::disk('attachments')->exists($a->file_path))->count();
        if ($missing > 0) $fail("$missing note attachments missing on disk");
        $missingSub = GeneralSubmissionAttachment::get()->reject(fn ($a) => $a->file_path && Storage::disk('attachments')->exists($a->file_path))->count();
        if ($missingSub > 0) $fail("$missingSub submission attachments missing on disk");

        $this->command->info('POLICY-CHECK OK: all reports publish-valid, all links unique, all files on disk');
    }

    private function createPngAttachment(Note $n, int $cam, int $fl, string $kind, Carbon $obs, $disk, int &$files, int $variant): void
    {
        $png = $this->pngScene($cam, $fl, $obs->format('Y-m-d H:i'), $kind, $variant);
        $path = 'notes/' . $n->id . '/' . Str::uuid() . '.png';
        $disk->put($path, $png);
        Attachment::create([
            'note_id' => $n->id,
            'file_path' => $path,
            'original_name' => "كاميرا-{$cam}-لقطة.png",
            'mime_type' => 'image/png',
            'file_size' => strlen($png),
        ]);
        $files++;
    }

    private function createSubmissionPng(GeneralSubmission $sub, int $cam, int $fl, string $kind, Carbon $obs, $disk, int &$files, int $variant): void
    {
        $png = $this->pngScene($cam, $fl, $obs->format('Y-m-d H:i'), $kind, $variant);
        $path = 'submissions/' . $sub->id . '/' . Str::uuid() . '.png';
        $disk->put($path, $png);
        GeneralSubmissionAttachment::create([
            'general_submission_id' => $sub->id,
            'file_path' => $path,
            'original_name' => "كاميرا-{$cam}-مرفق.png",
            'mime_type' => 'image/png',
            'file_size' => strlen($png),
        ]);
        $files++;
    }

    private function pngScene(int $cam, int $floor, string $when, string $kind, int $variant): string
    {
        $W = 640;
        $H = 360;
        $im = imagecreatetruecolor($W, $H);

        $bg = imagecolorallocate($im, 15, 26, 19);
        $band = imagecolorallocate($im, 18, 32, 23);
        $grid = imagecolorallocate($im, 30, 46, 34);
        $dark = imagecolorallocate($im, 10, 15, 12);
        $mid = imagecolorallocate($im, 42, 61, 47);
        $steel = imagecolorallocate($im, 95, 117, 104);
        $amber = imagecolorallocate($im, 216, 178, 74);
        $red = imagecolorallocate($im, 224, 82, 82);
        $paper = imagecolorallocate($im, 232, 228, 210);
        $water = imagecolorallocate($im, 95, 158, 160);
        $black = imagecolorallocate($im, 0, 0, 0);

        imagefilledrectangle($im, 0, 0, $W, $H, $bg);
        imagefilledrectangle($im, 0, 150, $W, 230, $band);
        foreach ([90, 180, 270] as $gy) imageline($im, 0, $gy, $W, $gy, $grid);
        foreach ([160, 320, 480] as $gx) imageline($im, $gx, 0, $gx, $H, $grid);

        $v = $variant % 5;
        $dx = $v * 7 - 14;
        switch ($kind) {
            case 'person':
                imagefilledellipse($im, 430 + $dx, 205, 32, 36, $amber);
                imagefilledrectangle($im, 414 + $dx, 222, 446 + $dx, 280, $amber);
                imagefilledrectangle($im, 120, 120, 520, 130, $dark);
                break;
            case 'door':
                imagefilledrectangle($im, 270 + $dx, 110, 370 + $dx, 280, $mid);
                imagerectangle($im, 270 + $dx, 110, 370 + $dx, 280, $paper);
                imagefilledellipse($im, 354 + $dx, 200, 10, 10, $paper);
                break;
            case 'archive':
                imagefilledrectangle($im, 200, 120, 440, 280, $dark);
                imagerectangle($im, 200, 120, 440, 280, $steel);
                foreach ([135, 165, 195, 225] as $ry) imagefilledrectangle($im, 215, $ry, 425, $ry + 18, $steel);
                imagefilledrectangle($im, 215, 225, 355, 243, $mid);
                break;
            case 'static':
                foreach ([140, 180, 220, 260] as $ly) imageline($im, 60, $ly, 580, $ly, $mid);
                imagestring($im, 5, 250, 195, 'NO SIGNAL', $red);
                break;
            case 'crowd':
                foreach ([200, 260, 320, 380, 440] as $i => $cx) {
                    imagefilledellipse($im, $cx + $dx, 210 + ($i % 3) * 5, 28, 30, $amber);
                }
                imagefilledrectangle($im, 180, 238, 460, 276, $amber);
                break;
            case 'corridor':
                imagefilledrectangle($im, 80, 120, 560, 280, $black);
                imagefilledellipse($im, 320 + $dx, 140, 20, 20, $steel);
                imageline($im, 320 + $dx, 150, 320 + $dx, 165, $steel);
                imagefilledrectangle($im, 100, 260, 540, 268, $mid);
                break;
            case 'bag':
                imagefilledrectangle($im, 295 + $dx, 210, 345 + $dx, 280, $amber);
                imagerectangle($im, 308 + $dx, 195, 332 + $dx, 215, $amber);
                imagefilledrectangle($im, 180, 282, 460, 290, $mid);
                break;
            case 'sign':
                imagefilledrectangle($im, 250 + $dx, 150, 390 + $dx, 210, $black);
                imagerectangle($im, 250 + $dx, 150, 390 + $dx, 210, $amber);
                imageline($im, 320 + $dx, 210, 320 + $dx, 280, $steel);
                imageline($im, 270 + $dx, 170, 370 + $dx, 170, $amber);
                imageline($im, 270 + $dx, 185, 340 + $dx, 185, $steel);
                break;
            case 'water':
                imagefilledrectangle($im, 200, 120, 440, 140, $dark);
                imagerectangle($im, 200, 120, 440, 140, $steel);
                foreach ([[240, 170], [280, 200], [320, 180], [360, 210], [400, 185]] as $d) {
                    imagefilledellipse($im, $d[0] + $dx, $d[1], 10, 12, $water);
                }
                imagefilledrectangle($im, 180, 260, 460, 280, $dark);
                break;
            case 'power':
                imagefilledrectangle($im, 290 + $dx, 130, 350 + $dx, 230, $dark);
                imagerectangle($im, 290 + $dx, 130, 350 + $dx, 230, $amber);
                $pts = [325 + $dx, 145, 305 + $dx, 195, 320 + $dx, 195, 315 + $dx, 230, 340 + $dx, 185, 325 + $dx, 185];
                imagefilledpolygon($im, $pts, (int) (count($pts) / 2), $amber);
                imagefilledellipse($im, 320 + $dx, 250, 12, 12, $red);
                break;
            case 'fire':
                $fl = [320 + $dx, 130, 340 + $dx, 180, 360 + $dx, 165, 350 + $dx, 210, 370 + $dx, 200, 350 + $dx, 250, 290 + $dx, 250, 275 + $dx, 200, 295 + $dx, 210, 285 + $dx, 165, 305 + $dx, 180];
                imagefilledpolygon($im, $fl, (int) (count($fl) / 2), $red);
                $fl2 = [320 + $dx, 180, 330 + $dx, 205, 340 + $dx, 195, 335 + $dx, 220, 305 + $dx, 220, 300 + $dx, 195, 310 + $dx, 205];
                imagefilledpolygon($im, $fl2, (int) (count($fl2) / 2), $amber);
                break;
            case 'parking':
                imagefilledrectangle($im, 140, 180, 260, 240, $mid);
                imagefilledrectangle($im, 380, 180, 500, 240, $mid);
                imagefilledrectangle($im, 260 + $dx, 170, 380 + $dx, 240, $dark);
                imagerectangle($im, 260 + $dx, 170, 380 + $dx, 240, $amber);
                imagestring($im, 5, 312 + $dx, 195, 'P', $paper);
                break;
            case 'elevator':
                imagefilledrectangle($im, 220, 110, 420, 280, $dark);
                imagerectangle($im, 220, 110, 420, 280, $steel);
                imageline($im, 320, 110, 320, 280, $steel);
                imagefilledrectangle($im, 240, 130, 310, 150, $amber);
                imagefilledrectangle($im, 330, 130, 400, 150, $mid);
                break;
            default:
                imagerectangle($im, 180, 130, 460, 280, $amber);
                imageline($im, 180, 130, 460, 280, $mid);
                imageline($im, 460, 130, 180, 280, $mid);
                break;
        }

        mt_srand(1000 + $variant * 77 + $cam * 13 + $floor * 5);
        for ($i = 0; $i < 220; $i++) {
            imagesetpixel($im, mt_rand(0, $W - 1), mt_rand(46, $H - 47), $mid);
        }
        imagerectangle($im, 0, 0, $W - 1, $H - 1, $dark);
        imagerectangle($im, 1, 1, $W - 2, $H - 2, $dark);

        imagefilledrectangle($im, 0, 0, $W, 46, $black);
        imagefilledellipse($im, 24, 23, 14, 14, $red);
        imagestring($im, 5, 44, 14, 'REC', $paper);
        $camLabel = sprintf('CAM %02d', $cam);
        imagestring($im, 5, $W - strlen($camLabel) * 9 - 12, 14, $camLabel, $paper);
        imagestring($im, 4, 200, 16, 'FL ' . $floor, $steel);
        imagefilledrectangle($im, 0, $H - 46, $W, $H, $black);
        $stamp = $when;
        imagestring($im, 4, (int) (($W - strlen($stamp) * 8) / 2), $H - 32, $stamp, $paper);

        ob_start();
        imagepng($im, null, 6);
        $bin = (string) ob_get_clean();
        imagedestroy($im);

        return $bin;
    }

    private function inferKind(string $desc): string
    {
        if (str_contains($desc, 'باب') || str_contains($desc, 'مصعد') || str_contains($desc, 'بوابة')) return 'doors';
        if (str_contains($desc, 'ازدحام') || str_contains($desc, 'تجمع') || str_contains($desc, 'طابور')) return 'crowd';
        if (str_contains($desc, 'تسرب') || str_contains($desc, 'مياه') || str_contains($desc, 'حنفية')) return 'water';
        if (str_contains($desc, 'إضاءة') || str_contains($desc, 'كهرباء') || str_contains($desc, 'تيار') || str_contains($desc, 'بصمة')) return 'power';
        if (str_contains($desc, 'كاميرا') || str_contains($desc, 'بث') || str_contains($desc, 'صورة')) return 'tech';
        if (str_contains($desc, 'سيارة') || str_contains($desc, 'شاحن') || str_contains($desc, 'موقف') || str_contains($desc, 'تحميل')) return 'traffic';
        if (str_contains($desc, 'حقيبة') || str_contains($desc, 'شخص') || str_contains($desc, 'دخول') || str_contains($desc, 'ركض')) return 'security';
        if (str_contains($desc, 'حريق') || str_contains($desc, 'احتراق') || str_contains($desc, 'دخان') || str_contains($desc, 'إنذار')) return 'safety';
        return 'general';
    }

    private function smartSummaryTail($kinds): string
    {
        $parts = [];
        if ($kinds->contains('security')) $parts[] = 'غلب عليها الطابع الأمني (دخول وحقائب وحركة أشخاص)';
        if ($kinds->contains('power')) $parts[] = 'مع أعطال كهربائية وإضاءة أثرت على الرؤية';
        if ($kinds->contains('water')) $parts[] = 'مع تسربات مياه عولجت ميدانياً وأحيلت للصيانة';
        if ($kinds->contains('crowd')) $parts[] = 'مع ازدحامات نُظمت ميدانياً بالتعاون مع الأمن';
        if ($kinds->contains('tech')) $parts[] = 'مع ملاحظات فنية على الكاميرات أحيلت للفريق المختص';
        if ($kinds->contains('traffic')) $parts[] = 'مع مخالفات توقف وحركة مركبات وُثقت باللوحات';
        if (empty($parts)) return 'وتركزت الوقائع على الجاهزية التشغيلية للمرافق والمخارج.';
        return implode('، ', $parts) . '.';
    }

    private function smartRecommendations($kinds): string
    {
        $recs = [];
        $i = 1;
        $recs[] = $i++ . ') متابعة إغلاق جميع الملاحظات المرفقة والتحقق الميداني خلال 48 ساعة مع توثيق الإغلاق بالصور.';
        if ($kinds->contains('doors') || $kinds->contains('general')) $recs[] = $i++ . ') تكليف النجارة والصيانة بجولة معايرة للأبواب والمفاصل خلال المناوبة المسائية.';
        if ($kinds->contains('power')) $recs[] = $i++ . ') جدولة فحص كهربائي شامل للإضاءة والبوابات والمحولات خلال 48 ساعة.';
        if ($kinds->contains('water')) $recs[] = $i++ . ') معالجة مصادر التسرب وإعادة فحص العزل في المواقع المذكورة قبل هطول إضافي.';
        if ($kinds->contains('crowd')) $recs[] = $i++ . ') تثبيت آلية تنظيم دخول على دفعات وقت الذروة مع عنصر أمن إضافي عند البوابات.';
        if ($kinds->contains('security')) $recs[] = $i++ . ') تشديد التدقيق على الهويات عند الأبواب الجانبية وتفعيل المناداة المسبقة للزوار.';
        if ($kinds->contains('tech')) $recs[] = $i++ . ') فحص توصيلات الكاميرات المتأثرة ووحدات التغذية وأرشفة عينة من التسجيل السليم للمقارنة.';
        if ($kinds->contains('traffic')) $recs[] = $i++ . ') منع التوقف أمام مخارج الطوارئ وبوابات الشحن وحجز المركبات المخالفة بالتنسيق مع المرور الداخلي.';
        if ($kinds->contains('safety')) $recs[] = $i++ . ') فحص حساسات الإنذار ولوحات الكهرباء ومخارج الطوارئ مع تجربة إخلاء مصغرة.';
        $recs[] = $i++ . ') أرشفة اللقطات المرفقة مصنفة بالتاريخ والكاميرا للرجوع إليها عند تقييم تكرار الحالات.';
        return implode("\n", $recs);
    }

    private function composeReport(Report $report, $notes, string $tz): string
    {
        $author = $report->author?->localized_name ?? '—';
        $dateStr = $report->report_date instanceof \DateTimeInterface ? $report->report_date->format('Y-m-d') : (string) $report->report_date;
        $lines = [
            (string) $report->title,
            '',
            'بيانات التقرير: التاريخ ' . $dateStr . ' | الرقم #' . $report->id . ' | الكاتب ' . $author,
            '',
            'أولاً — الملخص التنفيذي',
            trim((string) $report->summary) !== '' ? trim((string) $report->summary) : '—',
            '',
            'ثانياً — جدول الملاحظات (' . $notes->count() . ')',
        ];

        $i = 0;
        foreach ($notes as $n) {
            $i++;
            $at = $n->observed_at ? Carbon::parse($n->observed_at)->format('H:i') : '—';
            $end = $n->observed_end_at ? ' – ' . Carbon::parse($n->observed_end_at)->format('H:i') : '';
            $lines[] = "{$i} | الوقت {$at}{$end} | طابق {$n->floor_number} | كاميرا {$n->camera_number} | " . trim(preg_replace('/\s+/', ' ', (string) $n->description));
        }

        $lines[] = '';
        $lines[] = 'ثالثاً — التوصيات';
        $lines[] = trim((string) $report->recommendations) !== '' ? trim((string) $report->recommendations) : '— لا توجد توصيات بعد (مسودة قيد التجهيز) —';
        $lines[] = '';
        $lines[] = 'التوقيع: ' . $author . ' | رُكّب بتاريخ ' . now($tz)->format('Y-m-d H:i');

        return implode("\n", $lines);
    }

    private function renderSheet(Report $r, ReportDataBuilder $builder, ReportTemplateSelector $selector, ReportHtmlRenderingService $htmlSvc, $disk, int &$files): void
    {
        $r->loadMissing(['author', 'notes']);
        if (!$r->isPublished()) return;
        $n = $r->notes->count();
        $maxNotes = (int) config('report_sheets.max_notes', 7);
        if ($n < 1 || $n > $maxNotes) return;

        try {
            $system = $builder->systemData($r);
            $system['observations'] = ReportEngine::normalizeObservations($system['observations'] ?? []);
            $system['recommendations'] = ReportEngine::normalizeText($system['recommendations'] ?? '');
            $payload = ReportRenderPayload::fromArray([
                'report_number' => (string) $r->id,
                'date' => $r->report_date ? $r->report_date->toDateString() : '',
                'location' => $system['location'] ?? '',
                'observations' => $system['observations'],
                'recommendations' => $system['recommendations'],
            ]);
            $selected = $selector->selectForPayload($payload);
            if (!$selected) return;

            $payload = ReportRenderPayload::fromArray(array_merge($payload->toAiArray(), ['template' => $selected['key']]));
            ReportSheetRender::create([
                'report_id' => $r->id,
                'generation_no' => 1,
                'data_version' => 1,
                'template' => $selected['key'],
                'payload_hash' => $payload->hash(),
                'system_hash' => $htmlSvc->systemHash($r->fresh(['notes', 'author'])),
                'payload' => $payload->toAiArray(),
                'image_path' => null,
            ]);
            $r->update(['ai_sheet_image_path' => null, 'ai_sheet_generated_at' => now(), 'ai_sheet_data_hash' => $payload->hash()]);
            $this->command->line("  render report#{$r->id} template={$selected['key']}");
        } catch (\Throwable $e) {
            $this->command->error("  render failed report#{$r->id}: " . $e->getMessage());
        }
    }
}
