<?php

namespace App\Services\ReportPreview;

use App\Models\Report;

/**
 * يبني البيانات الافتراضية (System Generated Data) من Report + Notes.
 * المصدر الافتراضي = السيرفر/قاعدة البيانات، وليس الـAI.
 *
 * @return array{report_number: string, date: string, location: string, observations: string[], recommendations: string}
 */
final class ReportDataBuilder
{
    /** @return array{report_number: string, date: string, location: string, observations: string[], recommendations: string} */
    public function systemData(Report $report): array
    {
        $report->loadMissing(['author', 'notes']);
        $observations = [];
        foreach ($report->notes->values() as $n) {
            $observations[] = trim((string) $n->description);
        }

        return [
            'report_number' => (string) $report->id,
            'date' => $report->report_date ? $report->report_date->toDateString() : '',
            'location' => '',
            'observations' => $observations,
            'recommendations' => trim((string) $report->recommendations),
        ];
    }
}
