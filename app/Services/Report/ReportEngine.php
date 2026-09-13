<?php

namespace App\Services\Report;

/**
 * ONE REPORT ENGINE — single dynamic layout system (no 7 templates).
 *
 * Pipeline (unchanged architecture):
 *   FinalReportData → ReportEngine → Dynamic HTML/CSS → Preview → Print/PDF.
 * Gemini owns wording/organization only — never layout, signature placement,
 * templates, or images.
 *
 * Input : FinalReportData (observations[], recommendations, report_number,
 *         date) + Report model (author).
 * Output: ONE official document array consumed by ONE Blade document
 *         (resources/views/reports/engine/document.blade.php).
 *
 * Official vs internal separation:
 * - OFFICIAL (rendered once): header identity, meta (number + date ONCE),
 *   observations (each once, as <ol><li> with calm ordinal titles),
 *   recommendations (own data only, as a real <ol> when numbered),
 *   corner signature block (real persons only, no headings/panels).
 * - INTERNAL (never rendered): density, count_for_layout, template key,
 *   generation_id, generated_at. Kept in $doc['internal'] for backend/audit
 *   only. Density is additionally exposed as the invisible data-density
 *   attribute (CSS hook) — never as visible text.
 *
 * Root-cause note on the "1,2,3 × N" duplication: the engine renders each
 * stored observation exactly once (single foreach, no partial reuse). The
 * repetition lived inside the stored FinalReportData itself (one observation
 * string holding the recommendations block concatenated 3–5× verbatim).
 * Defense in depth: input is normalized before save (see
 * ReportHtmlRenderingService::render) AND collapsed at build time
 * (base×N → base), with exact-duplicate observations deduped.
 */
final class ReportEngine
{
    public const DENSITIES = ['comfortable', 'balanced', 'compact', 'dense'];

    /** @return array{official document + internal} */
    public function build(\App\Models\Report $report, array $payload, array $context = []): array
    {
        $report->loadMissing(['author', 'notes.attachments', 'notes.owner:id,name']);

        $locale = strtolower(trim((string) ($context['locale'] ?? app()->getLocale() ?? 'ar')));
        if (!in_array($locale, ['ar', 'en'], true)) {
            $locale = 'ar';
        }
        $isEn = $locale === 'en';

        $observations = self::normalizeObservations($payload['observations'] ?? []);
        $recommendationsText = self::normalizeText($payload['recommendations'] ?? '');
        $count = count($observations);
        $density = $this->density($observations, $recommendationsText);

        $reportNumber = trim((string) ($payload['report_number'] ?? $report->id));
        $date = trim((string) ($payload['date'] ?? ($report->report_date ? $report->report_date->toDateString() : '')));

        // Day name + approval date are derived facts (report_date / published_at) — never invented.
        $dayName = '';
        try {
            if ($report->report_date) {
                $dayName = (string) \Carbon\Carbon::parse($report->report_date->toDateString())->locale($isEn ? 'en' : 'ar')->dayName;
            }
        } catch (\Throwable) {
            $dayName = '';
        }
        $approvalDate = '';
        try {
            if ($report->published_at) {
                $approvalDate = $report->published_at->toDateString();
            } elseif ($report->report_date) {
                $approvalDate = $report->report_date->toDateString();
            }
        } catch (\Throwable) {
            $approvalDate = $date;
        }

        // Per-observation structured facts (observer / camera / floor / times / attachments).
        // Truthful mapping only: index i ↔ i-th note in pivot order WHEN counts match.
        // Mismatch (AI merge/dedupe) → meta omitted gracefully, text stays untouched.
        $obsMeta = $this->observationDetails($report, $count, $locale);
        $observers = $this->distinctObservers($report, $count);

        $obsItems = [];
        foreach (array_values($observations) as $i => $text) {
            $obsItems[] = [
                'title' => $isEn ? 'Observation '.($i + 1) : 'ملاحظة رقم '.($i + 1),
                'paragraphs' => self::splitParagraphs($text),
                'meta' => $obsMeta[$i] ?? null,
            ];
        }

        return [
            'dir' => $isEn ? 'ltr' : 'rtl',
            'lang' => $isEn ? 'en' : 'ar',
            'density' => $density,
            'logo' => '/report/assets/logo-report.png',
            'header' => [
                'ministry' => $isEn ? 'Syrian Arab Republic' : 'الجمهورية العربية السورية',
                'ministry_sub' => $isEn ? 'Ministry of Information' : 'وزارة الإعلام',
                // Paper form title — matches the attached official model.
                'doc_title' => $isEn ? 'Daily Camera Surveillance Report' : 'نموذج تقرير مراقبة الكاميرات اليومي',
            ],
            'day_name' => $dayName,
            'day_label' => $isEn ? 'Day' : 'اليوم',
            // Distinct monitor names from the underlying notes (pivot order).
            'observers' => $observers,
            'approval_date' => $approvalDate,
            'notes_count' => $count,
            'report_number' => $reportNumber,
            'report_date' => $date,
            // Single official identity block — report number + date appear ONCE.
            // Kept for backward compatibility; template prefers explicit keys above.
            'meta' => array_values(array_filter([
                $reportNumber !== '' ? ['label' => $isEn ? 'Report Number' : 'رقم التقرير', 'value' => $reportNumber, 'num' => true] : null,
                $date !== '' ? ['label' => $isEn ? 'Date' : 'التاريخ', 'value' => $date, 'num' => true] : null,
            ])),
            'observations' => $obsItems,
            // Independent data only — never copied from observations.
            'recommendations' => self::splitRecommendations($recommendationsText),
            'recommendations_title' => $isEn ? 'Immediate Actions Taken' : 'الإجراءات التي تم اتخاذها فوراً بناءً على الملاحظة',
            'signature_caption' => $isEn ? 'Signature' : 'التوقيع',
            // Real persons only (no invented facts). Rendered as a small
            // corner signature block WITHOUT any approval heading/panel.
            'responsibles' => $this->responsibles($report, $payload, $locale),
            // No closing identity text: the header owns the official identity.
            // The footer is a visual closing rule only.
            'footer' => [],
            // Backend / audit trail only — Blade MUST NOT render this.
            'internal' => [
                'count_for_layout' => $count,
                'layout_density' => $density,
                'template_id' => trim((string) ($payload['template'] ?? '')),
                'generation_id' => trim((string) ($context['generation_id'] ?? '')),
                'generated_at' => trim((string) ($context['generated_at'] ?? '')),
            ],
        ];
    }

    /**
     * Backend density decision: estimated printed lines first (toughest
     * cases: many observations or very long texts → tightest readable),
     * then count + total length. Frontend/Gemini never decide layout.
     */
    public function density(array $observations, string $recommendations): string
    {
        $n = count($observations);
        $total = mb_strlen($recommendations);
        // ~60 chars per justified Arabic line at body size + title rows.
        $estLines = (int) ceil(mb_strlen($recommendations) / 60);
        foreach ($observations as $o) {
            $len = mb_strlen((string) $o);
            $total += $len;
            $estLines += (int) ceil($len / 60) + 1;
        }
        $estLines += 8; // header + meta + section titles

        if ($n > 7 || $estLines > 40) {
            return 'dense';
        }
        if ($n <= 2 && $total < 1200) {
            return 'comfortable';
        }
        if ($n <= 4 && $total < 3500) {
            return 'balanced';
        }
        if ($n <= 6 && $total < 6000) {
            return 'compact';
        }

        return 'dense';
    }

    /**
     * Normalize a raw observations input into clean official texts:
     * trim → drop empties → strip leading camera/floor/time meta
     * (already shown in the observation meta row) → collapse internal
     * exact repetitions (polluted FinalReportData like base×3 / base×5)
     * → dedupe exact duplicates preserving order. Each observation is returned ONCE.
     *
     * @return string[]
     */
    public static function normalizeObservations(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (array_values($raw) as $o) {
            $text = self::normalizeText(is_array($o) ? ($o['text'] ?? '') : $o);
            $text = self::stripObservationMeta($text);
            if ($text === '') {
                continue;
            }
            $out[] = $text;
        }
        // Dedupe exact duplicates (loop/merge bugs) — keep first occurrence.
        // Never dedupe placeholders: identical "Translation is being prepared…" for different observations must stay separate.
        $seen = [];
        $deduped = [];
        foreach ($out as $t) {
            if ($t === 'Translation is being prepared…' || $t === 'جارٍ تجهيز الترجمة…' || str_contains($t, 'Translation is being prepared') || str_contains($t, 'جارٍ تجهيز الترجمة')) {
                $deduped[] = $t;
                continue;
            }
            $key = hash('sha256', $t);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $t;
        }

        return array_values($deduped);
    }

    /** Trim + collapse an exact repeated concatenation (base×N → base). */
    public static function normalizeText(mixed $raw): string
    {
        $original = trim((string) $raw);
        if ($original === '') {
            return '';
        }
        if (str_contains($original, "\n")) {
            $lines = array_map(
                fn ($l) => trim(preg_replace('/[ \t]+/u', ' ', $l) ?? ''),
                preg_split('/\R/u', $original)
            );
            $lines = array_values(array_filter($lines, fn ($l) => $l !== ''));
            $text = implode("\n", $lines);
            if ($text === '') {
                return '';
            }
        } else {
            $text = trim(preg_replace('/\s+/u', ' ', $original) ?? '');
            if ($text === '') {
                return '';
            }
        }

        return self::collapseRepeatedText($text);
    }

    /**
     * Split a normalized text into display paragraphs (one per line).
     *
     * @return string[]
     */
    public static function splitParagraphs(string $text): array
    {
        $parts = preg_split('/\R/u', $text);
        $out = [];
        foreach ((array) $parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out !== [] ? array_values($out) : [$text];
    }

    /**
     * Split recommendations into a real ordered list when the text carries
     * numbering (e.g. "1. … 2. … 3. …" or one item per line).
     *
     * @return array{items: string[], paragraphs: string[]}
     */
    public static function splitRecommendations(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['items' => [], 'paragraphs' => []];
        }
        $items = self::splitNumberedSequence($text);
        // Fallback: one recommendation per non-empty line (explicit line
        // breaks typed by the author), minus bullet prefixes.
        if (count($items) < 2 && str_contains($text, "\n")) {
            $lines = [];
            foreach (preg_split('/\R/u', $text) as $line) {
                $line = trim(preg_replace('/^(\d{1,2}\s*[.)\-–]\s*|[\-•*–—]+\s*)/u', '', trim((string) $line)) ?? '');
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            if (count($lines) >= 2) {
                $items = $lines;
            }
        }
        if (count($items) >= 2) {
            return ['items' => array_values($items), 'paragraphs' => []];
        }

        return ['items' => [], 'paragraphs' => self::splitParagraphs($text)];
    }

    private static function splitNumberedSequence(string $text): array
    {
        if (!preg_match_all('/(?:^|\s)(\d{1,2})\s*[.)]\s+/u', $text, $m, \PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $seq = [];
        $expect = 1;
        foreach ($m[0] as $k => $full) {
            $num = (int) $m[1][$k][0];
            if ($num === $expect) {
                $seq[] = [$full[1], $full[1] + strlen($full[0])];
                $expect++;
            } elseif ($num === 1) {
                $seq = [[$full[1], $full[1] + strlen($full[0])]];
                $expect = 2;
            }
        }
        if (count($seq) < 2) {
            return [];
        }
        $items = [];
        $byteLen = strlen($text);
        foreach ($seq as $i => $s) {
            $end = ($i + 1 < count($seq)) ? $seq[$i + 1][0] : $byteLen;
            $item = trim(substr($text, $s[1], $end - $s[1]));
            $item = trim(preg_replace('/^[\-•*–—]+\s*/u', '', $item) ?? '');
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return count($items) >= 2 ? array_values($items) : [];
    }

    public static function stripObservationMeta(string $text): string
    {
        $t = trim($text);
        if ($t === '') {
            return '';
        }
        // Repeated strip (in case of double prefix)
        $prev = null;
        while ($prev !== $t) {
            $prev = $t;
            $t = preg_replace('/^\s*كاميرا\s*\d+\s*[•·\|\/\-–—]\s*طابق\s*\d+\s*[•·\|\/\-–—]\s*\d{1,2}:\d{2}(?::\d{2})?\s*(?:[صم]|AM|PM)?\s*[—–\-•·]+\s*/u', '', $t) ?? $t;
            // English: Camera 14 • Floor 5 • 06:15 —
            $t = preg_replace('/^\s*Camera\s*\d+\s*[•·\|\/\-–—]\s*Floor\s*\d+\s*[•·\|\/\-–—]\s*\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?\s*[—–\-•·]+\s*/iu', '', $t) ?? $t;
            $t = preg_replace('/^\s*كاميرا\s*\d+\s+طابق\s*\d+\s+\d{1,2}:\d{2}(?::\d{2})?\s*[-–—]+\s*/u', '', $t) ?? $t;
            $t = preg_replace('/^\s*Camera\s*\d+\s+Floor\s*\d+\s+\d{1,2}:\d{2}(?::\d{2})?\s*[-–—]+\s*/iu', '', $t) ?? $t;
            // Only strip if dash follows time and there is remaining text
            if (preg_match('/^\s*\d{1,2}:\d{2}(?::\d{2})?\s*[-–—]+\s+/u', $t) && mb_strlen($t) > 20) {
                $t = preg_replace('/^\s*\d{1,2}:\d{2}(?::\d{2})?\s*[-–—]+\s+/u', '', $t) ?? $t;
            }
            $t = trim($t);
        }

        return $t;
    }

    /**
     * If $text is exactly a smaller block repeated N≥2 times, return the block.
     * Fixes polluted payloads where one observation stored recommendations×3/×5.
     */
    private static function collapseRepeatedText(string $text): string
    {
        $len = mb_strlen($text);
        if ($len < 100) {
            return $text;
        }
        // Try repetition counts from high to low; smallest valid block wins.
        for ($n = 10; $n >= 2; $n--) {
            if ($len % $n !== 0) {
                continue;
            }
            $blockLen = intdiv($len, $n);
            if ($blockLen < 50) {
                continue;
            }
            $block = mb_substr($text, 0, $blockLen);
            $rebuilt = str_repeat($block, $n);
            if ($rebuilt === $text) {
                // Recurse: block itself might still be repeated.
                return self::collapseRepeatedText($block);
            }
        }

        return $text;
    }

    /** Real persons only (no invented facts). */
    private function responsibles(\App\Models\Report $report, array $payload, string $locale = 'ar'): array
    {
        $isEn = $locale === 'en';
        $defaultRole = $isEn ? 'Prepared by' : 'معدّ التقرير';
        $fallbackRole = $isEn ? 'Responsible' : 'مسؤول';
        // Future-proof: accept explicit responsibles when FinalReportData provides them.
        if (isset($payload['responsibles']) && is_array($payload['responsibles'])) {
            $out = [];
            foreach (array_values($payload['responsibles']) as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $name = trim((string) ($r['name'] ?? ''));
                if ($name === '' || $name === '—') {
                    continue;
                }
                $role = trim((string) ($r['role'] ?? ''));
                if ($role === '') {
                    $role = $fallbackRole;
                } elseif ($isEn && $role === 'معدّ التقرير') {
                    $role = $defaultRole;
                } elseif ($isEn && $role === 'مسؤول') {
                    $role = 'Responsible';
                }
                $out[] = ['role' => $role, 'name' => $name];
            }
            if ($out !== []) {
                return array_slice(array_values($out), 0, 4);
            }
        }

        $author = trim((string) ($report->author->name ?? ''));
        if ($author === '' || $author === '—') {
            return [];
        }

        return [
            ['role' => $defaultRole, 'name' => $author],
        ];
    }

    /**
     * Structured per-note facts for the paper form (observer/camera/floor/times/attachments).
     * Returns [] when counts mismatch (graceful: template renders text only).
     *
     * @return array<int, array{observer: string, camera: string, floor: string, start: string, end: string, duration: string, has_attachments: bool, has_image: bool, has_video: bool, attachments_count: int}|null>
     */
    private function observationDetails(\App\Models\Report $report, int $count, string $locale = 'ar'): array
    {
        if ($count <= 0) {
            return [];
        }
        $isEn = $locale === 'en';
        try {
            $notes = $report->notes->sortBy(fn ($n) => $n->pivot->order_index ?? 0)->values();
        } catch (\Throwable) {
            return [];
        }
        if ($notes->count() !== $count) {
            return [];
        }
        $out = [];
        foreach ($notes->values() as $n) {
            try {
                $start = $n->observed_at ? $n->observed_at->format('H:i') : '';
                $end = $n->observed_end_at ? $n->observed_end_at->format('H:i') : '';
                $duration = '';
                if ($n->observed_at && $n->observed_end_at) {
                    try {
                        $mins = abs($n->observed_at->diffInMinutes($n->observed_end_at));
                        if ($isEn) {
                            if ($mins < 60) {
                                $duration = $mins.' min';
                            } else {
                                $h = intdiv((int) $mins, 60);
                                $m = ((int) $mins) % 60;
                                $duration = $m > 0 ? $h.'h '.$m.'min' : $h.'h';
                            }
                        } else {
                            if ($mins < 60) {
                                $duration = $mins.' د';
                            } else {
                                $h = intdiv((int) $mins, 60);
                                $m = ((int) $mins) % 60;
                                $duration = $m > 0 ? $h.' س '.$m.' د' : $h.' س';
                            }
                        }
                    } catch (\Throwable) {
                        $duration = '';
                    }
                }
                $atts = $n->relationLoaded('attachments') ? $n->attachments : collect();
                $hasImage = false;
                $hasVideo = false;
                try {
                    foreach ($atts as $a) {
                        $mime = (string) ($a->mime_type ?? '');
                        if (str_starts_with($mime, 'image/')) {
                            $hasImage = true;
                        } elseif (str_starts_with($mime, 'video/')) {
                            $hasVideo = true;
                        }
                    }
                } catch (\Throwable) {
                }
                $attsCount = 0;
                try {
                    $attsCount = count($atts);
                } catch (\Throwable) {
                }
                try {
                    $observer = trim((string) ($n->owner->name ?? ''));
                } catch (\Throwable) {
                    $observer = '';
                }
                if ($observer === '—') {
                    $observer = '';
                }
                $out[] = [
                    'observer' => $observer,
                    'camera' => trim((string) ($n->camera_number ?? '')),
                    'floor' => trim((string) ($n->floor_number ?? '')),
                    'start' => $start,
                    'end' => $end,
                    'duration' => $duration,
                    'has_attachments' => $attsCount > 0,
                    'has_image' => $hasImage,
                    'has_video' => $hasVideo,
                    'attachments_count' => $attsCount,
                ];
            } catch (\Throwable) {
                $out[] = null;
            }
        }

        return $out;
    }

    private function distinctObservers(\App\Models\Report $report, int $count): array
    {
        if ($count <= 0) {
            return [];
        }
        try {
            $notes = $report->notes;
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        try {
            foreach ($notes as $n) {
                try {
                    $name = trim((string) ($n->owner->name ?? ''));
                } catch (\Throwable) {
                    $name = '';
                }
                if ($name === '' || $name === '—' || in_array($name, $out, true)) {
                    continue;
                }
                $out[] = $name;
                if (count($out) >= 10) {
                    break;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return array_values($out);
    }

    private function ordinal(int $n, string $locale = 'ar'): string
    {
        if ($locale === 'en') {
            $suffix = match (true) {
                $n % 100 >= 11 && $n % 100 <= 13 => 'th',
                $n % 10 === 1 => 'st',
                $n % 10 === 2 => 'nd',
                $n % 10 === 3 => 'rd',
                default => 'th',
            };

            return $n.$suffix;
        }

        return match ($n) {
            1 => 'الأولى', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة',
            5 => 'الخامسة', 6 => 'السادسة', 7 => 'السابعة', 8 => 'الثامنة',
            9 => 'التاسعة', 10 => 'العاشرة',
            11 => 'الحادية عشرة', 12 => 'الثانية عشرة', 13 => 'الثالثة عشرة',
            14 => 'الرابعة عشرة', 15 => 'الخامسة عشرة', 16 => 'السادسة عشرة',
            17 => 'السابعة عشرة', 18 => 'الثامنة عشرة', 19 => 'التاسعة عشرة',
            20 => 'العشرون', default => "رقم {$n}",
        };
    }
}
