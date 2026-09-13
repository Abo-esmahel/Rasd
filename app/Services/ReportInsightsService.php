<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * لوحة مؤشرات كتّاب التقارير — بسيطة وذكية.
 *
 * النطاق الزمني: today | 7d | 30d | all (يفلتر report_date للتقارير و observed_at للملاحظات).
 * كل الأرقام تُحسب من السيرفر فقط، والرؤية محروسة بالـ Policy (كتّاب فقط).
 */
class ReportInsightsService
{
    public const RANGES = ['today', '7d', '30d', 'all'];

    public static function normalizeRange(?string $range): string
    {
        return in_array($range, self::RANGES, true) ? $range : '30d';
    }

    /**
     * @return array{from: ?string, to: string}
     */
    public function bounds(string $range): array
    {
        $tz = (string) config('app.timezone', 'Asia/Damascus');
        $today = Carbon::now($tz)->toDateString();

        return match ($range) {
            'today' => ['from' => $today, 'to' => $today],
            '7d' => ['from' => Carbon::now($tz)->subDays(6)->toDateString(), 'to' => $today],
            '30d' => ['from' => Carbon::now($tz)->subDays(29)->toDateString(), 'to' => $today],
            default => ['from' => null, 'to' => $today],
        };
    }

    public function get(string $rangeKey): array
    {
        $range = self::normalizeRange($rangeKey);

        return Cache::remember('report-insights:v2:'.$range, 120, fn () => $this->compute($range));
    }

    private function prevBounds(string $range, ?string $from, string $to): array
    {
        $tz = (string) config('app.timezone', 'Asia/Damascus');
        if ($from === null) {
            return ['from' => null, 'to' => null];
        }
        try {
            $f = Carbon::parse($from, $tz);
            $t = Carbon::parse($to, $tz);
            $len = $f->diffInDays($t) + 1;
            if ($range === 'today') {
                $len = 1;
            }
            $prevTo = $f->copy()->subDay()->toDateString();
            $prevFrom = $f->copy()->subDays($len)->toDateString();

            return ['from' => $prevFrom, 'to' => $prevTo];
        } catch (\Throwable) {
            return ['from' => null, 'to' => null];
        }
    }

    private function deltaPct(int $cur, int $prev): ?float
    {
        if ($prev <= 0) {
            return $cur > 0 ? 100.0 : 0.0;
        }

        return round(($cur - $prev) / $prev * 100, 1);
    }

    private function compute(string $range): array
    {
        ['from' => $from, 'to' => $to] = $this->bounds($range);

        $reportsQ = Report::query();
        $notesQ = Note::query();
        if ($from !== null) {
            $reportsQ->whereDate('report_date', '>=', $from)->whereDate('report_date', '<=', $to);
            $notesQ->whereDate('observed_at', '>=', $from)->whereDate('observed_at', '<=', $to);
        }

        // — التقارير —
        $reportCounts = (clone $reportsQ)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft, SUM(CASE WHEN visible_to_monitors = 1 THEN 1 ELSE 0 END) as visible', [Report::STATUS_PUBLISHED, Report::STATUS_DRAFT])
            ->first();
        $rTotal = (int) ($reportCounts->total ?? 0);
        $rPublished = (int) ($reportCounts->published ?? 0);
        $rDraft = (int) ($reportCounts->draft ?? 0);
        $rVisible = (int) ($reportCounts->visible ?? 0);
        $publishRate = $rTotal > 0 ? round($rPublished / $rTotal * 100) : 0;

        $linkedNotes = DB::table('report_note')->count();
        $reportsWithNotes = DB::table('report_note')->distinct('report_id')->count('report_id');
        $avgNotes = $rTotal > 0
            ? round((clone $reportsQ)->withCount('notes')->get()->avg('notes_count') ?? 0, 1)
            : 0.0;
        // متوسط أخف عند النطاقات الكبيرة: احسب من pivot مباشرة
        if ($rTotal > 200) {
            $avgNotes = $rTotal > 0 ? round($linkedNotes / max(1, Report::count()), 1) : 0.0;
        }

        // — الملاحظات —
        $raw = (clone $notesQ)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        $nTotal = array_sum($raw);
        $nAccepted = (int) ($raw[Note::STATUS_ACCEPTED] ?? 0);
        $nPending = (int) ($raw[Note::STATUS_PENDING] ?? 0);
        $nRejected = (int) ($raw[Note::STATUS_REJECTED] ?? 0);
        $nDraft = (int) ($raw[Note::STATUS_DRAFT] ?? 0);
        $decided = $nAccepted + $nRejected;
        $acceptRate = $decided > 0 ? round($nAccepted / $decided * 100) : 0;

        // مقبولة وغير مربوطة بأي تقرير (بانتظار التوظيف في تقرير)
        $unlinkedQ = Note::where('status', Note::STATUS_ACCEPTED)
            ->whereNotIn('id', fn ($q) => $q->select('note_id')->from('report_note'));
        if ($from !== null) {
            $unlinkedQ->whereDate('observed_at', '>=', $from)->whereDate('observed_at', '<=', $to);
        }
        $unlinkedAccepted = (clone $unlinkedQ)->count();

        // — سلسلة يومية مصغرة للرسم (آخر N يوم حسب النطاق) —
        $days = $this->dayList($range, $from, $to);
        $daily = [];
        if ($days !== []) {
            $repPerDay = Report::whereIn(DB::raw('DATE(report_date)'), $days)
                ->selectRaw('DATE(report_date) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd')->toArray();
            $notePerDay = Note::whereIn(DB::raw('DATE(observed_at)'), $days)
                ->where('status', Note::STATUS_ACCEPTED)
                ->selectRaw('DATE(observed_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd')->toArray();
            foreach ($days as $d) {
                $daily[] = [
                    'day' => $d,
                    'label' => mb_substr($d, 5), // MM-DD
                    'reports' => (int) ($repPerDay[$d] ?? 0),
                    'notes' => (int) ($notePerDay[$d] ?? 0),
                ];
            }
        }
        $maxDaily = 1;
        foreach ($daily as $d) {
            $maxDaily = max($maxDaily, $d['reports'], $d['notes']);
        }

        // — رسالة ذكية واحدة (للتوافق الخلفي) —
        $smartKey = 'ok';
        if ($nPending > 0 && $nPending >= max(5, (int) ceil($nTotal * 0.2))) {
            $smartKey = 'pending_backlog';
        } elseif ($unlinkedAccepted > 0) {
            $smartKey = 'unlinked';
        } elseif ($rDraft > 0 && $rPublished === 0 && $rTotal > 0) {
            $smartKey = 'no_publish';
        } elseif ($rTotal === 0 && $nTotal === 0) {
            $smartKey = 'empty';
        }

        // — مقاييس هندسية مشتقة من البيانات نفسها (بلا حشو) —
        $totalDays = max(1, count($daily));
        $activeDays = 0;
        $peak = null;
        $peakScore = -1;
        foreach ($daily as $d) {
            $s = $d['reports'] + $d['notes'];
            if ($s > 0) {
                $activeDays++;
            }
            if ($s > $peakScore) {
                $peakScore = $s;
                $peak = $d;
            }
        }
        if ($peakScore <= 0) {
            $peak = null;
        }
        $quietDays = $totalDays - $activeDays;
        $coverage = $totalDays > 0 ? (int) round($activeDays / $totalDays * 100) : 0;

        // سلسلة متصلة حتى اليوم
        $streak = 0;
        for ($i = count($daily) - 1; $i >= 0; $i--) {
            if (($daily[$i]['reports'] + $daily[$i]['notes']) > 0) {
                $streak++;
            } else {
                break;
            }
        }

        $pendingShare = $nTotal > 0 ? (int) round($nPending / $nTotal * 100) : 0;
        $visibleRate = $rPublished > 0 ? (int) round($rVisible / $rPublished * 100) : ($rTotal > 0 ? (int) round($rVisible / $rTotal * 100) : 0);
        $avgPerDay = $totalDays > 0 ? round($rTotal / $totalDays, 1) : 0.0;
        $perWeek = round($avgPerDay * 7, 1);
        $backlog = $nPending + $unlinkedAccepted + $rDraft;

        // مقارنة مع الفترة السابقة (نفس الطول مباشرة قبل النطاق)
        $prev = $this->prevBounds($range, $from, $to);
        $deltaReports = null;
        $deltaNotes = null;
        if (!empty($prev['from']) && !empty($prev['to'])) {
            $prQ = Report::query()->whereDate('report_date', '>=', $prev['from'])->whereDate('report_date', '<=', $prev['to']);
            $pnQ = Note::query()->where('status', Note::STATUS_ACCEPTED)->whereDate('observed_at', '>=', $prev['from'])->whereDate('observed_at', '<=', $prev['to']);
            $prevReports = (int) (clone $prQ)->count();
            $prevNotes = (int) (clone $pnQ)->count();
            $deltaReports = $this->deltaPct($rTotal, $prevReports);
            $deltaNotes = $this->deltaPct($nAccepted, $prevNotes);
        }

        // درجة الصحة التشغيلية 0-100 (موزونة وقابلة للتفسير)
        $health = (int) round(
            $publishRate * 0.30
            + $acceptRate * 0.25
            + (100 - min(100, $pendingShare)) * 0.25
            + $coverage * 0.20
        );
        if ($rTotal === 0 && $nTotal === 0) {
            $health = 0;
        }
        $healthKey = $health >= 85 ? 'excellent' : ($health >= 65 ? 'stable' : ($health >= 40 ? 'watch' : 'critical'));

        // إجراءات مرتبة بالأثر (بحد أقصى 4) — كل إجراء مرتبط برقم حقيقي
        $actions = [];
        if ($rTotal === 0 && $nTotal === 0) {
            $actions[] = ['key' => 'empty', 'level' => 'info', 'count' => 0];
        } else {
            if ($nPending > 0 && ($nPending >= 5 || $pendingShare >= 20)) {
                $actions[] = [
                    'key' => 'pending',
                    'level' => ($nPending >= 10 || $pendingShare >= 40) ? 'critical' : 'high',
                    'count' => $nPending,
                ];
            }
            if ($unlinkedAccepted > 0) {
                $actions[] = ['key' => 'unlinked', 'level' => 'high', 'count' => $unlinkedAccepted];
            }
            if ($rDraft > 0) {
                $actions[] = [
                    'key' => $rPublished === 0 ? 'drafts_blocked' : 'drafts',
                    'level' => $rPublished === 0 ? 'critical' : 'medium',
                    'count' => $rDraft,
                ];
            }
            if ($coverage < 50 && ($rTotal + $nTotal) > 0 && $totalDays >= 7) {
                $actions[] = ['key' => 'coverage', 'level' => 'medium', 'count' => $quietDays];
            }
            if ($actions === []) {
                $actions[] = ['key' => 'steady', 'level' => 'ok', 'count' => $streak];
            }
            $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'info' => 3, 'ok' => 4];
            usort($actions, fn ($a, $b) => ($order[$a['level']] ?? 9) <=> ($order[$b['level']] ?? 9));
            $actions = array_slice($actions, 0, 4);
        }

        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'reports' => [
                'total' => $rTotal,
                'published' => $rPublished,
                'draft' => $rDraft,
                'visible' => $rVisible,
                'publish_rate' => $publishRate,
                'visible_rate' => $visibleRate,
                'avg_notes' => $avgNotes,
                'with_notes' => $reportsWithNotes,
                'avg_per_day' => $avgPerDay,
                'per_week' => $perWeek,
                'delta' => $deltaReports,
            ],
            'notes' => [
                'total' => $nTotal,
                'accepted' => $nAccepted,
                'pending' => $nPending,
                'rejected' => $nRejected,
                'draft' => $nDraft,
                'accept_rate' => $acceptRate,
                'pending_share' => $pendingShare,
                'unlinked_accepted' => $unlinkedAccepted,
                'delta' => $deltaNotes,
            ],
            'daily' => $daily,
            'max_daily' => $maxDaily,
            'smart' => $smartKey,
            'health' => $health,
            'health_key' => $healthKey,
            'coverage' => $coverage,
            'active_days' => $activeDays,
            'total_days' => $totalDays,
            'quiet_days' => $quietDays,
            'streak' => $streak,
            'backlog' => $backlog,
            'peak' => $peak,
            'actions' => $actions,
        ];
    }

    /** @return string[] */
    private function dayList(string $range, ?string $from, string $to): array
    {
        if ($range === 'all') {
            // للنطاق الكلي: آخر 14 يوم فقط للرسم حتى لا يثقل
            $tz = (string) config('app.timezone', 'Asia/Damascus');
            $end = Carbon::parse($to, $tz);
            $out = [];
            for ($i = 13; $i >= 0; $i--) {
                $out[] = $end->copy()->subDays($i)->toDateString();
            }

            return $out;
        }
        if ($range === 'today') {
            return [$to];
        }
        $tz = (string) config('app.timezone', 'Asia/Damascus');
        $start = Carbon::parse((string) $from, $tz);
        $end = Carbon::parse($to, $tz);
        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $out[] = $d->toDateString();
            if (count($out) >= 31) {
                break;
            }
        }

        return $out;
    }
}
