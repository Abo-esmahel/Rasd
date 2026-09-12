<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportPublishedNotification;
use App\Services\Ai\ReportContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ReportService
{
    public function __construct(private ?ReportContextService $contexts = null)
    {
    }

    public function reportDay(Note $note): string
    {
        $tz = (string) config('app.timezone', 'UTC');
        $at = $note->observed_at instanceof \DateTimeInterface
            ? Carbon::parse($note->observed_at->format('Y-m-d H:i:s'), $tz)
            : Carbon::parse((string) $note->observed_at, $tz);

        return $at->setTimezone($tz)->toDateString();
    }

    public function reportDayForDate(mixed $observedAt): string
    {
        $tz = (string) config('app.timezone', 'UTC');

        return Carbon::parse((string) $observedAt, $tz)->setTimezone($tz)->toDateString();
    }

    public function createDraft(User $author, array $data): Report
    {
        if (!$author->isReportWriter()) {
            throw new InvalidArgumentException(__('api.report_unauthorized_create'));
        }
        $allowed = array_intersect_key($data, array_flip(['title', 'report_date', 'content', 'visible_to_monitors']));
        if (empty($allowed['report_date'])) {
            throw new InvalidArgumentException(__('api.report_date_required'));
        }
        if (empty(trim((string) ($allowed['title'] ?? '')))) {
            $allowed['title'] = __('report.daily_title_default', ['date' => $allowed['report_date']]);
        }
        if (mb_strlen(trim((string) $allowed['title'])) < 3) {
            throw new InvalidArgumentException(__('api.report_title_too_short'));
        }
        if (Carbon::parse($allowed['report_date'])->toDateString() > now()->toDateString()) {
            throw new InvalidArgumentException(__('api.report_date_future'));
        }
        // السماح بمسودات متعددة لنفس اليوم — التقييد يكون عند النشر فقط (واحد منشور/يوم).
        $allowed['author_id'] = $author->id;
        $allowed['status'] = Report::STATUS_DRAFT;
        $allowed['generation_mode'] = Report::MODE_MANUAL;
        if (array_key_exists('visible_to_monitors', $allowed)) {
            $allowed['visible_to_monitors'] = (bool) $allowed['visible_to_monitors'];
        }

        return DB::transaction(function () use ($author, $allowed) {
            $report = Report::create($allowed);
            // إرفاق تلقائي: كل الملاحظات المقبولة من نفس اليوم التي لم تُستخدم في تقرير آخر
            $day = Carbon::parse($allowed['report_date'])->toDateString();
            $ids = Note::where('status', Note::STATUS_ACCEPTED)
                ->whereNull('general_submission_id')
                ->whereDate('observed_at', $day)
                ->whereNotIn('id', function ($q) { $q->select('note_id')->from('report_note'); })
                ->orderBy('observed_at')->orderBy('id')
                ->pluck('id')->all();
            if ($ids !== []) {
                $this->attachNotes($author, $report, $ids);
            }

            return $report->fresh(['notes', 'author']);
        });
    }

    /**
     * يركّب المحتوى النهائي من القالب الثابت: بيانات + ملخص + جدول الملاحظات + توصيات + توقيع.
     * دالة خالصة (لا تحفظ) — تُستخدم للمعاينة ولإعادة التركيب قبل الحفظ/النشر.
     */
    public function composeContent(Report $report): string
    {
        $report->loadMissing(['author', 'notes']);
        $notes = $report->notes->sortBy(fn ($n) => $n->pivot->order_index ?? 0)->values();
        $author = $report->author?->name ?? '—';
        $dateStr = $report->report_date instanceof \DateTimeInterface
            ? $report->report_date->format('Y-m-d')
            : (string) ($report->report_date ?? '—');

        $lines = [];
        $lines[] = (string) ($report->title ?? 'تقرير');
        $lines[] = '';
        $lines[] = 'بيانات التقرير: التاريخ ' . $dateStr . ' | الرقم #' . $report->id . ' | الكاتب ' . $author;
        $lines[] = '';
        $lines[] = 'أولاً — الملخص التنفيذي';
        $lines[] = trim((string) $report->summary) !== '' ? trim((string) $report->summary) : '—';
        $lines[] = '';
        $lines[] = 'ثانياً — جدول الملاحظات (' . $notes->count() . ')';
        $i = 0;
        foreach ($notes as $n) {
            $i++;
            $at = $n->observed_at ? $n->observed_at->format('H:i') : '—';
            $lines[] = "{$i} | الوقت {$at} | طابق {$n->floor_number} | كاميرا {$n->camera_number} | " . trim(preg_replace('/\s+/', ' ', (string) $n->description));
        }
        $lines[] = '';
        $lines[] = 'ثالثاً — التوصيات';
        $lines[] = trim((string) $report->recommendations) !== '' ? trim((string) $report->recommendations) : '— لا توجد توصيات —';
        $lines[] = '';
        $lines[] = 'التوقيع: ' . $author . ' | رُكّب بتاريخ ' . now()->format('Y-m-d H:i');

        return implode("\n", $lines);
    }

    public function update(User $user, Report $report, array $data): Report
    {
        $this->assertOwner($user, $report);
        $this->assertMutable($report);
        $allowed = array_intersect_key($data, array_flip(['title', 'content', 'summary', 'recommendations', 'visible_to_monitors', 'report_date']));

        if (isset($allowed['title']) && mb_strlen(trim((string) $allowed['title'])) < 3) {
            throw new InvalidArgumentException(__('api.report_title_too_short'));
        }
        if (isset($allowed['report_date'])) {
            $newDate = Carbon::parse($allowed['report_date'])->toDateString();
            if ($newDate > now()->toDateString()) {
                throw new InvalidArgumentException(__('api.report_date_future'));
            }
            // تغيير تاريخ المسودة مسموح حتى لو وُجدت مسودات أخرى بنفس اليوم — المنع عند النشر فقط.
            // نمنع فقط التغيير إلى يوم يوجد فيه تقرير منشور بالفعل (لأن النشر سيُرفض لاحقاً).
            if (Report::whereDate('report_date', $newDate)->where('status', Report::STATUS_PUBLISHED)->where('id', '!=', $report->id)->exists()) {
                throw new InvalidArgumentException(__('api.report_publish_duplicate_day'));
            }
            $notes = $report->notes()->get();
            foreach ($notes as $n) {
                if ($this->reportDay($n) !== $newDate) {
                    throw new InvalidArgumentException(__('api.report_date_change_rejected'));
                }
            }
            $allowed['report_date'] = $newDate;
        }
        if (array_key_exists('visible_to_monitors', $allowed)) {
            $allowed['visible_to_monitors'] = (bool) $allowed['visible_to_monitors'];
        }


        if ((array_key_exists('content', $allowed) || array_key_exists('summary', $allowed) || array_key_exists('recommendations', $allowed)) && $report->ai_draft_content) {
            $allowed['generation_mode'] = Report::MODE_HYBRID;
        }

        $wasPublished = $report->isPublished();
        $report->update($allowed);
        $fresh = $report->fresh();

        // أي تعبئة للحقول المنظمة تعيد تركيب المحتوى من القالب الثابت فوراً.
        if (array_key_exists('summary', $allowed) || array_key_exists('recommendations', $allowed)) {
            $fresh->update(['content' => $this->composeContent($fresh)]);
            $fresh = $fresh->fresh();
        }

        if ($wasPublished) {
            $fresh->revisions()->create([
                'editor_id' => $user->id,
                'content_snapshot' => (string) $fresh->content,
            ]);
        }

        return $fresh;
    }

    public function delete(User $user, Report $report): void
    {
        $this->assertOwner($user, $report);
        $this->assertMutable($report);
        DB::transaction(function () use ($report) {
            $report->notes()->detach();
            $report->revisions()->delete();
            $report->delete();
        });
    }


    public function attachNotes(User $user, Report $report, array $noteIds): Report
    {
        $this->assertOwner($user, $report);
        $this->assertDraft($report);
        $noteIds = array_values(array_unique(array_map('intval', $noteIds)));
        if (empty($noteIds)) {
            throw new InvalidArgumentException(__('api.report_no_notes'));
        }

        return DB::transaction(function () use ($report, $noteIds) {
            $locked = $report->fresh();
            $day = $locked->report_date->toDateString();
            $maxOrder = (int) ($locked->notes()->max('report_note.order_index') ?? -1);

            $notes = Note::whereIn('id', $noteIds)->get()->keyBy('id');
            foreach ($noteIds as $nid) {
                $n = $notes->get($nid);
                if (!$n) {
                    throw new InvalidArgumentException(__('api.report_note_not_found', ['id' => $nid]));
                }
                if ($n->status !== Note::STATUS_ACCEPTED) {
                    throw new InvalidArgumentException(__('api.report_note_not_accepted', ['id' => $nid]));
                }
                if ($this->reportDay($n) !== $day) {
                    throw new InvalidArgumentException(__('api.report_note_wrong_day', ['id' => $nid]));
                }
                if (DB::table('report_note')->where('note_id', $nid)->where('report_id', '!=', $locked->id)->exists()) {
                    throw new InvalidArgumentException(__('api.report_note_already_in_report', ['id' => $nid]));
                }
            }

            foreach ($locked->notes()->get() as $existing) {
                if ($this->reportDay($existing) !== $day) {
                    throw new InvalidArgumentException(__('api.report_has_wrong_day_note'));
                }
            }

            foreach ($noteIds as $nid) {
                $maxOrder++;
                // فحص مسبق بدل التقاط رسالة UNIQUE الهشة (تختلف بين SQLite/MySQL/PgSQL).
                if ($locked->notes()->where('notes.id', $nid)->exists()) {
                    $maxOrder--;
                    continue;
                }
                $locked->notes()->attach($nid, ['order_index' => $maxOrder]);
            }

            $fresh = $locked->fresh(['notes', 'author']);
            // إعادة التركيب: أي تغيير في الملاحظات يعيد بناء المحتوى فوراً
            // (فقط عندما توجد حقول منظمة، حتى لا يُفتح النشر قبل تعبئة الملخص).
            $this->recomposeIfStructured($fresh);
            // pivot لا يُطلق Report::saved دائماً — جدولة تدفئة observations
            // صراحة هنا (خلفية فقط، بلا Gemini متزامن، بلا مساس بالمصدر).
            $this->warmReportObservations($fresh->fresh(['notes', 'author']));

            return $fresh->fresh(['notes', 'author']);
        });
    }

    public function detachNote(User $user, Report $report, int $noteId): Report
    {
        $this->assertOwner($user, $report);
        $this->assertDraft($report);

        return DB::transaction(function () use ($report, $noteId) {
            $report->notes()->detach($noteId);
            $fresh = $report->fresh(['notes', 'author']);
            $this->recomposeIfStructured($fresh);
            // إزالة الملاحظة تغيّر observations المشتقة — دفّئ projections
            // خلفياً (بلا Gemini في القراءة/Language Switch).
            $this->warmReportObservations($fresh->fresh(['notes', 'author']));

            return $fresh->fresh(['notes', 'author']);
        });
    }


    public function reorderNotes(User $user, Report $report, array $orderedIds): Report
    {
        $this->assertOwner($user, $report);
        $this->assertDraft($report);
        $orderedIds = array_values(array_map('intval', $orderedIds));

        return DB::transaction(function () use ($report, $orderedIds) {
            $current = $report->notes()->pluck('notes.id')->all();
            sort($current);
            $sorted = $orderedIds;
            sort($sorted);
            if ($current !== $sorted) {
                throw new InvalidArgumentException(__('api.report_invalid_order'));
            }
            foreach ($orderedIds as $i => $nid) {
                $report->notes()->updateExistingPivot($nid, ['order_index' => $i]);
            }

            $fresh = $report->fresh(['notes', 'author']);
            // الترتيب يغيّر جدول الملاحظات في القالب — أعد التركيب فوراً.
            $this->recomposeIfStructured($fresh);
            // الترتيب يغيّر فهارس observation:{i} — دفّئ projections خلفياً
            // (المصدر لا يُمس، والقراءة تبقى محفوظة فقط).
            $this->warmReportObservations($fresh->fresh(['notes', 'author']));

            return $fresh->fresh(['notes', 'author']);
        });
    }

    public function publish(User $user, Report $report): Report
    {
        $this->assertOwner($user, $report);
        if (!$report->isDraft()) {
            throw new InvalidArgumentException(__('api.report_publish_draft_only'));
        }

        return DB::transaction(function () use ($report, $user) {
            $locked = Report::with('notes')->lockForUpdate()->findOrFail($report->id);
            if ($locked->notes()->count() < 1) {
                throw new InvalidArgumentException(__('api.report_publish_needs_note'));
            }
            $day = $locked->report_date->toDateString();
            // تقييد النشر: تقرير منشور واحد فقط لكل يوم — المسودات متعددة مسموحة.
            if (Report::whereDate('report_date', $day)->where('status', Report::STATUS_PUBLISHED)->where('id', '!=', $locked->id)->exists()) {
                throw new InvalidArgumentException(__('api.report_publish_duplicate_day'));
            }
            foreach ($locked->notes()->get() as $n) {
                if ($n->status !== Note::STATUS_ACCEPTED || $this->reportDay($n) !== $day) {
                    throw new InvalidArgumentException(__('api.report_publish_invalid_note'));
                }
            }
            // القالب الثابت يُركّب من جديد لحظة النشر — لا يُنشر جدول قديم بعد فك ملاحظة.
            if (trim((string) $locked->summary) !== '' || trim((string) $locked->recommendations) !== '') {
                $locked->update(['content' => $this->composeContent($locked)]);
                $locked = $locked->fresh(['notes', 'author']);
            }
            // تقارير 1-7: النشر يتطلب معاينة معتمدة حديثة (اعتماد → render → preview HTML).
            // يمنع نشر نسخة قديمة بعد تعديل الملاحظات/الترتيب/التوصيات.
            $notesCount = $locked->notes()->count();
            if ($notesCount >= 1 && $notesCount <= (int) config('report_sheets.max_notes', 7)) {
                try {
                    $htmlSvc = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
                    $state = $htmlSvc->htmlState($locked);
                    // API/اختبارات قد تنشر دون المرور بزر الاعتماد — ولّد معاينة النظام تلقائياً بدل الرفض.
                    if (($state['state'] ?? 'none') === 'none') {
                        try {
                            $htmlSvc->renderSystem($user, $locked);
                            $locked = $locked->fresh(['notes', 'author']);
                            $state = $htmlSvc->htmlState($locked);
                        } catch (\Throwable) {
                        }
                    }
                } catch (\Throwable) {
                    $state = ['state' => 'none', 'render' => null];
                }
                if (($state['state'] ?? 'none') === 'none') {
                    throw new InvalidArgumentException(__('api.report_approve_first'));
                }
                if (($state['state'] ?? 'none') === 'stale') {
                    throw new InvalidArgumentException(__('api.report_preview_stale'));
                }
                // ضمان حد أدنى للمحتوى الاحتياطي (للطباعة العامة والإشعارات) ولو بلا ملخص.
                if (trim((string) $locked->content) === '') {
                    $locked->update(['content' => $this->composeContent($locked)]);
                    $locked = $locked->fresh(['notes', 'author']);
                }
            } elseif (trim((string) $locked->content) === '') {
                // التقارير خارج 1–7 بلا محرر اعتماد: ركّب المحتوى تلقائياً بدل الرفض.
                $locked->update(['content' => $this->composeContent($locked)]);
                $locked = $locked->fresh(['notes', 'author']);
            }

            $locked->update(['status' => Report::STATUS_PUBLISHED, 'published_at' => now()]);
            $fresh = $locked->fresh(['notes', 'author']);
            $fresh->revisions()->create(['editor_id' => $user->id, 'content_snapshot' => (string) $fresh->content]);

            $publishedId = $fresh->id;
            // خارج المعاملة: إشعارات + سياق AI في الخلفية حتى لا يُحجز قفل الصف.
            try {
                if ($fresh->visible_to_monitors) {
                    \App\Jobs\FanoutReportPublished::dispatch($publishedId)->afterResponse();
                }
                \App\Jobs\RebuildAiContext::dispatch()->afterResponse();
            } catch (\Throwable $e) {
                Log::warning('[REPORT] post-publish hook failed: '.$e->getMessage());
            }

            return $fresh;
        });
    }

    public function unpublish(User $user, Report $report): Report
    {
        $this->assertOwner($user, $report);
        if (!$report->isPublished()) {
            throw new InvalidArgumentException(__('api.report_unpublish_published_only'));
        }
        $this->assertMutable($report);

        $result = DB::transaction(function () use ($report) {
            $report->update(['status' => Report::STATUS_DRAFT, 'published_at' => null]);

            return $report->fresh(['notes', 'author']);
        });

        try {
            \App\Jobs\RebuildAiContext::dispatch()->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('[REPORT] post-unpublish hook failed: '.$e->getMessage());
        }

        return $result;
    }

    public function getVisibleQuery(User $user)
    {
        // withCount بدل تحميل كل الملاحظات — القائمة كانت تجلب N×M صف فقط لعرض العدد.
        $q = Report::with(['author:id,name'])->withCount('notes');
        if ($user->isReportWriter()) {
            return $q;
        }

        return $q->where('status', Report::STATUS_PUBLISHED)->where('visible_to_monitors', true);
    }

    private function assertOwner(User $user, Report $report): void
    {
        if (!$user->isReportWriter() || (int) $report->author_id !== (int) $user->id) {
            throw new InvalidArgumentException(__('api.report_unauthorized_manage'));
        }
    }

    private function assertDraft(Report $report): void
    {
        if (!$report->fresh()->isDraft()) {
            throw new InvalidArgumentException(__('api.report_edit_draft_only'));
        }
    }

    /**
     * قفل الـ12 ساعة: المنشور بعد انتهاء المهلة لا يُعدَّل ولا يُسحب ولا يُحذف.
     */
    private function assertMutable(Report $report): void
    {
        if ($report->fresh()->isLocked()) {
            throw new InvalidArgumentException(__('api.report_edit_locked'));
        }
    }

    /**
     * يعيد تركيب المحتوى من القالب الثابت بعد تغيّر الملاحظات/ترتيبها،
     * فقط عندما توجد حقول منظمة (حتى لا يُعتبر التقرير جاهزاً قبل تعبئة الملخص).
     */
    private function recomposeIfStructured(Report $report): void
    {
        $fresh = $report->fresh(['notes', 'author']);
        if (trim((string) $fresh->summary) !== '' || trim((string) $fresh->recommendations) !== '') {
            $fresh->update(['content' => $this->composeContent($fresh)]);
        }
    }

    /**
     * تدفئة projections الـ observations المشتقة بعد تغيّر pivot الملاحظات.
     *
     * STRICT — خلفية فقط (CREATE/UPDATE):
     *   Source → dispatchAfterResponse(Job) → Gemini مرة واحدة → Stored.
     * - لا Gemini متزامن هنا، لا مساس بالمصدر، لا فشل يُكسر الحفظ.
     * - dedup عبر resolveStoredMany (قراءة محفوظة فقط) — الجاهز لا يُعاد.
     * - القراءة/Language Switch لا تستدعي هذه الدالة إطلاقاً.
     */
    private function warmReportObservations(Report $report): void
    {
        try {
            $builder = app(\App\Services\ReportPreview\ReportDataBuilder::class);
            $system = $builder->systemData($report);
            $observations = array_values((array) ($system['observations'] ?? []));
            $recommendations = trim((string) ($system['recommendations'] ?? ''));
            if ($observations === [] && $recommendations === '') {
                return;
            }
            $fields = [];
            foreach (array_slice($observations, 0, 25) as $i => $obs) {
                $text = trim((string) $obs);
                if ($text !== '' && mb_strlen($text) <= 5000) {
                    $fields["observation:{$i}"] = $text;
                }
            }
            if ($recommendations !== '' && mb_strlen($recommendations) <= 5000) {
                $fields['recommendations'] = $recommendations;
            }
            if ($fields === []) {
                return;
            }
            // الهدف = عكس لغة المصدر (نفس منطق الـ Observer).
            $uiLocale = \App\Services\Localization\SourceLanguage::normalizeLocale(app()->getLocale());
            $target = null;
            foreach ($fields as $text) {
                $lang = \App\Services\Localization\SourceLanguage::detect($text);
                if ($lang === \App\Services\Localization\SourceLanguage::ARABIC) {
                    $target = 'en';
                    break;
                }
                if ($lang === \App\Services\Localization\SourceLanguage::ENGLISH) {
                    $target = $target ?? 'ar';
                }
            }
            if ($target === null) {
                $target = $uiLocale === 'ar' ? 'en' : 'ar';
            }
            try {
                $service = app(\App\Services\Localization\TranslationService::class);
                $refs = [];
                foreach ($fields as $field => $text) {
                    $refs[] = ['type' => 'report', 'id' => $report->id, 'field' => $field, 'text' => $text];
                }
                $ready = $service->resolveStoredMany($refs, $target);
                foreach (array_keys($fields) as $field) {
                    $k = \App\Services\Localization\TranslationService::itemKey('report', $report->id, (string) $field);
                    if (isset($ready[$k])) {
                        unset($fields[$field]);
                    }
                }
                if ($fields === []) {
                    return;
                }
            } catch (\Throwable) {
            }
            \App\Jobs\WarmTranslationProjection::dispatchAfterResponse('report', $report->id, $fields, $target);
        } catch (\Throwable $e) {
            Log::warning('[L10N] report observations warm skipped', ['report_id' => $report->id ?? null]);
        }
    }

    public static function aiLockKey(int $reportId): string
    {
        return "report-ai-{$reportId}";
    }

    public function acquireAiLock(int $reportId): bool
    {
        return (bool) Cache::lock(self::aiLockKey($reportId), 120)->get();
    }

    public function releaseAiLock(int $reportId): void
    {
        try {
            Cache::lock(self::aiLockKey($reportId))->forceRelease();
        } catch (\Throwable $e) {
        }
    }
}
