<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\Note;
use App\Models\Report;
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
 * بذر ذكي شامل:
 * 1) يمسح فقط: الملاحظات + الإرسالات العامة + التقارير (مع مرفقاتها وملفاتها
 *    وإشعاراتها المرتبطة) ويُبقي المستخدمين وكل شيء آخر.
 * 2) يبذر ملاحظات عربية دقيقة غير مشوهة + إرسالات عامة بتسلسل زمني منطقي.
 * 3) يبذر تقارير مبنية فعلياً على الملاحظات المقبولة (نفس اليوم فقط)
 *    مع نسخة النظام المعتمدة — وإلا ردّت المعاينة والتصدير 404.
 * 4) ينشئ الإشعارات المستحقة فقط (إرسال → للكتاب، قبول/رفض → للمالك،
 *    نشر تقرير → للمراقبين) بطوابع زمنية متماسكة وحالتي مقروء/غير مقروء.
 */
class RasdSmartSeed extends Seeder
{
    public function run(): void
    {
        $now = now();
        $disk = Storage::disk('attachments');
        $public = Storage::disk('public');

        $writers = User::where('role', 'report_writer')->orderBy('id')->get();
        $monitors = User::where('role', 'monitor')->orderBy('id')->get();
        if ($writers->isEmpty() || $monitors->isEmpty()) {
            throw new \RuntimeException('SEED ABORT: writers or monitors missing — run DatabaseSeeder users first');
        }
        $mainWriter = $writers->first();
        $secondWriter = $writers->count() > 1 ? $writers[1] : $mainWriter;
        $thirdWriter = $writers->count() > 2 ? $writers[2] : $mainWriter;
        $monitorByUsername = $monitors->keyBy('username');
        $m = fn (string $username, int $fallback) => $monitorByUsername->get($username)?->id ?? $monitors->firstWhere('id', $fallback)?->id ?? $monitors->first()->id;

        // ── 1) المسح: فقط النطاقات الثلاثة ──────────────────────────────
        $noteIds = Note::pluck('id')->all();
        $subIds = GeneralSubmission::pluck('id')->all();
        $reportIds = Report::pluck('id')->all();

        $files = 0;
        foreach (Attachment::whereIn('note_id', $noteIds)->get(['file_path']) as $a) {
            try {
                if ($a->file_path && $disk->exists($a->file_path)) {
                    $disk->delete($a->file_path);
                    $files++;
                }
            } catch (\Throwable $e) {
            }
        }
        foreach (GeneralSubmissionAttachment::whereIn('general_submission_id', $subIds)->get(['file_path']) as $a) {
            try {
                if ($a->file_path && $disk->exists($a->file_path)) {
                    $disk->delete($a->file_path);
                    $files++;
                }
            } catch (\Throwable $e) {
            }
        }
        // صور أوراق التقارير (أفضل جهد على القرصين)
        try {
            foreach (Report::whereIn('id', $reportIds)->get(['ai_sheet_image_path']) as $r) {
                if (!$r->ai_sheet_image_path) {
                    continue;
                }
                try {
                    if ($disk->exists($r->ai_sheet_image_path)) {
                        $disk->delete($r->ai_sheet_image_path);
                        $files++;
                    }
                } catch (\Throwable $e) {
                }
                try {
                    if ($public->exists($r->ai_sheet_image_path)) {
                        $public->delete($r->ai_sheet_image_path);
                        $files++;
                    }
                } catch (\Throwable $e) {
                }
            }
            if (Schema::hasTable('report_sheet_renders')) {
                foreach (DB::table('report_sheet_renders')->whereIn('report_id', $reportIds)->get(['image_path']) as $r) {
                    if (empty($r->image_path)) {
                        continue;
                    }
                    try {
                        if ($disk->exists($r->image_path)) {
                            $disk->delete($r->image_path);
                            $files++;
                        }
                    } catch (\Throwable $e) {
                    }
                    try {
                        if ($public->exists($r->image_path)) {
                            $public->delete($r->image_path);
                            $files++;
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // الإشعارات المرتبطة فقط (أنواع النطاقات الثلاثة أو data تشير إليها)
        $notifTypes = [
            NoteSentNotification::class, NoteAcceptedNotification::class, NoteRejectedNotification::class,
            DispatchSentNotification::class, DispatchAcceptedNotification::class, DispatchRejectedNotification::class,
            ReportPublishedNotification::class,
        ];
        $notifDel = DatabaseNotification::whereIn('type', $notifTypes)->delete();
        // توافق خلفي: سجلات قديمة بمفاتيح data تشير لصفوف محذوفة
        foreach (DatabaseNotification::all() as $n) {
            $d = $n->data;
            if (is_string($d)) {
                $d = json_decode($d, true);
            }
            if (!is_array($d)) {
                continue;
            }
            $nid = $d['note_id'] ?? null;
            $sid = $d['submission_id'] ?? $d['general_submission_id'] ?? null;
            $rid = $d['report_id'] ?? null;
            if (($nid && in_array($nid, $noteIds)) || ($sid && in_array($sid, $subIds)) || ($rid && in_array($rid, $reportIds))) {
                $n->delete();
                $notifDel++;
            }
        }

        DB::transaction(function () use ($noteIds, $subIds, $reportIds) {
            if (Schema::hasTable('report_note')) {
                DB::table('report_note')->whereIn('report_id', $reportIds)->orWhereIn('note_id', $noteIds)->delete();
            }
            if (Schema::hasTable('report_revisions')) {
                DB::table('report_revisions')->whereIn('report_id', $reportIds)->delete();
            }
            if (Schema::hasTable('report_sheet_renders')) {
                DB::table('report_sheet_renders')->whereIn('report_id', $reportIds)->delete();
            }
            Attachment::whereIn('note_id', $noteIds)->delete();
            GeneralSubmissionAttachment::whereIn('general_submission_id', $subIds)->delete();
            if (Schema::hasTable('general_submission_report_writer')) {
                DB::table('general_submission_report_writer')->whereIn('general_submission_id', $subIds)->delete();
            }
            Note::whereIn('id', $noteIds)->delete();
            GeneralSubmission::whereIn('id', $subIds)->delete();
            Report::whereIn('id', $reportIds)->delete();
        });

        // تصفير العدادات الذاتية (SQLite فقط) لأرقام نظيفة
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("DELETE FROM sqlite_sequence WHERE name IN ('notes','attachments','general_submissions','general_submission_attachments','reports','report_note','report_revisions','report_sheet_renders')");
            }
        } catch (\Throwable $e) {
        }

        $this->command->warn("WIPE notes=" . count($noteIds) . ' subs=' . count($subIds) . ' reports=' . count($reportIds) . " files=$files notif=$notifDel");

        // أدوات زمنية: أي وقت "اليوم" في المستقبل يُسحب للأمس (لا تواريخ مستقبلية أبداً)
        $pastToday = function (int $h, int $min) use ($now) {
            $t = $now->copy()->setTime($h, $min, 0);
            if ($t->greaterThan($now->copy()->subMinutes(5))) {
                $t = $now->copy()->subDay()->setTime($h, $min, 0);
            }

            return $t;
        };
        $dayAt = function (int $daysAgo, int $h, int $min) use ($now) {
            return $now->copy()->subDays($daysAgo)->setTime($h, $min, 0);
        };

        // ── 2) الملاحظات: عربية دقيقة + تسلسل منطقي ─────────────────────
        // kind يُستخدم لمشهد SVG المرفق. tag يُستخدم في ملخص التقرير.
        // Notes with English descriptions (foreign notes) to test translation layer
        $foreignNoteDefs = [
            ['u' => $m('tariq', 1), 'cam' => 15, 'fl' => 1, 'st' => 'accepted', 'kind' => 'door', 'tag' => 'Main entrance door left unlocked',
             'obs' => [$dayAt(3, 8, 30), null], 'sent' => $dayAt(3, 9, 0), 'processed' => $dayAt(3, 11, 0),
             'desc' => 'Main entrance door on ground floor was found unlocked during morning patrol. Security guard was notified and door was secured immediately. No signs of forced entry.',
             'cap' => 'Unlocked main entrance — ground floor'],
            ['u' => $m('rami', 4), 'cam' => 18, 'fl' => 4, 'st' => 'accepted', 'kind' => 'corridor', 'tag' => 'Water leak in ceiling near server room',
             'obs' => [$dayAt(2, 14, 15), $dayAt(2, 14, 30)], 'sent' => $dayAt(2, 15, 0), 'processed' => $dayAt(2, 16, 30),
             'desc' => 'Water leak detected in corridor ceiling on 4th floor near server room. Maintenance team dispatched with containment equipment. Affected area cordoned off to prevent equipment damage.',
             'cap' => 'Ceiling water leak — 4th floor corridor'],
            ['u' => $m('hadi', 2), 'cam' => 20, 'fl' => 2, 'st' => 'pending', 'kind' => 'person', 'tag' => 'Suspicious individual near emergency exit',
             'obs' => [$dayAt(1, 23, 45), $dayAt(2, 0, 10)], 'sent' => $dayAt(2, 0, 20),
             'desc' => 'Individual observed loitering near emergency exit on 2nd floor after hours. Subject appeared to be testing door handles. Security alerted and continued monitoring until subject left premises.',
             'cap' => 'Suspicious activity — emergency exit'],
            ['u' => $m('mutaz.hamad', 8), 'cam' => 16, 'fl' => 3, 'st' => 'accepted', 'kind' => 'sign', 'tag' => 'Fire extinguisher missing from designated location',
             'obs' => [$dayAt(1, 10, 0), null], 'sent' => $dayAt(1, 10, 30), 'processed' => $dayAt(1, 12, 0),
             'desc' => 'Fire extinguisher found missing from its designated mount on 3rd floor west wing. Replacement unit installed and incident logged for safety compliance review.',
             'cap' => 'Missing fire extinguisher — 3rd floor'],
            ['u' => $m('hamza', 3), 'cam' => 19, 'fl' => 5, 'st' => 'rejected', 'kind' => 'archive', 'tag' => 'Camera blind spot in parking area',
             'obs' => [$dayAt(4, 16, 0), null], 'sent' => $dayAt(4, 16, 45), 'processed' => $dayAt(3, 9, 30),
             'reason' => 'Duplicate: Already documented in previous submission #1245. Camera upgrade scheduled for Q2.',
             'desc' => 'Blind spot identified in parking lot camera coverage on 5th floor. Area near northwest corner not visible. Recommend additional camera installation.',
             'cap' => 'Parking blind spot — 5th floor'],
        ];

        $noteDefs = [
            ['u' => $m('hamza', 3), 'cam' => 2, 'fl' => 1, 'st' => 'rejected', 'kind' => 'door', 'tag' => 'باب مستودع غير محكم',
             'obs' => [$dayAt(5, 10, 0), null], 'sent' => $dayAt(5, 10, 30), 'processed' => $dayAt(4, 9, 0),
             'reason' => 'مكررة: سبق توثيق الحالة نفسها في ملاحظة سابقة وما تزال قيد المعالجة لدى الصيانة.',
             'desc' => 'باب المستودع الجانبي في الطابق الأول ظهر غير محكم الإغلاق صباحاً مع وجود فجوة واضحة عند القفل.',
             'cap' => 'باب المستودع الجانبي — إغلاق غير محكم'],
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'st' => 'rejected', 'kind' => 'archive', 'tag' => 'ازدحام المدخل الرئيسي',
             'obs' => [$dayAt(4, 16, 20), null], 'sent' => $dayAt(4, 17, 0), 'processed' => $dayAt(3, 11, 30),
             'reason' => 'اللقطة المرفقة غير واضحة ولا يظهر فيها وقت الرصد. أعد الإرسال مع مرفق أوضح يتضمن التوقيت.',
             'desc' => 'ازدحام ملحوظ عند المدخل الرئيسي وقت الذروة مع صعوبة تمييز التفاصيل في التسجيل المرفق.',
             'cap' => 'ازدحام المدخل الرئيسي — وقت الذروة'],
            ['u' => $m('hadi', 2), 'cam' => 5, 'fl' => 1, 'st' => 'accepted', 'kind' => 'door', 'tag' => 'باب خلفي مفتوح لقاعة الاجتماعات',
             'obs' => [$dayAt(3, 9, 5), null], 'sent' => $dayAt(3, 9, 40), 'processed' => $dayAt(3, 12, 10),
             'desc' => 'الباب الخلفي لقاعة الاجتماعات ظهر مفتوحاً ولا يوجد موظفون في الجوار وقت الرصد. تم إغلاقه والتأكد من إحكام القفل وإبلاغ الحارس المناوب لتعزيز المرور على هذا المقطع.',
             'cap' => 'باب خلفي مفتوح — قاعة الاجتماعات'],
            ['u' => $m('hadi', 2), 'cam' => 10, 'fl' => 3, 'st' => 'accepted', 'kind' => 'corridor', 'tag' => 'تراكم صناديق يعيق مسار الإخلاء',
             'obs' => [$dayAt(3, 14, 20), $dayAt(3, 14, 35)], 'sent' => $dayAt(3, 15, 0), 'processed' => $dayAt(2, 10, 15),
             'desc' => 'تراكم صناديق كرتونية وبقايا تغليف في ممر الطابق الثالث يعيق مسار الإخلاء ويحجب جزءاً من رؤية الكاميرا. تم تصوير الموقع وإشعار مشرف النظافة لرفعها خلال اليوم.',
             'cap' => 'صناديق في الممر — إعاقة مسار الإخلاء'],
            ['u' => $m('mutaz.hamad', 8), 'cam' => 9, 'fl' => 2, 'st' => 'accepted', 'kind' => 'bag', 'tag' => 'حقيبة متروكة قرب المصعد المركزي',
             'obs' => [$dayAt(2, 10, 30), $dayAt(2, 10, 52)], 'sent' => $dayAt(2, 11, 10), 'processed' => $dayAt(2, 14, 5),
             'desc' => 'حقيبة ظهر متروكة قرب المصعد المركزي في الطابق الثاني لأكثر من عشرين دقيقة دون أن يقترب منها أحد. تم إبلاغ غرفة الأمن التي فحصتها وأعادتها لصاحبها بعد التحقق من هويته.',
             'cap' => 'حقيبة متروكة — المصعد المركزي'],
            ['u' => $m('rami', 4), 'cam' => 11, 'fl' => 2, 'st' => 'accepted', 'kind' => 'sign', 'tag' => 'لوحة إرشادية ساقطة عند مخرج الطوارئ',
             'obs' => [$dayAt(2, 11, 15), null], 'sent' => $dayAt(2, 11, 45), 'processed' => $dayAt(2, 15, 20),
             'desc' => 'لوحة إرشادية ساقطة عند مخرج الطوارئ في الطابق الثاني تحجب الرؤية عن جزء من الممر. تم تثبيتها مؤقتاً وإبلاغ الصيانة لإعادة تركيبها وتثبيتها بشكل دائم.',
             'cap' => 'لوحة ساقطة — مخرج الطوارئ'],
            ['u' => $m('tariq', 1), 'cam' => 21, 'fl' => 4, 'st' => 'accepted', 'kind' => 'crowd', 'tag' => 'تجمع مراجعين يعيق مدخل الاستوديو',
             'obs' => [$dayAt(2, 13, 0), $dayAt(2, 13, 20)], 'sent' => $dayAt(2, 13, 45), 'processed' => $dayAt(2, 16, 40),
             'desc' => 'تجمع عدد من المراجعين أمام مدخل الاستوديو في الطابق الرابع بشكل يعيق حركة الدخول والخروج وقت الذروة. تم تنظيم الدخول على دفعات بالتعاون مع عناصر الأمن حتى انسياب الحركة.',
             'cap' => 'تجمع مراجعين — مدخل الاستوديو'],
            ['u' => $m('hadi', 2), 'cam' => 12, 'fl' => 2, 'st' => 'pending', 'kind' => 'person', 'tag' => 'حركة ليلية قرب البوابة الجانبية',
             'obs' => [$dayAt(1, 22, 40), $dayAt(1, 23, 15)], 'sent' => $dayAt(1, 23, 20),
             'desc' => 'رصد شخص يتجول بمحاذاة السور قرب البوابة الجانبية بعد انتهاء الدوام الرسمي لأكثر من عشر دقائق، مع توقف متكرر والنظر باتجاه الداخل. تم توثيق الفترة كاملة وإبقاء الكاميرا على المتابعة الحية.',
             'cap' => 'حركة ليلية — البوابة الجانبية'],
            ['u' => $m('hamza', 3), 'cam' => 14, 'fl' => 5, 'st' => 'accepted', 'kind' => 'corridor', 'tag' => 'انطفاء إضاءة ممر الطابق الخامس',
             'obs' => [$pastToday(6, 15), null], 'sent' => null, 'processed' => null,
             'desc' => 'إضاءة ممر الطابق الخامس مطفأة بالكامل والرؤية شبه معدومة في المقطع الأوسط. تم التحقق من لوحة القواطع وإبلاغ الصيانة التي أعادت التيار خلال ساعة.',
             'cap' => 'انطفاء إضاءة الممر — الطابق الخامس'],
            ['u' => $m('rami', 4), 'cam' => 8, 'fl' => 3, 'st' => 'accepted', 'kind' => 'static', 'tag' => 'انقطاع متقطع في بث الكاميرا',
             'obs' => [$pastToday(7, 30), null], 'sent' => null, 'processed' => null,
             'desc' => 'انقطاع متقطع في بث الكاميرا الثامنة بالطابق الثالث مع تجمد الصورة لثوانٍ متكررة أثناء المتابعة الصباحية. تم فحص التوصيلات ووحدة التغذية واستقر البث بعد إعادة التثبيت.',
             'cap' => 'انقطاع البث — فحص فني'],
            ['u' => $m('mutaz.hamad', 8), 'cam' => 6, 'fl' => 3, 'st' => 'pending', 'kind' => 'card', 'tag' => 'محاولة دخول ببطاقة زميل',
             'obs' => [$now->copy()->subHours(2), null], 'sent' => null,
             'desc' => 'موظف يحاول الدخول من الباب الممغنط في الطابق الثالث باستخدام بطاقة زميله بعد رفض بطاقته الخاصة. تم توثيق المحاولة وإبلاغ المشرف المباشر لاستكمال التحقق.',
             'cap' => 'دخول ببطاقة الغير — الباب الممغنط'],
            ['u' => $m('tariq', 1), 'cam' => 7, 'fl' => 2, 'st' => 'draft', 'kind' => 'archive', 'tag' => 'صوت إنذار من جهة الأرشيف',
             'obs' => [$now->copy()->subMinutes(40), null], 'sent' => null,
             'desc' => 'مسودة أولية: صوت إنذار متقطع يُسمع من جهة غرفة الأرشيف في الطابق الثاني دون ظهور دخان أو حركة غير طبيعية على الكاميرا. قيد الاستكمال والتحقق قبل الإرسال.',
             'cap' => 'مسودة — صوت إنذار الأرشيف'],
        ];

        $notes = [];
        foreach ($noteDefs as $d) {
            // اشتقاق sent/processed للبنود المتأخرة بدل كتابتها يدوياً
            $sent = $d['sent'] ?? ($d['st'] !== 'draft' ? $d['obs'][0]->copy()->addMinutes(25) : null);
            if ($sent && $sent->greaterThan($now)) {
                $sent = $now->copy()->subMinutes(10);
            }
            $processed = $d['processed'] ?? null;
            if (in_array($d['st'], ['accepted', 'rejected']) && !$processed && $sent) {
                $processed = $sent->copy()->addHours(2);
                if ($processed->greaterThan($now)) {
                    $processed = $now->copy()->subMinutes(5);
                }
            }
            $created = ($sent ?? $d['obs'][0])->copy()->addMinutes(3);
            if ($created->greaterThan($now)) {
                $created = $now->copy();
            }

            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected']) ? $mainWriter->id : null,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $created, 'updated_at' => $processed ?? $created]);
            $n->refresh();
            $n->tag = $d['tag'];

            $when = $n->observed_at->format('Y-m-d H:i');
            $svg = $this->svgScene($n->camera_number, $n->floor_number, $when, $d['kind'], $d['cap']);
            $path = 'notes/' . $n->id . '/' . (string) Str::uuid() . '.svg';
            $disk->put($path, $svg);
            Attachment::create([
                'note_id' => $n->id,
                'file_path' => $path,
                'original_name' => 'كاميرا-' . $n->camera_number . '-لقطة.svg',
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $notes[] = $n;
            $this->command->line("note #{$n->id} user={$d['u']} cam={$n->camera_number} status={$d['st']}");
        }

        // ── 2ب) الملاحظات الأجنبية (إنكليزية) — لاختبار طبقة الترجمة ───────
        foreach ($foreignNoteDefs as $d) {
            $sent = $d['sent'] ?? ($d['st'] !== 'draft' ? $d['obs'][0]->copy()->addMinutes(25) : null);
            if ($sent && $sent->greaterThan($now)) {
                $sent = $now->copy()->subMinutes(10);
            }
            $processed = $d['processed'] ?? null;
            if (in_array($d['st'], ['accepted', 'rejected']) && !$processed && $sent) {
                $processed = $sent->copy()->addHours(2);
                if ($processed->greaterThan($now)) {
                    $processed = $now->copy()->subMinutes(5);
                }
            }
            $created = ($sent ?? $d['obs'][0])->copy()->addMinutes(3);
            if ($created->greaterThan($now)) {
                $created = $now->copy();
            }

            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected']) ? $mainWriter->id : null,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $created, 'updated_at' => $processed ?? $created]);
            $n->refresh();
            $n->tag = $d['tag'];

            $when = $n->observed_at->format('Y-m-d H:i');
            $svg = $this->svgScene($n->camera_number, $n->floor_number, $when, $d['kind'], $d['cap']);
            $path = 'notes/' . $n->id . '/' . (string) Str::uuid() . '.svg';
            $disk->put($path, $svg);
            Attachment::create([
                'note_id' => $n->id,
                'file_path' => $path,
                'original_name' => 'Camera-' . $n->camera_number . '-Snapshot.svg',
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $notes[] = $n;
            $this->command->line("foreign note #{$n->id} user={$d['u']} cam={$n->camera_number} status={$d['st']}");
        }

        // ── 3) الإرسالات العامة ──────────────────────────────────────────
        $subDefs = [
            ['u' => $m('tariq', 1), 'fl' => '5', 'cam' => '14', 'st' => 'pending', 'kind' => 'corridor', 'writers' => [$mainWriter->id, $secondWriter->id],
             'obs' => [$dayAt(1, 10, 0), null], 'sent' => $dayAt(1, 10, 30),
             'desc' => 'طلب مراجعة شاملة لمنظومة الإضاءة الاحتياطية في الطوابق العلوية بعد تكرار حالات الانطفاء خلال الأسبوع، مع اقتراح جدول فحص دوري للمخارج والقواطع.',
             'cap' => 'مراجعة الإضاءة الاحتياطية — الطوابق العلوية'],
            ['u' => $m('hadi', 2), 'fl' => '2', 'cam' => '9', 'st' => 'accepted', 'kind' => 'archive', 'writers' => [$mainWriter->id],
             'obs' => [$dayAt(2, 9, 0), null], 'sent' => $dayAt(2, 9, 30), 'processed' => $dayAt(2, 14, 0),
             'desc' => 'إرسالية صيانة: ثلاث كاميرات في الجناح الشرقي من الطابق الثاني بحاجة إلى تنظيف العدسات وإعادة ضبط زوايا التغطية بعد ملاحظة ضبابية في الصورة.',
             'cap' => 'صيانة كاميرات — الجناح الشرقي'],
            ['u' => $m('hamza', 3), 'fl' => '1', 'cam' => '2', 'st' => 'accepted', 'kind' => 'person', 'writers' => [$mainWriter->id, $thirdWriter->id],
             'obs' => [$dayAt(3, 11, 0), null], 'sent' => $dayAt(3, 11, 30), 'processed' => $dayAt(2, 10, 0),
             'desc' => 'مقترح تحديث جدول مناوبات حراس البوابة الخلفية لتغطية فترة الظهيرة التي تكررت فيها الملاحظات، مع تثبيت نقطة تمركز إضافية قرب المستودع.',
             'cap' => 'تحديث مناوبات الحراسة — البوابة الخلفية'],
            ['u' => $m('rami', 4), 'fl' => '3', 'cam' => '6', 'st' => 'rejected', 'kind' => 'sign', 'writers' => [$secondWriter->id],
             'obs' => [$dayAt(4, 15, 0), null], 'sent' => $dayAt(4, 15, 20), 'processed' => $dayAt(3, 9, 0),
             'reason' => 'مكرر: الطلب نفسه قيد التنفيذ ضمن خطة الصيانة الحالية، وسيُعلن عند التركيب.',
             'desc' => 'طلب تزويد ممرات الطابق الثالث بلوحات إرشادية جديدة عند مخارج الطوارئ.',
             'cap' => 'لوحات إرشادية — ممرات الطابق الثالث'],
            ['u' => $m('mutaz.hamad', 8), 'fl' => 'الأرضي', 'cam' => 'عامة', 'st' => 'draft', 'kind' => 'archive', 'writers' => [],
             'obs' => [$now->copy()->subHour(), null], 'sent' => null,
             'desc' => 'مسودة: حصر أولي بالنقاط العمياء في التغطية بالطابق الأرضي تمهيداً لاقتراح مواقع كاميرات إضافية. قيد الاستكمال قبل الإرسال.',
             'cap' => 'مسودة — النقاط العمياء في التغطية'],
        ];

        $subs = [];
        foreach ($subDefs as $d) {
            $created = ($d['sent'] ?? $d['obs'][0])->copy()->addMinutes(3);
            $s = GeneralSubmission::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected']) ? $mainWriter->id : null,
                'sent_at' => $d['sent'],
                'processed_at' => $d['processed'] ?? null,
            ]);
            GeneralSubmission::where('id', $s->id)->update(['created_at' => $created, 'updated_at' => ($d['processed'] ?? $created)]);
            if (!empty($d['writers'])) {
                $s->reportWriters()->sync(array_values(array_unique($d['writers'])));
            }
            $s->refresh();

            $when = $s->observed_at->format('Y-m-d H:i');
            $svg = $this->svgScene($s->camera_number, $s->floor_number, $when, $d['kind'], $d['cap']);
            $path = 'submissions/' . $s->id . '/' . (string) Str::uuid() . '.svg';
            $disk->put($path, $svg);
            GeneralSubmissionAttachment::create([
                'general_submission_id' => $s->id,
                'file_path' => $path,
                'original_name' => 'إرسالية-' . $s->id . '-لقطة.svg',
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $subs[] = $s;
            $this->command->line("submission #{$s->id} user={$d['u']} status={$d['st']}");
        }

        // ── 3ب) الإرسالات العامة الأجنبية (إنكليزية) — لاختبار طبقة الترجمة ──
        $foreignSubDefs = [
            ['u' => $m('tariq', 1), 'fl' => '3', 'cam' => '17', 'st' => 'pending', 'kind' => 'archive', 'writers' => [$mainWriter->id, $secondWriter->id],
             'obs' => [$dayAt(2, 11, 0), null], 'sent' => $dayAt(2, 11, 30),
             'desc' => 'Request for comprehensive review of HVAC system maintenance schedule across all floors following repeated temperature complaints from staff. Propose quarterly filter replacement and annual duct cleaning.',
             'cap' => 'HVAC maintenance review — all floors'],
            ['u' => $m('hadi', 2), 'fl' => '1', 'cam' => '5', 'st' => 'accepted', 'kind' => 'corridor', 'writers' => [$mainWriter->id],
             'obs' => [$dayAt(1, 9, 30), null], 'sent' => $dayAt(1, 10, 0), 'processed' => $dayAt(1, 14, 30),
             'desc' => 'Maintenance dispatch: Emergency lighting units on 1st floor east wing require battery replacement. Three units showing fault indicators. Parts ordered and temporary units installed.',
             'cap' => 'Emergency lighting battery replacement — 1st floor'],
            ['u' => $m('rami', 4), 'fl' => 'Ground', 'cam' => 'Main', 'st' => 'rejected', 'kind' => 'sign', 'writers' => [$secondWriter->id],
             'obs' => [$dayAt(3, 14, 0), null], 'sent' => $dayAt(3, 14, 20), 'processed' => $dayAt(2, 10, 0),
             'reason' => 'Duplicate: Already covered in facilities upgrade plan approved last month.',
             'desc' => 'Request to install additional directional signage at main entrance and lobby area for visitor wayfinding.',
             'cap' => 'Additional wayfinding signage — main entrance'],
        ];

        foreach ($foreignSubDefs as $d) {
            $created = ($d['sent'] ?? $d['obs'][0])->copy()->addMinutes(3);
            $s = GeneralSubmission::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => $d['reason'] ?? null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected']) ? $mainWriter->id : null,
                'sent_at' => $d['sent'],
                'processed_at' => $d['processed'] ?? null,
            ]);
            GeneralSubmission::where('id', $s->id)->update(['created_at' => $created, 'updated_at' => ($d['processed'] ?? $created)]);
            if (!empty($d['writers'])) {
                $s->reportWriters()->sync(array_values(array_unique($d['writers'])));
            }
            $s->refresh();

            $when = $s->observed_at->format('Y-m-d H:i');
            $svg = $this->svgScene($s->camera_number, $s->floor_number, $when, $d['kind'], $d['cap']);
            $path = 'submissions/' . $s->id . '/' . (string) Str::uuid() . '.svg';
            $disk->put($path, $svg);
            GeneralSubmissionAttachment::create([
                'general_submission_id' => $s->id,
                'file_path' => $path,
                'original_name' => 'Submission-' . $s->id . '-Snapshot.svg',
                'mime_type' => 'image/svg+xml',
                'file_size' => strlen($svg),
            ]);
            $subs[] = $s;
            $this->command->line("foreign submission #{$s->id} user={$d['u']} status={$d['st']}");
        }

        // ── 4) التقارير: مبنية فعلياً على المقبولة (نفس اليوم حصراً) ─────
        $accepted = collect($notes)->where('status', 'accepted')->sortBy('observed_at')->values();
        $byDay = [];
        foreach ($accepted as $n) {
            $byDay[$n->observed_at->toDateString()][] = $n;
        }
        krsort($byDay);
        $reportDays = array_slice(array_keys($byDay), 0, 3);

        $tz = (string) config('app.timezone', 'UTC');
        $compose = function (Report $report, $orderedNotes) use ($tz) {
            $author = $report->author?->name ?? '—';
            $dateStr = $report->report_date instanceof \DateTimeInterface ? $report->report_date->format('Y-m-d') : (string) $report->report_date;
            $lines = [(string) $report->title, '', 'بيانات التقرير: التاريخ ' . $dateStr . ' | الرقم #' . $report->id . ' | الكاتب ' . $author, '', 'أولاً — الملخص التنفيذي', trim((string) $report->summary) !== '' ? trim((string) $report->summary) : '—', '', 'ثانياً — جدول الملاحظات (' . count($orderedNotes) . ')'];
            $i = 0;
            foreach ($orderedNotes as $n) {
                $i++;
                $at = $n->observed_at ? Carbon::parse($n->observed_at)->format('H:i') : '—';
                $lines[] = "{$i} | الوقت {$at} | طابق {$n->floor_number} | كاميرا {$n->camera_number} | " . trim(preg_replace('/\s+/', ' ', (string) $n->description));
            }
            $lines[] = '';
            $lines[] = 'ثالثاً — التوصيات';
            $lines[] = trim((string) $report->recommendations) !== '' ? trim((string) $report->recommendations) : '— لا توجد توصيات —';
            $lines[] = '';
            $lines[] = 'التوقيع: ' . $author . ' | رُكّب بتاريخ ' . now($tz)->format('Y-m-d H:i');

            return implode("\n", $lines);
        };

        $reports = [];
        $ri = 0;
        foreach ($reportDays as $day) {
            $ri++;
            $dayNotes = collect($byDay[$day])->sortBy('observed_at')->values();
            $cams = $dayNotes->pluck('camera_number')->unique()->sort()->values()->join('، ');
            $floors = $dayNotes->pluck('floor_number')->unique()->sort()->values()->join('، ');
            $tags = $dayNotes->pluck('tag')->unique()->values()->join('؛ ');
            $from = $dayNotes->first()->observed_at->format('H:i');
            $to = $dayNotes->last()->observed_at->format('H:i');
            $summary = "خلال يوم {$day} وثّق المراقبون " . $dayNotes->count() . " ملاحظات مقبولة بين الساعة {$from} والساعة {$to}، تركزت في الطوابق ({$floors}) عبر الكاميرات ({$cams})، وشملت: {$tags}. عولجت جميعها ميدانياً أو أحيلت إلى الجهة المختصة دون تسجيل أي حادث.";
            $recs = [
                "1) إعطاء أولوية المتابعة الميدانية لمعالجة: {$dayNotes->first()->tag}، والتحقق من إغلاقها خلال 48 ساعة.",
                '2) تثبيت جولة تفقد إضافية على المواقع المذكورة أعلاه خلال المناوبة المسائية وتوثيقها بالصور.',
                '3) أرشفة اللقطات المرفقة مع هذا التقرير للرجوع إليها عند تقييم تكرار الحالات في التقارير القادمة.',
            ];
            if ($ri % 2 === 0) {
                $recs = [$recs[1], $recs[0], $recs[2]];
            }
            $publishedAt = Carbon::parse($day, $tz)->setTime(17, 30);
            if ($publishedAt->greaterThan($now)) {
                $publishedAt = $now->copy()->subMinutes(20);
            }
            $report = Report::create([
                'author_id' => $mainWriter->id,
                'title' => 'التقرير اليومي — ' . $day,
                'summary' => $summary,
                'recommendations' => implode("\n", $recs),
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
            $report->update(['content' => $compose($report->fresh(['notes', 'author']), $dayNotes)]);
            $report->refresh();
            $report->revisions()->create(['editor_id' => $mainWriter->id, 'content_snapshot' => (string) $report->content]);
            Report::where('id', $report->id)->update(['created_at' => $publishedAt->copy()->subHour(), 'updated_at' => $publishedAt]);
            $reports[] = $report->refresh();
            $this->command->line("report #{$report->id} date={$day} notes=" . $dayNotes->count() . ' published');
        }

        // مسودة تقرير ليوم بلا ملاحظات مقبولة (تعرض حالة المسودة الفارغة)
        $draftDay = null;
        for ($i = 1; $i <= 6; $i++) {
            $cand = $now->copy()->subDays($i)->toDateString();
            if (!isset($byDay[$cand])) {
                $draftDay = $cand;
                break;
            }
        }
        $draftDay ??= $now->copy()->toDateString();
        $draftReport = Report::create([
            'author_id' => $mainWriter->id,
            'title' => 'التقرير اليومي — ' . $draftDay,
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => $draftDay,
        ]);
        $reports[] = $draftReport->refresh();
        $this->command->line("report #{$draftReport->id} date={$draftDay} draft (no notes yet)");

        // ── 4ب) نسخة النظام المعتمدة — بدونها تردّ المعاينة والتصدير 404 ──
        // (النشر الطبيعي يولّدها تلقائياً؛ البذر المباشر كان يتجاوزها)
        $builder = app(ReportDataBuilder::class);
        $selector = app(ReportTemplateSelector::class);
        $htmlSvc = app(ReportHtmlRenderingService::class);
        $maxNotes = (int) config('report_sheets.max_notes', 7);
        foreach ($reports as $r) {
            $r->loadMissing(['author', 'notes']);
            $n = $r->notes->count();
            if (!$r->isPublished() || $n < 1 || $n > $maxNotes) {
                continue;
            }
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
            if (!$selected) {
                continue;
            }
            $payload = ReportRenderPayload::fromArray(array_merge($payload->toAiArray(), ['template' => $selected['key']]));
            $row = ReportSheetRender::create([
                'report_id' => $r->id,
                'generation_no' => 1,
                'data_version' => 1,
                'template' => $selected['key'],
                'payload_hash' => $payload->hash(),
                'system_hash' => $htmlSvc->systemHash($r->fresh(['notes', 'author'])),
                'payload' => $payload->toAiArray(),
                'image_path' => null,
            ]);
            $r->update(['ai_sheet_image_path' => null, 'ai_sheet_generated_at' => now(), 'ai_sheet_data_hash' => $row->payload_hash]);
            $current = $htmlSvc->systemHash($r->fresh(['notes', 'author']));
            if ($row->system_hash !== $current) {
                $row->update(['system_hash' => $current]);
            }
            $st = $htmlSvc->htmlState($r->fresh(['notes', 'author']))['state'] ?? '?';
            $this->command->line("render report#{$r->id} template={$selected['key']} state=$st");
        }

        // ── 5) الإشعارات المستحقة فقط ────────────────────────────────────
        $recentCutoff = $now->copy()->subDay()->startOfDay();
        $made = 0;
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
        $usersById = User::all()->keyBy('id');

        foreach ($notes as $n) {
            if (!$n->sent_at) {
                continue; // المسودة: لا إشعار مستحق
            }
            $sender = $usersById->get($n->user_id);
            $senderName = $sender?->name ?? 'مراقب';
            $isRecent = $n->sent_at->greaterThanOrEqualTo($recentCutoff);
            // واردة → الكتاب الثلاثة (نفس دور fan-out للكتاب)
            foreach ($writers as $w) {
                if ($w->id === $n->user_id) {
                    continue;
                }
                $mkNotif($w, new NoteSentNotification($n, $senderName), $n->sent_at->copy(), $isRecent ? null : $n->sent_at->copy()->addHours(2));
            }
            // قرار → مالك الملاحظة فقط
            $owner = $usersById->get($n->user_id);
            if ($owner && $n->status === 'accepted' && $n->processed_at) {
                $recent = $n->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new NoteAcceptedNotification($n, $mainWriter->name), $n->processed_at->copy(), $recent ? null : $n->processed_at->copy()->addHours(3));
            }
            if ($owner && $n->status === 'rejected' && $n->processed_at) {
                $recent = $n->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new NoteRejectedNotification($n, (string) $n->rejection_reason, $mainWriter->name), $n->processed_at->copy(), $recent ? null : $n->processed_at->copy()->addHours(3));
            }
        }

        foreach ($subs as $s) {
            if (!$s->sent_at) {
                continue;
            }
            $sender = $usersById->get($s->user_id);
            $senderName = $sender?->name ?? 'مراقب';
            $isRecent = $s->sent_at->greaterThanOrEqualTo($recentCutoff);
            $targets = $s->reportWriters->isNotEmpty() ? $s->reportWriters : collect([$mainWriter]);
            foreach ($targets as $w) {
                $mkNotif($w, new DispatchSentNotification($s, $senderName), $s->sent_at->copy(), $isRecent ? null : $s->sent_at->copy()->addHours(2));
            }
            $owner = $usersById->get($s->user_id);
            if ($owner && $s->status === 'accepted' && $s->processed_at) {
                $recent = $s->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new DispatchAcceptedNotification($s, $mainWriter->name), $s->processed_at->copy(), $recent ? null : $s->processed_at->copy()->addHours(3));
            }
            if ($owner && $s->status === 'rejected' && $s->processed_at) {
                $recent = $s->processed_at->greaterThanOrEqualTo($recentCutoff);
                $mkNotif($owner, new DispatchRejectedNotification($s, (string) $s->rejection_reason, $mainWriter->name), $s->processed_at->copy(), $recent ? null : $s->processed_at->copy()->addHours(3));
            }
        }

        foreach ($reports as $r) {
            if (!$r->isPublished() || !$r->visible_to_monitors || !$r->published_at) {
                continue; // المسودة: لا إشعار نشر
            }
            $isRecent = $r->published_at->greaterThanOrEqualTo($recentCutoff);
            foreach ($monitors as $mon) {
                $mkNotif($mon, new ReportPublishedNotification($r), $r->published_at->copy(), $isRecent ? null : $r->published_at->copy()->addDay());
            }
        }
        $unread = DB::table('notifications')->whereNull('read_at')->count();
        $this->command->info("NOTIFICATIONS made=$made unread=$unread");

        // ── 6) تحقق صارم ────────────────────────────────────────────────
        // Arabic notes check: allow English (foreign) notes which have mb_strlen == strlen
        $hasArabic = fn ($text) => preg_match('/[\x{0600}-\x{06FF}]/u', $text);
        $badAr = Note::get()->filter(fn ($n) => mb_strlen($n->description) >= strlen($n->description) && $hasArabic($n->description))->count()
            + GeneralSubmission::get()->filter(fn ($s) => mb_strlen($s->description) >= strlen($s->description) && $hasArabic($s->description))->count();
        $localNotes = Attachment::get()->filter->isLocal()->count();
        $localSubs = GeneralSubmissionAttachment::get()->filter->isLocal()->count();
        $future = Note::where('observed_at', '>', $now)->count()
            + GeneralSubmission::where('observed_at', '>', $now)->count();
        $pubEmpty = Report::where('status', 'published')->withCount('notes')->get()->filter(fn ($r) => $r->notes_count < 1)->count();
        $noRender = 0;
        try {
            $hs = app(ReportHtmlRenderingService::class);
            $mx = (int) config('report_sheets.max_notes', 7);
            foreach (Report::where('status', 'published')->withCount('notes')->get() as $r) {
                if ($r->notes_count < 1 || $r->notes_count > $mx) {
                    continue;
                }
                $st = $hs->htmlState($r)['state'] ?? 'none';
                if (!in_array($st, ['system', 'custom'], true)) {
                    $noRender++;
                }
            }
        } catch (\Throwable $e) {
            $noRender = -1;
        }
        $orphanNotif = 0;
        foreach (DatabaseNotification::all() as $n) {
            $d = $n->data;
            if (is_string($d)) {
                $d = json_decode($d, true);
            }
            if (!is_array($d)) {
                $orphanNotif++;
                continue;
            }
            if (isset($d['note_id']) && !Note::where('id', $d['note_id'])->exists()) {
                $orphanNotif++;
            }
            $sid = $d['submission_id'] ?? $d['general_submission_id'] ?? null;
            if ($sid && !GeneralSubmission::where('id', $sid)->exists()) {
                $orphanNotif++;
            }
            if (isset($d['report_id']) && !Report::where('id', $d['report_id'])->exists()) {
                $orphanNotif++;
            }
        }
        $this->command->info('DONE notes=' . Note::count() . ' subs=' . GeneralSubmission::count() . ' reports=' . Report::count() . " notif=" . DatabaseNotification::count() . " local_notes=$localNotes local_subs=$localSubs broken_arabic=$badAr future=$future pub_empty=$pubEmpty no_render=$noRender orphan_notif=$orphanNotif");
        if ($badAr > 0 || $future > 0 || $pubEmpty > 0 || $noRender !== 0 || $orphanNotif > 0 || $localNotes !== Note::count() || $localSubs !== GeneralSubmission::count()) {
            throw new \RuntimeException('SEED VERIFICATION FAILED');
        }
    }

    private function svgScene($cam, $floor, $when, $kind, $caption): string
    {
        $extra = match ($kind) {
            'person' => '<circle cx="430" cy="205" r="16" fill="#d8b24a"/><rect x="414" y="222" width="32" height="58" rx="8" fill="#d8b24a"/><rect x="120" y="120" width="400" height="10" fill="#2a3d2f"/>',
            'door' => '<rect x="270" y="110" width="100" height="170" rx="4" fill="#3d5a43" stroke="#e8e4d2" stroke-width="4"/><circle cx="354" cy="200" r="5" fill="#e8e4d2"/>',
            'static' => '<g stroke="#3d5a43" stroke-width="2"><line x1="60" y1="140" x2="580" y2="140"/><line x1="60" y1="180" x2="580" y2="180"/><line x1="60" y1="220" x2="580" y2="220"/><line x1="60" y1="260" x2="580" y2="260"/></g><text x="320" y="205" font-size="26" fill="#e05252" text-anchor="middle" font-family="sans-serif">NO SIGNAL</text>',
            'crowd' => '<g fill="#d8b24a"><circle cx="240" cy="220" r="14"/><circle cx="300" cy="210" r="14"/><circle cx="360" cy="220" r="14"/><circle cx="420" cy="212" r="14"/><rect x="200" y="238" width="260" height="42" rx="10"/></g>',
            'corridor' => '<rect x="80" y="120" width="480" height="160" fill="#0a0f0c"/><circle cx="320" cy="140" r="10" fill="none" stroke="#5f7568" stroke-width="4"/><line x1="320" y1="150" x2="320" y2="165" stroke="#5f7568" stroke-width="4"/>',
            'bag' => '<rect x="295" y="210" width="50" height="70" rx="10" fill="#d8b24a"/><rect x="308" y="195" width="24" height="20" rx="8" fill="none" stroke="#d8b24a" stroke-width="5"/><rect x="180" y="280" width="280" height="8" fill="#2a3d2f"/>',
            'sign' => '<rect x="250" y="150" width="140" height="60" rx="6" fill="#0a0f0c" stroke="#d8b24a" stroke-width="3"/><line x1="320" y1="210" x2="320" y2="280" stroke="#5f7568" stroke-width="6"/><line x1="270" y1="170" x2="370" y2="170" stroke="#d8b24a" stroke-width="4"/><line x1="270" y1="185" x2="340" y2="185" stroke="#5f7568" stroke-width="4"/>',
            'card' => '<rect x="250" y="170" width="140" height="90" rx="8" fill="#3d5a43" stroke="#e8e4d2" stroke-width="3"/><rect x="265" y="185" width="50" height="35" rx="4" fill="#d8b24a"/><line x1="265" y1="232" x2="375" y2="232" stroke="#e8e4d2" stroke-width="4"/>',
            default => '<rect x="180" y="130" width="280" height="150" rx="6" fill="none" stroke="#d8b24a" stroke-width="3" stroke-dasharray="10 6"/>',
        };
        $safeCap = htmlspecialchars($caption, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">'
            . '<rect width="640" height="360" fill="#0f1a13"/>'
            . '<g stroke="#1e2e22" stroke-width="1"><line x1="0" y1="90" x2="640" y2="90"/><line x1="0" y1="180" x2="640" y2="180"/><line x1="0" y1="270" x2="640" y2="270"/><line x1="160" y1="0" x2="160" y2="360"/><line x1="320" y1="0" x2="320" y2="360"/><line x1="480" y1="0" x2="480" y2="360"/></g>'
            . '<rect width="640" height="46" fill="#0a0f0c"/>'
            . '<circle cx="24" cy="23" r="7" fill="#e05252"/><text x="44" y="30" font-size="17" fill="#e8e4d2" font-family="sans-serif">REC</text>'
            . '<text x="616" y="30" font-size="17" fill="#e8e4d2" text-anchor="end" font-family="sans-serif">CAM ' . htmlspecialchars((string) $cam, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</text>'
            . $extra
            . '<rect y="314" width="640" height="46" fill="#0a0f0c"/>'
            . '<text x="320" y="335" font-size="16" fill="#e8e4d2" text-anchor="middle" font-family="sans-serif">' . $safeCap . ' — ' . $when . '</text>'
            . '</svg>';
    }
}
