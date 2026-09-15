<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\Note;
use App\Models\Report;
use App\Models\ReportSheetRender;
use App\Models\User;
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

class RasdSmartSeed extends Seeder
{
    /**
     * IMPORTANT: هذا البذر لا يمسّ الكاش أبداً.
     * لا Cache::flush ولا cache:clear ولا مساس بجدول cache / cache_locks.
     * نمسح فقط بيانات العمل (ملاحظات، تقارير، إرسالات، إشعارات، ملفاتها).
     */
    public function run(): void
    {
        $now = now();
        $disk = Storage::disk('attachments');

        $writers = User::where('role', 'report_writer')->orderBy('id')->get();
        $monitors = User::where('role', 'monitor')->orderBy('id')->get();
        if ($writers->isEmpty() || $monitors->isEmpty()) {
            throw new \RuntimeException('SEED ABORT: users missing — run DatabaseSeeder first');
        }
        $mainWriter = $writers->first();
        $monitorByUsername = $monitors->keyBy('username');
        $m = fn(string $username, int $fallback) => $monitorByUsername->get($username)?->id ?? $monitors->firstWhere('id', $fallback)?->id ?? $monitors->first()->id;

        $this->wipeExisting($disk);

        $dayAt = fn(int $daysAgo, int $h, int $min) => $now->copy()->subDays($daysAgo)->setTime($h, $min, 0);

        $allNotes = [];
        $allReports = [];
        $allSubmissions = [];
        $files = 0;

        // 1) ملاحظات قديمة مقبولة (20) — عمود التقارير المنشورة
        $createdNotes = $this->seedOldAcceptedNotes($m, $mainWriter, $dayAt, $disk, $files);
        $allNotes = array_merge($allNotes, $createdNotes);

        // 2) ملاحظات قديمة مرفوضة (6) — بأسباب رفض ذكية ومتنوعة
        $createdRejected = $this->seedOldRejectedNotes($m, $mainWriter, $dayAt, $disk, $files);
        $allNotes = array_merge($allNotes, $createdRejected);

        // 3) تقارير قديمة منشورة من المقبولة القديمة
        $createdReports = $this->seedOldPublishedReports($allNotes, $mainWriter, $dayAt, $disk, $files);
        $allReports = array_merge($allReports, $createdReports);

        // 4) إرسالات عامة قديمة (5)
        $createdSubs = $this->seedOldSubmissions($m, $mainWriter, $dayAt, $disk, $files);
        $allSubmissions = array_merge($allSubmissions, $createdSubs);

        // 5) ملاحظات اليوم والأمس: مقبولة حديثة + قيد المراجعة + مرفوضة + مسودات
        $todayBundle = $this->seedRecentNotes($m, $mainWriter, $now, $disk, $files);
        $allNotes = array_merge($allNotes, $todayBundle);

        // 6) تقرير حديث منشور (قابل للتحرير ضمن 12 ساعة)
        $recentReport = $this->seedRecentReport($todayBundle, $mainWriter, $now, $disk, $files);
        if ($recentReport) $allReports[] = $recentReport;

        // 7) تقارير مسودة (4) — فارغ + قيد التجهيز + عمل جارٍ + متروك
        $draftReports = $this->seedDraftReports($m, $mainWriter, $now, $disk, $files, $allNotes);
        $allReports = array_merge($allReports, $draftReports);

        // 8) إرسالات اليوم: قيد المراجعة + مسودات + مقبولة
        $todaySubs = $this->seedTodaySubmissions($m, $mainWriter, $now, $disk, $files);
        $allSubmissions = array_merge($allSubmissions, $todaySubs);

        $this->seedNotifications($allNotes, $allReports, $writers, $monitors, $mainWriter, $now);

        $localAttachments = Attachment::get()->filter->isLocal()->count();
        $this->command->info(
            "DONE notes=" . Note::count()
            . ' reports=' . Report::count()
            . ' submissions=' . GeneralSubmission::count()
            . ' notif=' . DatabaseNotification::count()
            . " local_attachments=$localAttachments"
        );
    }

    private function wipeExisting($disk): void
    {
        // مسح بيانات العمل فقط — الكاش (cache / cache_locks) لا يُلمس إطلاقاً.
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
            if (Schema::hasTable('general_submission_report_writer')) DB::table('general_submission_report_writer')->whereIn('general_submission_id', $subIds)->delete();
            GeneralSubmissionAttachment::whereIn('general_submission_id', $subIds)->delete();
            GeneralSubmission::whereIn('id', $subIds)->delete();
            Attachment::whereIn('note_id', $noteIds)->delete();
            Note::whereIn('id', $noteIds)->delete();
            Report::whereIn('id', $reportIds)->delete();
        });

        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("DELETE FROM sqlite_sequence WHERE name IN ('notes','attachments','reports','report_note','report_revisions','report_sheet_renders','general_submissions','general_submission_attachments','general_submission_report_writer')");
            }
        } catch (\Throwable) {}

        $this->command->warn("WIPE notes=" . count($noteIds) . ' reports=' . count($reportIds) . ' submissions=' . count($subIds) . " files=$files (cache untouched)");
    }

    // ------------------------------------------------------------------
    // 1) مقبولة قديمة: 20 ملاحظة عالية الجودة، عربية فصيحة، بلا شوائب
    // ------------------------------------------------------------------
    private function seedOldAcceptedNotes(callable $m, User $writer, callable $dayAt, $disk, int &$files): array
    {
        $data = [
            ['u' => $m('tariq', 1), 'cam' => 2, 'fl' => 1, 'days' => 28, 'h' => 10, 'min' => 5, 'kind' => 'door', 'end' => null,
             'desc' => 'باب المستودع الرئيسي في الطابق الأول رُصد مفتوحاً بالكامل صباحاً ولا يوجد أي موظف في الجوار. تم إغلاقه فوراً والتحقق من سلامة القفل وإبلاغ الحارس المناوب لتوثيق الواقعة.',
             'cap' => 'باب مفتوح — المستودع الرئيسي'],
            ['u' => $m('hadi', 2), 'cam' => 5, 'fl' => 1, 'days' => 27, 'h' => 14, 'min' => 30, 'kind' => 'crowd', 'end' => 40,
             'desc' => 'ازدحام لافت في بهو الاستقبال عند المدخل الرئيسي وقت الذروة، مع صعوبة في تنظيم حركة المراجعين عند بوابة التدقيق. تم التنسيق مع الأمن لتنظيم الدخول على دفعات.',
             'cap' => 'ازدحام بهو الاستقبال'],
            ['u' => $m('hamza', 3), 'cam' => 8, 'fl' => 2, 'days' => 26, 'h' => 9, 'min' => 15, 'kind' => 'water', 'end' => null,
             'desc' => 'تسرب مياه واضح من سقف ممر الطابق الثاني أدى إلى تبلل مساحة من الأرضية وخطر انزلاق للمارة. تم وضع إشارة تحذيرية وإبلاغ الصيانة التي حضرت وعاينت الموقع.',
             'cap' => 'تسرب مياه — ممر الطابق الثاني'],
            ['u' => $m('rami', 4), 'cam' => 12, 'fl' => 3, 'days' => 25, 'h' => 11, 'min' => 0, 'kind' => 'archive', 'end' => null,
             'desc' => 'حاوية نفايات ممتلئة خلف قسم الأرشيف في الطابق الثالث منذ أكثر من يوم دون تفريغ، مع انتشار رائحة كريهة في الممر. تم إبلاغ خدمات النظافة لمعالجة فورية.',
             'cap' => 'تراكم نفايات — قسم الأرشيف'],
            ['u' => $m('tariq', 1), 'cam' => 15, 'fl' => 3, 'days' => 24, 'h' => 16, 'min' => 20, 'kind' => 'door', 'end' => null,
             'desc' => 'باب قاعة الاجتماعات الرئيسية في الطابق الثالث يعاني من خلل في المفصلة ويصدر صوتاً حاداً عند الفتح والإغلاق. يحتاج إلى صيانة عاجلة قبل تفاقم العطل.',
             'cap' => 'خلل باب — قاعة الاجتماعات'],
            ['u' => $m('hadi', 2), 'cam' => 3, 'fl' => 1, 'days' => 23, 'h' => 8, 'min' => 45, 'kind' => 'person', 'end' => null,
             'desc' => 'شخص غير معروف حاول الدخول عبر الباب الجانبي في الطابق الأول دون إبراز بطاقة هوية. أوقفه الحارس المناوب وتم اصطحابه إلى مكتب الأمن للتحقق من هويته وأسباب حضوره.',
             'cap' => 'محاولة دخول بلا هوية'],
            ['u' => $m('hamza', 3), 'cam' => 7, 'fl' => 2, 'days' => 22, 'h' => 13, 'min' => 10, 'kind' => 'bag', 'end' => 35,
             'desc' => 'حقيبة ظهر متروكة تحت أحد المقاعد في الحديقة الداخلية لأكثر من نصف ساعة دون ظهور صاحبها. تم فحصها ظاهرياً بحذر وإبلاغ الأمن الذي تحفظ عليها لحين مراجعة صاحبها.',
             'cap' => 'حقيبة متروكة — الحديقة الداخلية'],
            ['u' => $m('rami', 4), 'cam' => 1, 'fl' => 1, 'days' => 21, 'h' => 7, 'min' => 30, 'kind' => 'sign', 'end' => null,
             'desc' => 'لوحة الإرشاد عند المدخل الرئيسي مائلة بشكل واضح وتوشك على السقوط بسبب ارتخاء براغي التثبيت. تشكل خطراً على المارة وتحتاج إلى إعادة تثبيت فورية.',
             'cap' => 'لوحة إرشاد مائلة — المدخل'],
            ['u' => $m('tariq', 1), 'cam' => 10, 'fl' => 2, 'days' => 20, 'h' => 15, 'min' => 45, 'kind' => 'static', 'end' => null,
             'desc' => 'الكاميرا العاشرة في الطابق الثاني تعرض صورة متجمدة منذ ساعات الصباح مع تشويش خفيف على الأطراف. تم إبلاغ الفريق الفني لفحص التوصيلات ووحدة التغذية.',
             'cap' => 'تجمد صورة — الكاميرا 10'],
            ['u' => $m('hadi', 2), 'cam' => 14, 'fl' => 4, 'days' => 19, 'h' => 10, 'min' => 30, 'kind' => 'door', 'end' => null,
             'desc' => 'باب الطوارئ الشرقي في الطابق الرابع يصدر صوتاً حاداً عند كل حركة فتح بسبب جفاف المفاصل. يحتاج إلى تشحيم ومعايرة لمنع تفاقم الاهتراء.',
             'cap' => 'باب طوارئ — صوت مفصلة'],
            ['u' => $m('hamza', 3), 'cam' => 6, 'fl' => 1, 'days' => 18, 'h' => 12, 'min' => 0, 'kind' => 'crowd', 'end' => 25,
             'desc' => 'تجمع عدد من الموظفين حول كشك القهوة في الممر الرئيسي يعيق حركة العبور ذهاباً وإياباً. تم الطلب منهم إخلاء الممر والانتقال إلى منطقة الاستراحة المخصصة.',
             'cap' => 'تجمع يعيق الممر — كشك القهوة'],
            ['u' => $m('rami', 4), 'cam' => 18, 'fl' => 5, 'days' => 17, 'h' => 9, 'min' => 20, 'kind' => 'archive', 'end' => null,
             'desc' => 'خزائن التخزين في الطابق الخامس رُصدت مفتوحة وبعض الملفات بارزة للخارج ولا يوجد أي موظف في المنطقة. تم إغلاقها مبدئياً وإبلاغ أمين المستودع للجرد والتوثيق.',
             'cap' => 'خزائن مفتوحة — الطابق الخامس'],
            ['u' => $m('tariq', 1), 'cam' => 9, 'fl' => 2, 'days' => 16, 'h' => 14, 'min' => 0, 'kind' => 'person', 'end' => null,
             'desc' => 'شخص يركض بشكل مريب عبر ممر الطابق الثاني حاملاً حقيبة كبيرة ومتجهاً نحو مخرج الطوارئ. تم تتبعه عبر الكاميرات وإبلاغ الأمن الذي اعترضه للتحقق.',
             'cap' => 'شخص يركض — ممر الطابق الثاني'],
            ['u' => $m('hadi', 2), 'cam' => 4, 'fl' => 1, 'days' => 15, 'h' => 11, 'min' => 40, 'kind' => 'power', 'end' => 50,
             'desc' => 'انقطاع متكرر للتيار عن بوابة الدخول الإلكترونية في الطابق الأول أدى إلى ازدحام عند التحقق اليدوي من الهويات. تم التحويل إلى الإجراء اليدوي وإبلاغ الكهرباء.',
             'cap' => 'عطل بوابة دخول — تحقق يدوي'],
            ['u' => $m('hamza', 3), 'cam' => 11, 'fl' => 3, 'days' => 14, 'h' => 13, 'min' => 5, 'kind' => 'fire', 'end' => null,
             'desc' => 'انبعاث رائحة احتراق خفيفة قرب غرفة الكهرباء في الطابق الثالث مع تصاعد دخان بسيط من لوحة التوزيع. تم فصل التيار احترازياً واستدعاء فريق الصيانة فوراً.',
             'cap' => 'رائحة احتراق — غرفة الكهرباء'],
            ['u' => $m('rami', 4), 'cam' => 13, 'fl' => 4, 'days' => 13, 'h' => 16, 'min' => 10, 'kind' => 'parking', 'end' => null,
             'desc' => 'سيارة خاصة متوقفة بشكل مخالف أمام مخرج الطوارئ في ساحة الطابق الرابع تعيق أي إخلاء محتمل. تم توثيق لوحتها وإبلاغ المرور الداخلي لسحبها.',
             'cap' => 'توقف مخالف — مخرج طوارئ'],
            ['u' => $m('tariq', 1), 'cam' => 17, 'fl' => 5, 'days' => 12, 'h' => 9, 'min' => 50, 'kind' => 'corridor', 'end' => null,
             'desc' => 'كسر في زجاج نافذة ممر الطابق الخامس مع تناثر شظايا صغيرة على الأرضية. تم تطويق المكان بشريط تحذيري وإبلاغ الصيانة للاستبدال والتنظيف.',
             'cap' => 'كسر زجاج — ممر الطابق الخامس'],
            ['u' => $m('hadi', 2), 'cam' => 20, 'fl' => 2, 'days' => 11, 'h' => 10, 'min' => 20, 'kind' => 'elevator', 'end' => 30,
             'desc' => 'توقف مفاجئ للمصعد بين الطابقين الأول والثاني لمدة تجاوزت العشر دقائق مع وجود موظفين في الداخل. تم إخراجهم بسلام عبر فريق الطوارئ وتوقيف المصعد للفحص.',
             'cap' => 'توقف مصعد — إخلاء آمن'],
            ['u' => $m('hamza', 3), 'cam' => 22, 'fl' => 6, 'days' => 10, 'h' => 15, 'min' => 0, 'kind' => 'parking', 'end' => 45,
             'desc' => 'حركة شاحنات غير معتادة في منطقة التحميل بالطابق السادس خارج أوقات التوريد الرسمية. تم تسجيل الأرقام وإبلاغ إدارة المستودعات للتحقق من التصاريح.',
             'cap' => 'حركة شاحنات — منطقة التحميل'],
            ['u' => $m('rami', 4), 'cam' => 5, 'fl' => 1, 'days' => 9, 'h' => 8, 'min' => 30, 'kind' => 'person', 'end' => null,
             'desc' => 'تعطل جهاز البصمة عند مدخل الموظفين أدى إلى تجمع طابور طويل وتأخر في الدخول. تم فتح البوابة اليدوية بإشراف الأمن وتوثيق الدخول يدوياً لحين الإصلاح.',
             'cap' => 'عطل بصمة — طابور موظفين'],
        ];

        $created = [];
        foreach ($data as $d) {
            $obs = $dayAt($d['days'], $d['h'], $d['min']);
            $obsEnd = !empty($d['end']) ? $obs->copy()->addMinutes((int) $d['end']) : null;
            $sent = $obs->copy()->addMinutes(rand(15, 45));
            $processed = $sent->copy()->addHours(rand(1, 5));
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
            Note::where('id', $n->id)->update(['created_at' => $sent->copy()->subMinutes(rand(1, 5)), 'updated_at' => $processed]);
            $n->refresh();

            $this->createSvgAttachment($n, $d['cam'], $d['fl'], $d['kind'], $d['cap'], $obs, $disk, $files);
            $created[] = $n;
            $this->command->line("  old accepted note #{$n->id} cam={$d['cam']} fl={$d['fl']} days_ago={$d['days']}");
        }

        return $created;
    }

    // ------------------------------------------------------------------
    // 2) مرفوضة قديمة: 6 بأسباب ذكية مصنفة ومهذبة مع توجيه واضح
    // ------------------------------------------------------------------
    private function seedOldRejectedNotes(callable $m, User $writer, callable $dayAt, $disk, int &$files): array
    {
        $data = [
            ['u' => $m('hamza', 3), 'cam' => 2, 'fl' => 1, 'days' => 14, 'h' => 10, 'min' => 0, 'kind' => 'door',
             'reason' => 'مكررة: سبق توثيق الحالة نفسها (باب المستودع الجانبي) في ملاحظة سابقة وما تزال قيد المعالجة لدى الصيانة. يرجى عدم إعادة الإرسال قبل إغلاق البلاغ الأصلي.',
             'desc' => 'باب المستودع الجانبي في الطابق الأول ما يزال غير محكم الإغلاق صباحاً.',
             'cap' => 'باب المستودع — بلاغ مكرر'],
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'days' => 12, 'h' => 16, 'min' => 20, 'kind' => 'crowd',
             'reason' => 'اللقطة المرفقة غير واضحة: الصورة مظلمة ولا تُظهر أي تفاصيل مفيدة عن الازدحام المذكور. يرجى إعادة الإرسال مع لقطة أوضح تتضمن التوقيت.',
             'desc' => 'ازدحام عند المدخل الرئيسي وقت الذروة مع صعوبة في تمييز التفاصيل بسبب الإضاءة.',
             'cap' => 'ازدحام المدخل — لقطة غير واضحة'],
            ['u' => $m('hadi', 2), 'cam' => 11, 'fl' => 3, 'days' => 10, 'h' => 9, 'min' => 30, 'kind' => 'corridor',
             'reason' => 'الوصف غير محدد: الملاحظة لا تحدد الموقع بدقة ولا الإجراء المتخذ. حدد رقم الكاميرا والطابق والوقت بدقة وأضف ما قمت به.',
             'desc' => 'لوحظ نشاط غير معتاد في ممر الطابق الثالث صباحاً يحتاج إلى متابعة من المعنيين.',
             'cap' => 'نشاط غير محدد — الممر'],
            ['u' => $m('tariq', 1), 'cam' => 5, 'fl' => 1, 'days' => 8, 'h' => 11, 'min' => 15, 'kind' => 'person',
             'reason' => 'عدم تطابق: الوصف يشير إلى الكاميرا 5 بينما اللقطة المرفقة مأخوذة من الكاميرا 9. يرجى تصحيح رقم الكاميرا وإعادة الإرسال بالمرفق الصحيح.',
             'desc' => 'شخص يقف مطولاً أمام الكاميرا الخامسة في بهو الطابق الأول بطريقة مثيرة للانتباه.',
             'cap' => 'وقوف مريب — عدم تطابق الكاميرا'],
            ['u' => $m('hamza', 3), 'cam' => 6, 'fl' => 2, 'days' => 7, 'h' => 12, 'min' => 40, 'kind' => 'archive',
             'reason' => 'خارج الاختصاص: البلاغ يتعلق بتأخر إداري في تسليم ملفات الأرشيف وليس واقعة مرصودة عبر الكاميرات. يرجى توجيهه عبر القناة الإدارية المختصة.',
             'desc' => 'تأخر قسم الأرشيف في تسليم ملفات مطلوبة منذ يومين دون مبرر واضح.',
             'cap' => 'تأخر إداري — خارج الاختصاص'],
            ['u' => $m('rami', 4), 'cam' => 16, 'fl' => 5, 'days' => 6, 'h' => 15, 'min' => 0, 'kind' => 'static',
             'reason' => 'وقت الرصد غير متطابق: زمن اللقطة المرفقة يختلف بساعتين عن وقت الرصد المسجل. تحقق من توقيت الكاميرا وسجل الوقت الصحيح.',
             'desc' => 'تشويش متقطع على بث الكاميرا السادسة عشرة في الطابق الخامس منذ الظهيرة.',
             'cap' => 'تشويش بث — توقيت غير متطابق'],
        ];

        $created = [];
        foreach ($data as $d) {
            $obs = $dayAt($d['days'], $d['h'], $d['min']);
            $sent = $obs->copy()->addMinutes(rand(10, 30));
            $processed = $sent->copy()->addHours(rand(2, 6));
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
            Note::where('id', $n->id)->update(['created_at' => $sent->copy()->subMinutes(rand(1, 5)), 'updated_at' => $processed]);
            $n->refresh();

            $this->createSvgAttachment($n, $d['cam'], $d['fl'], $d['kind'], $d['cap'], $obs, $disk, $files);
            $created[] = $n;
            $this->command->line("  old rejected note #{$n->id} cam={$d['cam']} days_ago={$d['days']}");
        }

        return $created;
    }

    private function seedOldPublishedReports(array $oldNotes, User $writer, callable $dayAt, $disk, int &$files): array
    {
        $accepted = collect($oldNotes)->where('status', 'accepted')->sortBy('observed_at')->values();
        // تجميع ذكي: كل يوم تقرير، وإذا كان اليوم فيه ملاحظة واحدة ندمجه مع اليوم التالي لتجنب تقارير وحيدة ضعيفة
        $byDay = [];
        foreach ($accepted as $n) {
            $byDay[$n->observed_at->toDateString()][] = $n;
        }
        krsort($byDay);

        // دمج الأيام وحيدة الملاحظة مع أقرب يوم (لجودة أعلى)
        $merged = [];
        $carry = [];
        foreach ($byDay as $day => $notes) {
            $set = array_merge($notes, $carry);
            $carry = [];
            if (count($set) === 1) {
                $carry = $set;
                continue;
            }
            $merged[$day] = $set;
        }
        if (!empty($carry)) {
            $lastKey = array_key_last($merged);
            if ($lastKey) $merged[$lastKey] = array_merge($merged[$lastKey], $carry);
            else $merged[$dayAt(9, 17, 0)->toDateString()] = $carry;
        }

        $tz = (string) config('app.timezone', 'UTC');
        $reports = [];

        foreach ($merged as $day => $dayNotesRaw) {
            $dayNotes = collect($dayNotesRaw)->sortBy('observed_at')->values();
            if ($dayNotes->count() > 7) $dayNotes = $dayNotes->take(7)->values(); // سقف القالب
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
            $this->command->line("  old report #{$report->id} date={$day} notes=" . $dayNotes->count() . ' published');
        }

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
        foreach ($data as $d) {
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

            $obsFormatted = $obs->format('Y-m-d H:i');
            $svg = $this->svgScene($d['cam'], $d['fl'], $obsFormatted, $d['kind'], 'إرسال عام — كاميرا ' . $d['cam']);
            $path = 'submissions/' . $sub->id . '/' . Str::uuid() . '.svg';
            $disk->put($path, $svg);
            GeneralSubmissionAttachment::create([
                'general_submission_id' => $sub->id,
                'file_path' => $path,
                'original_name' => "كاميرا-{$d['cam']}-مرفق.svg",
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $files++;

            $created[] = $sub;
            $this->command->line("  old submission #{$sub->id} cam={$d['cam']} status={$d['st']}");
        }

        return $created;
    }

    // ------------------------------------------------------------------
    // 5) ملاحظات حديثة: 5 مقبولة + 10 قيد المراجعة + 2 مرفوضة + 7 مسودات = 24
    // ------------------------------------------------------------------
    private function seedRecentNotes(callable $m, User $writer, Carbon $now, $disk, int &$files): array
    {
        $ago = fn(int $minutes, ?int $dur = null) => $dur
            ? [$now->copy()->subMinutes($minutes + $dur), $now->copy()->subMinutes($minutes)]
            : [$now->copy()->subMinutes($minutes), null];

        $yesterday = fn(int $h, int $min, ?int $dur = null) => $dur
            ? [$now->copy()->subDay()->setTime($h, $min, 0), $now->copy()->subDay()->setTime($h, $min, 0)->addMinutes($dur)]
            : [$now->copy()->subDay()->setTime($h, $min, 0), null];

        $data = [
            // --- مقبولة حديثة (5) ---
            ['u' => $m('tariq', 1), 'cam' => 7, 'fl' => 2, 'st' => 'accepted', 'kind' => 'door', 'obs' => $ago(90),
             'desc' => 'باب غرفة التحكم في الطابق الثاني رُصد مفتوحاً خلال فترة الاستراحة ولا يوجد أحد في الداخل. تم إغلاقه والتأكد من سلامة الأجهزة وإبلاغ المشرف.',
             'cap' => 'باب مفتوح — غرفة التحكم'],
            ['u' => $m('hadi', 2), 'cam' => 11, 'fl' => 3, 'st' => 'accepted', 'kind' => 'bag', 'obs' => [$now->copy()->subHours(2), $now->copy()->subHours(1)->subMinutes(30)],
             'desc' => 'حقيبة يد سوداء صغيرة متروكة على مقعد الانتظار في الطابق الثالث لأكثر من خمس عشرة دقيقة. تم فحصها بحذر مع الأمن وتبين أنها تحوي أوراق عمل لأحد المراجعين الذي استلمها.',
             'cap' => 'حقيبة متروكة — الطابق الثالث'],
            ['u' => $m('hamza', 3), 'cam' => 14, 'fl' => 4, 'st' => 'accepted', 'kind' => 'power', 'obs' => $ago(45),
             'desc' => 'انطفاء جزئي للإضاءة في ممر الطابق الرابع: ثلاثة مصابيح من أصل أربعة مطفأة والمنطقة شبه مظلمة. تم إبلاغ الصيانة الكهربائية للمعالجة الفورية.',
             'cap' => 'انطفاء إضاءة — ممر الطابق الرابع'],
            ['u' => $m('rami', 4), 'cam' => 4, 'fl' => 1, 'st' => 'accepted', 'kind' => 'crowd', 'obs' => $ago(180, 40),
             'desc' => 'تعطل البوابة الإلكترونية عند المدخل الرئيسي أدى إلى تجمع المراجعين. تم تفعيل التدقيق اليدوي بإشراف الأمن وتنظيم الدخول على دفعات حتى عودة النظام.',
             'cap' => 'عطل بوابة — تنظيم يدوي'],
            ['u' => $m('tariq', 1), 'cam' => 9, 'fl' => 2, 'st' => 'accepted', 'kind' => 'water', 'obs' => $ago(240),
             'desc' => 'تسرب مياه خفيف أسفل رفوف المستودع الفرعي في الطابق الثاني مصدره وصلة صرف مرتخية. تم احتواء التسرب بوسائل أولية وإبلاغ الصيانة لإحكام الوصلة.',
             'cap' => 'تسرب مياه — المستودع الفرعي'],

            // --- قيد المراجعة pending (10) — الأهم للواجهة ---
            ['u' => $m('hamza', 3), 'cam' => 20, 'fl' => 5, 'st' => 'pending', 'kind' => 'elevator', 'obs' => $ago(60),
             'desc' => 'باب المصعد في الطابق الخامس يصدر صوت طقطقة معدنية عند كل عملية فتح وإغلاق. يبدو أن سكة الباب بحاجة إلى معايرة وتشحيم قبل تفاقم العطل.',
             'cap' => 'باب المصعد — صوت طقطقة'],
            ['u' => $m('tariq', 1), 'cam' => 21, 'fl' => 4, 'st' => 'pending', 'kind' => 'crowd', 'obs' => $ago(150, 50),
             'desc' => 'تجمع مؤقت لعدد من المراجعين عند مدخل استوديو التصوير في الطابق الرابع بسبب تأخر الموظف المسؤول عن فتح القاعة. بانتظار توجيه كاتب التقارير.',
             'cap' => 'تجمع — مدخل استوديو التصوير'],
            ['u' => $m('hadi', 2), 'cam' => 8, 'fl' => 2, 'st' => 'pending', 'kind' => 'static', 'obs' => $ago(120),
             'desc' => 'انقطاع متقطع في بث الكاميرا الثامنة مع تجمد الصورة لثوانٍ متكررة ثم عودتها. يرجح أن السبب توصيلات مرتخية أو خلل في وحدة التغذية.',
             'cap' => 'تقطع بث — الكاميرا الثامنة'],
            ['u' => $m('rami', 4), 'cam' => 12, 'fl' => 1, 'st' => 'pending', 'kind' => 'person', 'obs' => $yesterday(22, 40, 25),
             'desc' => 'رصد حركة غير معتادة قرب البوابة الجانبية بعد انتهاء الدوام: شخص يتجول بمحاذاة السور لأكثر من عشر دقائق ثم غادر باتجاه الشارع الفرعي. بانتظار المراجعة.',
             'cap' => 'حركة ليلية — البوابة الجانبية'],
            ['u' => $m('hamza', 3), 'cam' => 23, 'fl' => 6, 'st' => 'pending', 'kind' => 'parking', 'obs' => $ago(180),
             'desc' => 'سيارة مجهولة من دون لوحات أمامية متوقفة منذ أكثر من ساعة أمام بوابة الشحن في الطابق السادس. لم يظهر سائقها حتى لحظة الإرسال.',
             'cap' => 'سيارة مجهولة — بوابة الشحن'],
            ['u' => $m('tariq', 1), 'cam' => 5, 'fl' => 3, 'st' => 'pending', 'kind' => 'water', 'obs' => $ago(40),
             'desc' => 'تسرب مياه من سقف دورة المياه في الطابق الثالث مع تجمع بسيط عند المدخل. تم وضع تحذير للمارة بانتظار قرار الصيانة العاجلة.',
             'cap' => 'تسرب مياه — دورة المياه'],
            ['u' => $m('hadi', 2), 'cam' => 2, 'fl' => 0, 'st' => 'pending', 'kind' => 'archive', 'obs' => $ago(90),
             'desc' => 'صوت إنذار متقطع يصدر من غرفة الأرشيف في الطابق الأرضي دون ظهور دخان أو حريق. يحتمل خلل في حساس الإنذار ويحتاج إلى فحص فني.',
             'cap' => 'صوت إنذار — غرفة الأرشيف'],
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'st' => 'pending', 'kind' => 'crowd', 'obs' => $yesterday(18, 10, 45),
             'desc' => 'ازدحام مسائي كثيف عند المدخل الرئيسي مع وصول دفعة مراجعين دفعة واحدة. تم تنظيم الدخول مبدئياً وما يزال البلاغ بانتظار الاعتماد.',
             'cap' => 'ازدحام مسائي — المدخل الرئيسي'],
            ['u' => $m('hamza', 3), 'cam' => 19, 'fl' => 0, 'st' => 'pending', 'kind' => 'door', 'obs' => $ago(25),
             'desc' => 'باب القبو الخدمي في الطابق الأرضي رُصد مفتوحاً ولا يوجد أي فني في الجوار. تم إغلاقه مبدئياً وتوثيقه بانتظار مراجعة كاتب التقارير.',
             'cap' => 'باب قبو مفتوح — الطابق الأرضي'],
            ['u' => $m('tariq', 1), 'cam' => 24, 'fl' => 6, 'st' => 'pending', 'kind' => 'power', 'obs' => $yesterday(21, 5),
             'desc' => 'إضاءة موقف السيارات في الطابق السادس مطفأة بالكامل والرؤية شبه معدومة ليلاً. الوضع يشكل خطراً أمنياً ويستدعي تدخل الصيانة العاجل.',
             'cap' => 'انطفاء شامل — موقف السيارات'],

            // --- مرفوضة حديثة (2) ---
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'st' => 'rejected', 'kind' => 'person', 'obs' => $ago(180),
             'reason' => 'الوصف قصير جداً ولا يتضمن أي تفاصيل: يرجى ذكر المكان الدقيق والوقت والسلوك المرصود والإجراء المتخذ قبل إعادة الإرسال.',
             'desc' => 'رُصدت حركة مشبوهة قرب المدخل الرئيسي تستدعي الانتباه.',
             'cap' => 'حركة مشبوهة — وصف ناقص'],
            ['u' => $m('tariq', 1), 'cam' => 16, 'fl' => 5, 'st' => 'rejected', 'kind' => 'door', 'obs' => $ago(120),
             'reason' => 'اللقطة المرفقة لا تتوافق مع الوصف: الملف يوثق ممراً مختلفاً تماماً عن النافذة المذكورة في النص. تحقق من الكاميرا الصحيحة.',
             'desc' => 'شخص يحاول فتح نافذة خارجية في الطابق الخامس بطريقة غير معتادة تستدعي التحقق.',
             'cap' => 'محاولة فتح نافذة — عدم تطابق'],

            // --- مسودات (7) ---
            ['u' => $m('hadi', 2), 'cam' => 8, 'fl' => 2, 'st' => 'draft', 'kind' => 'static', 'obs' => $ago(20),
             'desc' => 'مسودة: تشويش متقطع على بث الكاميرا الثامنة يبدو ناتجاً عن عطل مؤقت في كابل الاتصال. سأستكمل التوثيق بعد التأكد.',
             'cap' => 'تشويش — الكاميرا الثامنة'],
            ['u' => $m('rami', 4), 'cam' => 19, 'fl' => 5, 'st' => 'draft', 'kind' => 'sign', 'obs' => $ago(10),
             'desc' => 'مسودة: لوحة تعليمات السلامة عند مخرج الطوارئ في الطابق الخامس مفقودة منذ يوم أمس. بحاجة لصورة أوضح قبل الإرسال.',
             'cap' => 'لوحة سلامة مفقودة'],
            ['u' => $m('tariq', 1), 'cam' => 18, 'fl' => 5, 'st' => 'draft', 'kind' => 'fire', 'obs' => $ago(35),
             'desc' => 'مسودة: رائحة احتراق خفيفة قرب غرفة الخوادم في الطابق الخامس. ما تزال قيد التحقق ولم يتم التأكد من المصدر بعد.',
             'cap' => 'رائحة احتراق — قيد التحقق'],
            ['u' => $m('hamza', 3), 'cam' => 6, 'fl' => 2, 'st' => 'draft', 'kind' => 'corridor', 'obs' => $ago(50),
             'desc' => 'مسودة: كرسي متحرك متروك وسط ممر الطابق الثاني يعيق حركة العبور. سأوثق رقم الكاميرا الدقيق وأرفق لقطة أوضح.',
             'cap' => 'عائق وسط الممر'],
            ['u' => $m('hadi', 2), 'cam' => 12, 'fl' => 1, 'st' => 'draft', 'kind' => 'person', 'obs' => $ago(75),
             'desc' => 'مسودة: ظل شخص ظهر briefly على طرف الكاميرا الثانية عشرة ليلاً ثم اختفى. الصورة غير حاسمة وأحتاج لمراجعة التسجيل.',
             'cap' => 'ظل ليلي — غير مؤكد'],
            ['u' => $m('rami', 4), 'cam' => 4, 'fl' => 1, 'st' => 'draft', 'kind' => 'water', 'obs' => $ago(200),
             'desc' => 'مسودة: حنفية مكسورة في دورة مياه الطابق الأول تسبب هدراً مستمراً للمياه. بانتظار التقاط صورة نهارية أوضح.',
             'cap' => 'حنفية مكسورة — هدر مياه'],
            ['u' => $m('hamza', 3), 'cam' => 7, 'fl' => 2, 'st' => 'draft', 'kind' => 'archive', 'obs' => $yesterday(11, 10),
             'desc' => 'مسودة: صوت إنذار متقطع من غرفة الأرشيف سُمع صباح أمس. قيد الاستكمال والتحقق قبل الإرسال للمراجعة.',
             'cap' => 'مسودة — صوت إنذار الأرشيف'],
        ];

        // إصلاح أي تسرب لغوي غير عربي في المسودات (دفاع إضافي)
        foreach ($data as &$row) {
            $row['desc'] = str_replace('briefly', 'بشكل عابر', $row['desc']);
        }
        unset($row);

        $created = [];
        foreach ($data as $d) {
            $sent = null;
            $processed = null;
            if (in_array($d['st'], ['pending', 'accepted', 'rejected'])) {
                $sent = $d['obs'][0]->copy()->addMinutes(rand(5, 20));
                if ($sent->greaterThan($now)) $sent = $now->copy()->subMinutes(2);
            }
            if (in_array($d['st'], ['accepted', 'rejected'])) {
                $processed = $sent->copy()->addMinutes(rand(30, 90));
                if ($processed->greaterThan($now)) $processed = $now->copy()->subMinutes(1);
            }

            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1] ?? null,
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected']) ? $writer->id : null,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            $created_at = ($sent ?? $d['obs'][0])->copy()->subMinutes(rand(1, 5));
            if ($created_at->greaterThan($now)) $created_at = $now->copy();
            Note::where('id', $n->id)->update(['created_at' => $created_at, 'updated_at' => $processed ?? $created_at]);
            $n->refresh();

            $this->createSvgAttachment($n, $d['cam'], $d['fl'], $d['kind'], $d['cap'], $d['obs'][0], $disk, $files);
            $created[] = $n;
            $this->command->line("  recent note #{$n->id} cam={$d['cam']} status={$d['st']}");
        }

        return $created;
    }

    private function seedRecentReport(array $todayNotes, User $writer, Carbon $now, $disk, int &$files): ?Report
    {
        $accepted = collect($todayNotes)->where('status', 'accepted')->sortBy('observed_at')->values();
        if ($accepted->isEmpty()) return null;

        $day = $accepted->first()->observed_at->toDateString();
        $floors = $accepted->pluck('floor_number')->unique()->sort()->values()->join('، ');
        $cams = $accepted->pluck('camera_number')->unique()->sort()->values()->join('، ');
        $from = $accepted->first()->observed_at->format('H:i');
        $to = $accepted->last()->observed_at->format('H:i');

        $kinds = $accepted->map(fn ($n) => $this->inferKind($n->description))->unique()->values();
        $summary = "خلال يوم {$day} وثّق المراقبون " . $accepted->count() . " ملاحظات مقبولة بين {$from} و{$to}، في الطوابق ({$floors}) عبر الكاميرات ({$cams}). " . $this->smartSummaryTail($kinds);
        $recs = $this->smartRecommendations($kinds);

        $publishedAt = $now->copy()->subMinutes(45);
        $tz = (string) config('app.timezone', 'UTC');
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

        $this->command->line("  recent report #{$report->id} date={$day} notes=" . $accepted->count() . ' published (editable)');
        return $report->refresh();
    }

    // ------------------------------------------------------------------
    // 7) تقارير مسودة (4) بأنماط مختلفة — تغطي كل حالات الواجهة
    // قاعدة صارمة: المسودة القابلة للنشر تُربط فقط بملاحظات مقبولة من نفس يومها،
    // وإلا فشل النشر (report_publish_invalid_note). لذا ننشئ ملاحظات مخصصة لكل مسودة.
    // ------------------------------------------------------------------
    private function seedDraftReports(callable $m, User $writer, Carbon $now, $disk, int &$files, array &$extraNotes): array
    {
        $out = [];
        $tz = (string) config('app.timezone', 'UTC');

        // مساعد: ملاحظة مقبولة بتاريخ مخصص + مرفق (تُدمج لاحقاً في الإشعارات عبر $extraNotes)
        $mkAccepted = function (array $d) use ($writer, $disk, &$files) {
            $sent = $d['obs']->copy()->addMinutes(rand(10, 25));
            $processed = $sent->copy()->addHours(rand(1, 3));
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
            $this->createSvgAttachment($n, $d['cam'], $d['fl'], $d['kind'], $d['cap'], $d['obs'], $disk, $files);
            return $n;
        };

        // أ) مسودة اليوم فارغة تماماً (جديدة)
        $r1 = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $now->toDateString(),
            'summary' => '',
            'recommendations' => '',
            'content' => '',
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => $now->toDateString(),
            'published_at' => null,
        ]);
        Report::where('id', $r1->id)->update(['created_at' => $now->copy()->subMinutes(30), 'updated_at' => $now->copy()->subMinutes(30)]);
        $this->command->line("  draft report #{$r1->id} (empty today)");
        $out[] = $r1->refresh();

        // ب) مسودة الأمس قيد التجهيز: ملاحظتان مقبولتان من يوم أمس نفسه (قابلة للنشر)
        $yesterday = $now->copy()->subDay()->toDateString();
        $yNotes = [];
        $yNotes[] = $mkAccepted(['u' => $m('tariq', 1), 'cam' => 3, 'fl' => 1, 'kind' => 'crowd',
            'obs' => $now->copy()->subDay()->setTime(18, 10, 0), 'end' => $now->copy()->subDay()->setTime(18, 55, 0),
            'desc' => 'ازدحام مسائي عند المدخل الرئيسي مع تزامن خروج الموظفين ودخول دفعة مراجعين. تم تنظيم الحركة على مسارين بإشراف الأمن حتى انحسار الذروة.',
            'cap' => 'ذروة مسائية — المدخل الرئيسي']);
        $yNotes[] = $mkAccepted(['u' => $m('rami', 4), 'cam' => 12, 'fl' => 1, 'kind' => 'person',
            'obs' => $now->copy()->subDay()->setTime(21, 5, 0),
            'desc' => 'رصد شخص يتجول بمحاذاة البوابة الجانبية بعد انتهاء الدوام لأكثر من عشر دقائق ثم غادر باتجاه الشارع الفرعي. تم تتبعه عبر الكاميرات وتوثيق أوصافه وإبلاغ الأمن.',
            'cap' => 'حركة ليلية — البوابة الجانبية']);
        $extraNotes = array_merge($extraNotes, $yNotes);
        $r2 = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $yesterday . ' (قيد التجهيز)',
            'summary' => 'مسودة قيد التجهيز ليوم أمس: واقعتان مسائيتان موثقتان بانتظار المراجعة النهائية والصياغة قبل النشر.',
            'recommendations' => '',
            'content' => '',
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => $yesterday,
            'published_at' => null,
        ]);
        $order = 0;
        foreach (collect($yNotes)->sortBy('observed_at')->values() as $n) {
            $r2->notes()->attach($n->id, ['order_index' => $order++]);
        }
        $r2->refresh();
        $r2->update(['content' => $this->composeReport($r2->fresh(['notes', 'author']), $r2->notes()->orderByPivot('order_index')->get(), $tz)]);
        $r2->revisions()->create(['editor_id' => $writer->id, 'content_snapshot' => $r2->content ?? 'مسودة قيد التجهيز']);
        Report::where('id', $r2->id)->update(['created_at' => $now->copy()->subDay()->setTime(16, 0), 'updated_at' => $now->copy()->subHours(5)]);
        $this->command->line("  draft report #{$r2->id} (in-preparation, notes=" . count($yNotes) . ')');
        $out[] = $r2->refresh();

        // ج) مسودة عمل جارٍ: ملخص وتوصيات جاهزة + 3 مقبولة من نفس اليوم (قابلة للنشر)
        $twoDaysAgo = $now->copy()->subDays(2)->toDateString();
        $wNotes = [];
        $wNotes[] = $mkAccepted(['u' => $m('tariq', 1), 'cam' => 6, 'fl' => 2, 'kind' => 'door',
            'obs' => $now->copy()->subDays(2)->setTime(10, 15, 0),
            'desc' => 'باب غرفة الاجتماعات الفرعية في الطابق الثاني رُصد موارباً صباحاً مع عدم وجود موظفين في الجوار. تم إغلاقه بإحكام وإبلاغ المشرف لتوثيق الحالة ومتابعة المفصلة.',
            'cap' => 'باب موارب — اجتماعات فرعية']);
        $wNotes[] = $mkAccepted(['u' => $m('hadi', 2), 'cam' => 12, 'fl' => 3, 'kind' => 'power',
            'obs' => $now->copy()->subDays(2)->setTime(14, 30, 0),
            'desc' => 'انطفاء كامل لإضاءة ممر الطابق الثالث بعد الظهر بسبب خلل في قاطع التغذية. تم تأمين الممر مؤقتاً بمصابيح طوارئ وإبلاغ الكهرباء التي أعادت التيار.',
            'cap' => 'انطفاء إضاءة — ممر الطابق الثالث']);
        $wNotes[] = $mkAccepted(['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'kind' => 'crowd',
            'obs' => $now->copy()->subDays(2)->setTime(18, 5, 0), 'end' => $now->copy()->subDays(2)->setTime(18, 40, 0),
            'desc' => 'ازدحام مسائي عند المدخل الرئيسي مع تزامن خروج الموظفين ودخول دفعة مراجعين. تم تنظيم الحركة على مسارين بإشراف الأمن حتى انحسار الذروة.',
            'cap' => 'ذروة مسائية — المدخل الرئيسي']);
        $extraNotes = array_merge($extraNotes, $wNotes);
        $r3 = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $twoDaysAgo,
            'summary' => "خلال يوم {$twoDaysAgo} رُصدت عدة وقائع تشغيلية وفنية تستدعي المتابعة، وقد اكتملت الصياغة الأولية للملخص والتوصيات وبانتظار المراجعة النهائية قبل النشر.",
            'recommendations' => "1) اعتماد الملاحظات المعلقة ذات الأولوية الأمنية خلال 24 ساعة.\n2) جدولة صيانة وقائية للإضاءة والأبواب خلال 48 ساعة.\n3) تثبيت دورية مسائية إضافية على المواقف والمخارج.",
            'content' => '',
            'generation_mode' => Report::MODE_HYBRID,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => $twoDaysAgo,
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
        Report::where('id', $r3->id)->update(['created_at' => $now->copy()->subDays(2)->setTime(15, 0), 'updated_at' => $now->copy()->subHours(10)]);
        $this->command->line("  draft report #{$r3->id} (work-in-progress)");
        $out[] = $r3->refresh();

        // د) مسودة متروكة من 3 أيام (تُظهر تراكم المسودات في اللوحة)
        $threeDaysAgo = $now->copy()->subDays(3)->toDateString();
        $r4 = Report::create([
            'author_id' => $writer->id,
            'title' => 'التقرير اليومي — ' . $threeDaysAgo . ' (متأخر)',
            'summary' => '',
            'recommendations' => '',
            'content' => '',
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => false,
            'report_date' => $threeDaysAgo,
            'published_at' => null,
        ]);
        Report::where('id', $r4->id)->update(['created_at' => $now->copy()->subDays(3)->setTime(16, 30), 'updated_at' => $now->copy()->subDays(3)->setTime(16, 30)]);
        $this->command->line("  draft report #{$r4->id} (stale)");
        $out[] = $r4->refresh();

        return $out;
    }

    private function seedTodaySubmissions(callable $m, User $writer, Carbon $now, $disk, int &$files): array
    {
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
        foreach ($data as $d) {
            $obs = $now->copy()->subMinutes($d['ago']);
            $sent = $d['st'] === 'draft' ? null : $obs->copy()->addMinutes(5);
            $processed = $d['st'] === 'accepted' ? ($sent ? $sent->copy()->addHour() : null) : null;
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

            $obsFormatted = $obs->format('Y-m-d H:i');
            $svg = $this->svgScene($d['cam'], $d['fl'], $obsFormatted, $d['kind'], 'إرسال عام — كاميرا ' . $d['cam']);
            $path = 'submissions/' . $sub->id . '/' . Str::uuid() . '.svg';
            $disk->put($path, $svg);
            GeneralSubmissionAttachment::create([
                'general_submission_id' => $sub->id,
                'file_path' => $path,
                'original_name' => "كاميرا-{$d['cam']}-مرفق.svg",
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $files++;

            $created[] = $sub;
            $this->command->line("  today submission #{$sub->id} cam={$d['cam']} status={$d['st']}");
        }

        return $created;
    }

    // ------------------------------------------------------------------
    // ذكاء التلخيص والتوصيات حسب نوع الوقائع
    // ------------------------------------------------------------------
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

    private function seedNotifications(array $allNotes, array $allReports, $writers, $monitors, User $mainWriter, Carbon $now): void
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
            if (!$n->sent_at) continue; // المسودات لا إشعارات لها
            $sender = $usersById->get($n->user_id);
            $senderName = $sender?->localized_name ?? 'مراقب';
            $isRecent = $n->sent_at->greaterThanOrEqualTo($recentCutoff);

            foreach ($writers as $w) {
                if ($w->id === $n->user_id) continue;
                $mkNotif($w, new NoteSentNotification($n, $senderName), $n->sent_at->copy(), $isRecent ? null : $n->sent_at->copy()->addHours(2));
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
            foreach ($monitors as $mon) {
                $mkNotif($mon, new ReportPublishedNotification($r), $r->published_at->copy(), $isRecent ? null : $r->published_at->copy()->addDay());
            }
        }

        $unread = DB::table('notifications')->whereNull('read_at')->count();
        $this->command->info("NOTIFICATIONS made=$made unread=$unread");
    }

    private function createSvgAttachment(Note $n, int $cam, int $fl, string $kind, string $cap, Carbon $obs, $disk, int &$files): void
    {
        $when = $obs->format('Y-m-d H:i');
        $svg = $this->svgScene($cam, $fl, $when, $kind, $cap);
        $path = 'notes/' . $n->id . '/' . Str::uuid() . '.svg';
        $disk->put($path, $svg);
        Attachment::create([
            'note_id' => $n->id,
            'file_path' => $path,
            'original_name' => "كاميرا-{$cam}-لقطة.svg",
            'mime_type' => 'image/svg+xml',
            'file_size' => strlen($svg),
        ]);
        $files++;
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

    private function svgScene(int $cam, int $floor, string $when, string $kind, string $caption): string
    {
        $extra = match ($kind) {
            'person' => '<circle cx="430" cy="205" r="16" fill="#d8b24a"/><rect x="414" y="222" width="32" height="58" rx="8" fill="#d8b24a"/><rect x="120" y="120" width="400" height="10" fill="#2a3d2f"/>',
            'door' => '<rect x="270" y="110" width="100" height="170" rx="4" fill="#3d5a43" stroke="#e8e4d2" stroke-width="4"/><circle cx="354" cy="200" r="5" fill="#e8e4d2"/>',
            'archive' => '<rect x="200" y="120" width="240" height="160" rx="6" fill="#1e2e22" stroke="#5f7568" stroke-width="2"/><rect x="215" y="135" width="210" height="20" rx="3" fill="#5f7568"/><rect x="215" y="165" width="210" height="20" rx="3" fill="#5f7568"/><rect x="215" y="195" width="210" height="20" rx="3" fill="#5f7568"/><rect x="215" y="225" width="140" height="20" rx="3" fill="#5f7568"/>',
            'static' => '<g stroke="#3d5a43" stroke-width="2"><line x1="60" y1="140" x2="580" y2="140"/><line x1="60" y1="180" x2="580" y2="180"/><line x1="60" y1="220" x2="580" y2="220"/><line x1="60" y1="260" x2="580" y2="260"/></g><text x="320" y="205" font-size="26" fill="#e05252" text-anchor="middle" font-family="sans-serif">NO SIGNAL</text>',
            'crowd' => '<g fill="#d8b24a"><circle cx="200" cy="220" r="14"/><circle cx="260" cy="215" r="14"/><circle cx="320" cy="210" r="14"/><circle cx="380" cy="215" r="14"/><circle cx="440" cy="220" r="14"/><rect x="180" y="238" width="280" height="38" rx="10"/></g>',
            'corridor' => '<rect x="80" y="120" width="480" height="160" fill="#0a0f0c"/><circle cx="320" cy="140" r="10" fill="none" stroke="#5f7568" stroke-width="4"/><line x1="320" y1="150" x2="320" y2="165" stroke="#5f7568" stroke-width="4"/>',
            'bag' => '<rect x="295" y="210" width="50" height="70" rx="10" fill="#d8b24a"/><rect x="308" y="195" width="24" height="20" rx="8" fill="none" stroke="#d8b24a" stroke-width="5"/><rect x="180" y="280" width="280" height="8" fill="#2a3d2f"/>',
            'sign' => '<rect x="250" y="150" width="140" height="60" rx="6" fill="#0a0f0c" stroke="#d8b24a" stroke-width="3"/><line x1="320" y1="210" x2="320" y2="280" stroke="#5f7568" stroke-width="6"/><line x1="270" y1="170" x2="370" y2="170" stroke="#d8b24a" stroke-width="4"/><line x1="270" y1="185" x2="340" y2="185" stroke="#5f7568" stroke-width="4"/>',
            'water' => '<g><rect x="200" y="120" width="240" height="20" rx="4" fill="#1e2e22" stroke="#5f7568" stroke-width="2"/><g fill="#5f9ea0"><circle cx="240" cy="170" r="5"/><circle cx="280" cy="200" r="6"/><circle cx="320" cy="180" r="5"/><circle cx="360" cy="210" r="6"/><circle cx="400" cy="185" r="5"/></g><rect x="180" y="260" width="280" height="20" rx="10" fill="#1e2e22"/></g>',
            'power' => '<g><rect x="290" y="130" width="60" height="100" rx="6" fill="#1e2e22" stroke="#d8b24a" stroke-width="3"/><polygon points="325,145 305,195 320,195 315,230 340,185 325,185" fill="#d8b24a"/><circle cx="320" cy="250" r="6" fill="#e05252"/></g>',
            'fire' => '<g><polygon points="320,130 340,180 360,165 350,210 370,200 350,250 290,250 275,200 295,210 285,165 305,180" fill="#e05252" opacity="0.9"/><polygon points="320,180 330,205 340,195 335,220 305,220 300,195 310,205" fill="#d8b24a"/></g>',
            'parking' => '<g><rect x="140" y="180" width="120" height="60" rx="8" fill="#2a3d2f" stroke="#5f7568" stroke-width="2"/><rect x="380" y="180" width="120" height="60" rx="8" fill="#2a3d2f" stroke="#5f7568" stroke-width="2"/><rect x="260" y="170" width="120" height="70" rx="8" fill="#3d5a43" stroke="#d8b24a" stroke-width="2"/><text x="320" y="210" font-size="28" fill="#e8e4d2" text-anchor="middle" font-family="sans-serif">P</text></g>',
            'elevator' => '<g><rect x="220" y="110" width="200" height="170" rx="6" fill="#1e2e22" stroke="#5f7568" stroke-width="3"/><line x1="320" y1="110" x2="320" y2="280" stroke="#5f7568" stroke-width="3"/><rect x="240" y="130" width="70" height="20" rx="3" fill="#d8b24a"/><rect x="330" y="130" width="70" height="20" rx="3" fill="#2a3d2f"/></g>',
            default => '<rect x="180" y="130" width="280" height="150" rx="6" fill="none" stroke="#d8b24a" stroke-width="3" stroke-dasharray="10 6"/>',
        };

        $safeCap = htmlspecialchars($caption, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $safeWhen = htmlspecialchars($when, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $safeCam = htmlspecialchars((string) $cam, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $safeFloor = htmlspecialchars((string) $floor, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $timePart = explode(' ', $when)[1] ?? '12:00';
        $hour = (int) explode(':', $timePart)[0];
        $isNight = $hour < 7 || $hour > 18;
        $bgTint = $isNight ? 'rgba(0,10,20,0.15)' : 'rgba(255,255,200,0.03)';
        $grainId = 'grain_' . Str::random(6);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">'
            . '<defs>'
            . '<filter id="' . $grainId . '"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/><feColorMatrix type="saturate" values="0"/></filter>'
            . '<radialGradient id="vignette" cx="50%" cy="50%" r="70%"><stop offset="0%" stop-color="transparent"/><stop offset="100%" stop-color="rgba(0,0,0,0.5)"/></radialGradient>'
            . '<filter id="glow"><feGaussianBlur stdDeviation="3" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>'
            . '</defs>'
            . '<rect width="640" height="360" fill="#0f1a13"/>'
            . '<rect width="640" height="360" fill="' . $bgTint . '"/>'
            . '<g stroke="#1e2e22" stroke-width="1" opacity="0.5"><line x1="0" y1="90" x2="640" y2="90"/><line x1="0" y1="180" x2="640" y2="180"/><line x1="0" y1="270" x2="640" y2="270"/><line x1="160" y1="0" x2="160" y2="360"/><line x1="320" y1="0" x2="320" y2="360"/><line x1="480" y1="0" x2="480" y2="360"/></g>'
            . '<g filter="url(#glow)">' . $extra . '</g>'
            . '<rect width="640" height="360" fill="url(#vignette)"/>'
            . '<rect width="640" height="360" filter="url(#' . $grainId . ')" opacity="0.04"/>'
            . '<rect width="640" height="46" fill="#0a0f0c"/>'
            . '<circle cx="24" cy="23" r="7" fill="#e05252"><animate attributeName="opacity" values="1;0.3;1" dur="2s" repeatCount="indefinite"/></circle>'
            . '<text x="44" y="30" font-size="17" fill="#e8e4d2" font-family="sans-serif" font-weight="bold">REC</text>'
            . '<text x="200" y="30" font-size="13" fill="#5f7568" font-family="sans-serif">FL ' . $safeFloor . '</text>'
            . '<text x="616" y="30" font-size="17" fill="#e8e4d2" text-anchor="end" font-family="sans-serif" font-weight="bold">CAM ' . $safeCam . '</text>'
            . '<rect y="314" width="640" height="46" fill="#0a0f0c"/>'
            . '<text x="320" y="335" font-size="14" fill="#e8e4d2" text-anchor="middle" font-family="sans-serif">' . $safeCap . ' — ' . $safeWhen . '</text>'
            . '<rect x="0" y="0" width="640" height="360" fill="none" stroke="#1e2e22" stroke-width="2"/>'
            . '</svg>';
    }
}
