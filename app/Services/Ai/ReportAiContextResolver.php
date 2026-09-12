<?php

namespace App\Services\Ai;

use App\Models\Note;
use App\Models\Report;


class ReportAiContextResolver
{
    public function __construct(private ReportContextService $contexts)
    {
    }

    public function resolveFor(Report $report): string
    {
        $date = $report->report_date->toDateString();
        $cacheKey = 'ai:ctx:' . $date . ':' . $report->id;
        try {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') return $cached;
        } catch (\Throwable) {}

        $this->contexts->ensureBuilt();
        $full = $this->contexts->read();

        // Fast slice: only system rules + lines containing target date (no full scan of similar reports bloat)
        $out = [];
        $needles = ['CONTEXT_VERSION','GENERATED_AT','== SYSTEM','DATABASE =','== REPORT RULES','REPORT =','== WRITING','عربية رسمية','== TERMINOLOGY','مراقب'];
        foreach (explode("\n", $full) as $line) {
            foreach ($needles as $n) { if (str_starts_with($line, $n)) { $out[] = $line; continue 2; } }
            if (str_contains($line, $date)) { $out[] = $line; continue; }
            if (str_starts_with($line, '  - att#') && !empty($out) && str_contains(end($out), $date)) $out[] = $line;
        }

        // Similar reports: lightweight, cached per day, style only — not factual source
        try {
            $similar = Report::where('status', Report::STATUS_PUBLISHED)
                ->where('id', '!=', $report->id)
                ->whereDate('report_date', '<', $date)
                ->orderByDesc('report_date')
                ->limit(2)
                ->get(['id', 'title', 'report_date']);
            if ($similar->isNotEmpty()) {
                $out[] = '== SIMILAR REPORTS (style only, not facts) ==';
                foreach ($similar as $s) $out[] = "#{$s->id} | {$s->report_date->toDateString()} | " . mb_substr((string) $s->title, 0, 60);
            }
        } catch (\Throwable) {}

        $slice = mb_substr(implode("\n", $out), 0, 3000);
        try { \Illuminate\Support\Facades\Cache::put($cacheKey, $slice, now()->addMinutes(10)); } catch (\Throwable) {}
        return $slice;
    }
}
