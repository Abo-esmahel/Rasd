<?php

namespace Database\Seeders;

use App\Models\Attachment;
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
    public function run(): void
    {
        $now = now();
        $disk = Storage::disk('attachments');

        $writers = User::where('role', 'report_writer')->orderBy('id')->get();
        $monitors = User::where('role', 'monitor')->orderBy('id')->get();
        if ($writers->isEmpty() || $monitors->isEmpty()) {
            throw new \RuntimeException('SEED ABORT: writers or monitors missing — run DatabaseSeeder users first');
        }
        $mainWriter = $writers->first();
        $monitorByUsername = $monitors->keyBy('username');
        $m = fn (string $username, int $fallback) => $monitorByUsername->get($username)?->id ?? $monitors->firstWhere('id', $fallback)?->id ?? $monitors->first()->id;

        $noteIds = Note::pluck('id')->all();
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

        try {
            foreach (Report::whereIn('id', $reportIds)->get(['ai_sheet_image_path']) as $r) {
                if (!$r->ai_sheet_image_path) continue;
                try {
                    if ($disk->exists($r->ai_sheet_image_path)) {
                        $disk->delete($r->ai_sheet_image_path);
                        $files++;
                    }
                } catch (\Throwable $e) {
                }
            }
            if (Schema::hasTable('report_sheet_renders')) {
                foreach (DB::table('report_sheet_renders')->whereIn('report_id', $reportIds)->get(['image_path']) as $r) {
                    if (empty($r->image_path)) continue;
                    try {
                        if ($disk->exists($r->image_path)) {
                            $disk->delete($r->image_path);
                            $files++;
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $notifDel = DatabaseNotification::query()->delete();

        DB::transaction(function () use ($noteIds, $reportIds) {
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
            Note::whereIn('id', $noteIds)->delete();
            Report::whereIn('id', $reportIds)->delete();
        });

        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("DELETE FROM sqlite_sequence WHERE name IN ('notes','attachments','reports','report_note','report_revisions','report_sheet_renders')");
            }
        } catch (\Throwable $e) {
        }

        $this->command->warn("WIPE notes=" . count($noteIds) . ' reports=' . count($reportIds) . " files=$files notif=$notifDel");

        $dayAt = function (int $daysAgo, int $h, int $min) use ($now) {
            return $now->copy()->subDays($daysAgo)->setTime($h, $min, 0);
        };
        $pastToday = function (int $h, int $min) use ($now) {
            $t = $now->copy()->setTime($h, $min, 0);
            if ($t->greaterThan($now->copy()->subMinutes(5))) {
                $t = $now->copy()->subDay()->setTime($h, $min, 0);
            }
            return $t;
        };

        $oldNotesData = [
            ['u' => $m('hamza', 3), 'cam' => 2, 'fl' => 1, 'st' => 'accepted', 'kind' => 'door',
             'obs' => [$dayAt(5, 10, 0), null], 'sent' => $dayAt(5, 10, 30), 'processed' => $dayAt(4, 9, 0),
             'desc' => 'باب المستودع الجانبي في الطابق الأول ظهر غير محكم الإغلاق صباحاً مع وجود فجوة واضحة عند القفل.',
             'cap' => 'باب المستودع الجانبي — إغلاق غير محكم'],
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'st' => 'accepted', 'kind' => 'archive',
             'obs' => [$dayAt(4, 16, 20), null], 'sent' => $dayAt(4, 17, 0), 'processed' => $dayAt(3, 11, 30),
             'desc' => 'ازدحام ملحوظ عند المدخل الرئيسي وقت الذروة مع صعوبة تمييز التفاصيل في التسجيل المرفق.',
             'cap' => 'ازدحام المدخل الرئيسي — وقت الذروة'],
            ['u' => $m('hadi', 2), 'cam' => 5, 'fl' => 1, 'st' => 'accepted', 'kind' => 'door',
             'obs' => [$dayAt(3, 9, 5), null], 'sent' => $dayAt(3, 9, 40), 'processed' => $dayAt(3, 12, 10),
             'desc' => 'الباب الخلفي لقاعة الاجتماعات ظهر مفتوحاً ولا يوجد موظفون في الجوار وقت الرصد. تم إغلاقه والتأكد من إحكام القفل.',
             'cap' => 'باب خلفي مفتوح — قاعة الاجتماعات'],
            ['u' => $m('hadi', 2), 'cam' => 10, 'fl' => 3, 'st' => 'accepted', 'kind' => 'corridor',
             'obs' => [$dayAt(3, 14, 20), $dayAt(3, 14, 35)], 'sent' => $dayAt(3, 15, 0), 'processed' => $dayAt(2, 10, 15),
             'desc' => 'تراكم صناديق كرتونية وبقايا تغليف في ممر الطابق الثالث يعيق مسار الإخلاء ويحجب جزءاً من رؤية الكاميرا.',
             'cap' => 'صناديق في الممر — إعاقة مسار الإخلاء'],
            ['u' => $m('tariq', 1), 'cam' => 21, 'fl' => 4, 'st' => 'accepted', 'kind' => 'crowd',
             'obs' => [$dayAt(2, 13, 0), $dayAt(2, 13, 20)], 'sent' => $dayAt(2, 13, 45), 'processed' => $dayAt(2, 16, 40),
             'desc' => 'تجمع عدد من المراجعين أمام مدخل الاستوديو في الطابق الرابع بشكل يعيق حركة الدخول والخروج وقت الذروة.',
             'cap' => 'تجمع مراجعين — مدخل الاستوديو'],
        ];

        $oldNotes = [];
        foreach ($oldNotesData as $d) {
            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => null,
                'processed_by' => $mainWriter->id,
                'sent_at' => $d['sent'],
                'processed_at' => $d['processed'],
            ]);
            $created = $d['sent']->copy()->addMinutes(3);
            Note::where('id', $n->id)->update(['created_at' => $created, 'updated_at' => $d['processed']]);
            $n->refresh();
            $n->tag = $d['tag'] ?? $d['desc'];

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
            $oldNotes[] = $n;
            $this->command->line("old note #{$n->id} user={$d['u']} cam={$n->camera_number} status={$d['st']}");
        }

        $rejectedOld = [
            ['u' => $m('hamza', 3), 'cam' => 2, 'fl' => 1, 'st' => 'rejected', 'kind' => 'door',
             'obs' => [$dayAt(7, 10, 0), null], 'sent' => $dayAt(7, 10, 30), 'processed' => $dayAt(6, 9, 0),
             'reason' => 'مكررة: سبق توثيق الحالة نفسها في ملاحظة سابقة وما تزال قيد المعالجة لدى الصيانة.',
             'desc' => 'باب المستودع الجانبي في الطابق الأول ظهر غير محكم الإغلاق صباحاً مع وجود فجوة واضحة عند القفل.',
             'cap' => 'باب المستودع — مكررة'],
            ['u' => $m('rami', 4), 'cam' => 3, 'fl' => 1, 'st' => 'rejected', 'kind' => 'archive',
             'obs' => [$dayAt(6, 16, 20), null], 'sent' => $dayAt(6, 17, 0), 'processed' => $dayAt(5, 11, 30),
             'reason' => 'اللقطة المرفقة غير واضحة ولا يظهر فيها وقت الرصد. أعد الإرسال مع مرفق أوضح يتضمن التوقيت.',
             'desc' => 'ازدحام ملحوظ عند المدخل الرئيسي وقت الذروة مع صعوبة تمييز التفاصيل.',
             'cap' => 'ازدحام المدخل — لقطة غير واضحة'],
        ];

        foreach ($rejectedOld as $d) {
            $n = Note::create([
                'user_id' => $d['u'],
                'floor_number' => $d['fl'],
                'camera_number' => $d['cam'],
                'observed_at' => $d['obs'][0],
                'observed_end_at' => $d['obs'][1],
                'description' => $d['desc'],
                'status' => 'rejected',
                'rejection_reason' => $d['reason'],
                'processed_by' => $mainWriter->id,
                'sent_at' => $d['sent'],
                'processed_at' => $d['processed'],
            ]);
            $created = $d['sent']->copy()->addMinutes(3);
            Note::where('id', $n->id)->update(['created_at' => $created, 'updated_at' => $d['processed']]);
            $n->refresh();
            $n->tag = $d['desc'];

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
            $oldNotes[] = $n;
            $this->command->line("old rejected note #{$n->id} user={$d['u']} cam={$n->camera_number}");
        }

        $oldAccepted = collect($oldNotes)->where('status', 'accepted')->sortBy('observed_at')->values();
        $byDay = [];
        foreach ($oldAccepted as $n) {
            $byDay[$n->observed_at->toDateString()][] = $n;
        }
        krsort($byDay);

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
        foreach ($byDay as $day => $dayNotesRaw) {
            $ri++;
            $dayNotes = collect($dayNotesRaw)->sortBy('observed_at')->values();
            $cams = $dayNotes->pluck('camera_number')->unique()->sort()->values()->join('، ');
            $floors = $dayNotes->pluck('floor_number')->unique()->sort()->values()->join('، ');
            $tags = $dayNotes->map(fn($n) => $n->tag ?? $n->description)->unique()->values()->join('؛ ');
            $from = $dayNotes->first()->observed_at->format('H:i');
            $to = $dayNotes->last()->observed_at->format('H:i');
            $summary = "خلال يوم {$day} وثّق المراقبون " . $dayNotes->count() . " ملاحظات مقبولة بين الساعة {$from} والساعة {$to}، تركزت في الطوابق ({$floors}) عبر الكاميرات ({$cams})، وشملت: {$tags}. عولجت جميعها ميدانياً أو أحيلت إلى الجهة المختصة.";
            $recs = [
                "1) إعطاء أولوية المتابعة الميدانية لمعالجة: " . ($dayNotes->first()->tag ?? 'أول ملاحظة') . "، والتحقق من إغلاقها خلال 48 ساعة.",
                '2) تثبيت جولة تفقد إضافية على المواقع المذكورة أعلاه خلال المناوبة المسائية.',
                '3) أرشفة اللقطات المرفقة مع هذا التقرير للرجوع إليها عند تقييم تكرار الحالات.',
            ];
            $publishedAt = Carbon::parse($day, $tz)->setTime(17, 30);
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
            $this->command->line("old report #{$report->id} date={$day} notes=" . $dayNotes->count() . ' published');
        }

        $builder = app(ReportDataBuilder::class);
        $selector = app(ReportTemplateSelector::class);
        $htmlSvc = app(ReportHtmlRenderingService::class);
        $maxNotes = (int) config('report_sheets.max_notes', 7);
        $renderSheet = function (Report $r) use ($builder, $selector, $htmlSvc, $maxNotes) {
            $r->loadMissing(['author', 'notes']);
            if (!$r->isPublished()) return;
            $n = $r->notes->count();
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
                $this->command->line("render report#{$r->id} template={$selected['key']}");
            } catch (\Throwable $e) {
                $this->command->error("render failed for report#{$r->id}: " . $e->getMessage());
            }
        };
        foreach ($reports as $r) {
            $renderSheet($r);
        }

        $todayNotesData = [
            ['u' => $m('tariq', 1), 'cam' => 7, 'fl' => 2, 'st' => 'draft', 'kind' => 'archive',
             'obs' => [$now->copy()->subMinutes(40), null],
             'desc' => 'مسودة أولية: صوت إنذار متقطع يُسمع من جهة غرفة الأرشيف في الطابق الثاني دون ظهور دخان أو حركة غير طبيعية على الكاميرا.',
             'cap' => 'صوت إنذار — الأرشيف'],
            ['u' => $m('hadi', 2), 'cam' => 9, 'fl' => 2, 'st' => 'pending', 'kind' => 'person',
             'obs' => [$now->copy()->subHours(2), $now->copy()->subHours(1)->subMinutes(45)],
             'desc' => 'شخص يتجول بمحاذاة السور قرب البوابة الجانبية بعد انتهاء الدوام الرسمي لأكثر من عشر دقائق، مع توقف متكرر والنظر باتجاه الداخل.',
             'cap' => 'حركة مشبوهة — البوابة الجانبية'],
            ['u' => $m('hamza', 3), 'cam' => 14, 'fl' => 5, 'st' => 'accepted', 'kind' => 'corridor',
             'obs' => [$pastToday(6, 15), null], 'sent' => $pastToday(6, 40), 'processed' => null,
             'desc' => 'إضاءة ممر الطابق الخامس مطفأة بالكامل والرؤية شبه معدومة في المقطع الأوسط. تم التحقق من لوحة القواطع وإبلاغ الصيانة.',
             'cap' => 'انطفاء إضاءة الممر — الطابق الخامس'],
            ['u' => $m('rami', 4), 'cam' => 8, 'fl' => 3, 'st' => 'pending', 'kind' => 'static',
             'obs' => [$pastToday(7, 30), null], 'sent' => $pastToday(7, 50),
             'desc' => 'انقطاع متقطع في بث الكاميرا الثامنة بالطابق الثالث مع تجمد الصورة لثوانٍ متكررة أثناء المتابعة الصباحية.',
             'cap' => 'انقطاع البث — الكاميرا الثامنة'],
            ['u' => $m('tariq', 1), 'cam' => 12, 'fl' => 2, 'st' => 'draft', 'kind' => 'door',
             'obs' => [$now->copy()->subMinutes(15), null],
             'desc' => 'مسودة: باب الطوارئ الشرقي يبدو بحاجة إلى تشحيم بعد صوت طقطقة أثناء الفتح. لم يُرسل بعد.',
             'cap' => 'باب طوارئ — صوت طقطقة'],
            ['u' => $m('hadi', 2), 'cam' => 16, 'fl' => 3, 'st' => 'accepted', 'kind' => 'bag',
             'obs' => [$pastToday(8, 10), $pastToday(8, 25)], 'sent' => $pastToday(8, 30), 'processed' => null,
             'desc' => 'حقيبة ظهر متروكة قرب المصعد المركزي في الطابق الثالث لأكثر من عشرين دقيقة دون أن يقترب منها أحد. تم إبلاغ غرفة الأمن.',
             'cap' => 'حقيبة متروكة — المصعد المركزي'],
            ['u' => $m('rami', 4), 'cam' => 19, 'fl' => 5, 'st' => 'draft', 'kind' => 'sign',
             'obs' => [$now->copy()->subMinutes(55), null],
             'desc' => 'مسودة: لوحة إرشادية عند مخرج الطابق الخامس مائلة وتحتاج إلى إعادة تثبيت.',
             'cap' => 'لوحة مائلة — مخرج الطابق الخامس'],
        ];

        $todayNotes = [];
        foreach ($todayNotesData as $d) {
            $sent = $d['sent'] ?? ($d['st'] !== 'draft' ? $d['obs'][0]->copy()->addMinutes(25) : null);
            if ($sent && $sent->greaterThan($now)) {
                $sent = $now->copy()->subMinutes(10);
            }
            $processed = $d['processed'] ?? null;
            if (in_array($d['st'], ['accepted', 'rejected']) && !$processed && $sent) {
                $processed = $sent->copy()->addHours(1);
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
                'observed_end_at' => $d['obs'][1] ?? null,
                'description' => $d['desc'],
                'status' => $d['st'],
                'rejection_reason' => null,
                'processed_by' => in_array($d['st'], ['accepted', 'rejected']) ? $mainWriter->id : null,
                'sent_at' => $sent,
                'processed_at' => $processed,
            ]);
            Note::where('id', $n->id)->update(['created_at' => $created, 'updated_at' => $processed ?? $created]);
            $n->refresh();
            $n->tag = $d['desc'];

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
            $todayNotes[] = $n;
            $this->command->line("today note #{$n->id} user={$d['u']} cam={$n->camera_number} status={$d['st']}");
        }

        $todayAccepted = collect($todayNotes)->where('status', 'accepted')->sortBy('observed_at')->values();
        if ($todayAccepted->isNotEmpty()) {
            $recentNote = $todayAccepted->first();
            $recentDay = $recentNote->observed_at->toDateString();
            $recentPublishedAt = $now->copy()->subHour();
            $recentReport = Report::create([
                'author_id' => $mainWriter->id,
                'title' => 'التقرير اليومي — ' . $recentDay,
                'summary' => "خلال يوم {$recentDay} وثّق المراقبون ملاحظة مقبولة عبر الكاميرا ({$recentNote->camera_number}) في الطابق ({$recentNote->floor_number}): " . ($recentNote->tag ?? $recentNote->description),
                'recommendations' => "1) متابعة معالجة: " . ($recentNote->tag ?? 'الملاحظة المرفقة') . "، والتحقق من إغلاقها خلال 48 ساعة.\n2) تثبيت جولة تفقد إضافية على الموقع خلال المناوبة المسائية.",
                'generation_mode' => Report::MODE_MANUAL,
                'status' => Report::STATUS_PUBLISHED,
                'visible_to_monitors' => true,
                'report_date' => $recentDay,
                'published_at' => $recentPublishedAt,
            ]);
            $recentReport->notes()->attach($recentNote->id, ['order_index' => 0]);
            $recentReport->refresh();
            $recentReport->update(['content' => $compose($recentReport->fresh(['notes', 'author']), collect([$recentNote]))]);
            $recentReport->refresh();
            $recentReport->revisions()->create(['editor_id' => $mainWriter->id, 'content_snapshot' => (string) $recentReport->content]);
            Report::where('id', $recentReport->id)->update(['created_at' => $recentPublishedAt->copy()->subHour(), 'updated_at' => $recentPublishedAt]);
            $recentReport->refresh();
            $renderSheet($recentReport);
            $reports[] = $recentReport->refresh();
            $this->command->line("recent report #{$recentReport->id} date={$recentDay} notes=1 published (editable)");
        }

        $draftReport = Report::create([
            'author_id' => $mainWriter->id,
            'title' => 'التقرير اليومي — ' . $now->toDateString(),
            'summary' => '',
            'recommendations' => '',
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => $now->toDateString(),
            'published_at' => null,
        ]);
        $this->command->line("draft report #{$draftReport->id} date=" . $now->toDateString());

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

        foreach (array_merge($oldNotes, $todayNotes) as $n) {
            if (!$n->sent_at) continue;
            $sender = $usersById->get($n->user_id);
            $senderName = $sender?->name ?? 'مراقب';
            $isRecent = $n->sent_at->greaterThanOrEqualTo($recentCutoff);
            foreach ($writers as $w) {
                if ($w->id === $n->user_id) continue;
                $mkNotif($w, new NoteSentNotification($n, $senderName), $n->sent_at->copy(), $isRecent ? null : $n->sent_at->copy()->addHours(2));
            }
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

        foreach ($reports as $r) {
            if (!$r->isPublished() || !$r->visible_to_monitors || !$r->published_at) continue;
            $isRecent = $r->published_at->greaterThanOrEqualTo($recentCutoff);
            foreach ($monitors as $mon) {
                $mkNotif($mon, new ReportPublishedNotification($r), $r->published_at->copy(), $isRecent ? null : $r->published_at->copy()->addDay());
            }
        }

        $unread = DB::table('notifications')->whereNull('read_at')->count();
        $this->command->info("NOTIFICATIONS made=$made unread=$unread");

        $localNotes = Attachment::get()->filter->isLocal()->count();
        $this->command->info('DONE notes=' . Note::count() . ' reports=' . Report::count() . " notif=" . DatabaseNotification::count() . " local_attachments=$localNotes");
    }

    private function svgScene($cam, $floor, $when, $kind, $caption): string
    {
        $extra = match ($kind) {
            'person' => '<circle cx="430" cy="205" r="16" fill="#d8b24a"/><rect x="414" y="222" width="32" height="58" rx="8" fill="#d8b24a"/><rect x="120" y="120" width="400" height="10" fill="#2a3d2f"/>',
            'door' => '<rect x="270" y="110" width="100" height="170" rx="4" fill="#3d5a43" stroke="#e8e4d2" stroke-width="4"/><circle cx="354" cy="200" r="5" fill="#e8e4d2"/>',
            'archive' => '<rect x="200" y="120" width="240" height="160" rx="6" fill="#1e2e22" stroke="#5f7568" stroke-width="2"/><rect x="215" y="135" width="210" height="20" rx="3" fill="#5f7568"/><rect x="215" y="165" width="210" height="20" rx="3" fill="#5f7568"/><rect x="215" y="195" width="210" height="20" rx="3" fill="#5f7568"/><rect x="215" y="225" width="140" height="20" rx="3" fill="#5f7568"/>',
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
