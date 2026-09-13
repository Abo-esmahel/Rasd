<?php

namespace App\Services\ReportPreview;

use InvalidArgumentException;

final class ReportRenderPayload
{
    public const MAX_OBSERVATIONS = 100;

    public const MIN_OBSERVATIONS = 1;

    public const MAX_TEXT = 2000;

    public const MAX_LOCATION = 255;

    /**
     * @param  string[]  $observations
     */
    private function __construct(
        public readonly string $template,
        public readonly string $reportNumber,
        public readonly string $date,
        public readonly string $location,
        public readonly array $observations,
        public readonly string $recommendations,
    ) {
    }

    /**
     * @param  array{template?: string, report_number?: mixed, date?: mixed, location?: mixed, observations?: mixed, recommendations?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $observations = $data['observations'] ?? [];
        if (!is_array($observations)) {
            throw new InvalidArgumentException(__('api.report_payload_invalid'));
        }
        $observations = array_values(array_map(
            fn ($o) => trim((string) (is_array($o) ? ($o['text'] ?? '') : $o)),
            $observations
        ));
        $count = count($observations);
        if ($count < self::MIN_OBSERVATIONS || $count > self::MAX_OBSERVATIONS) {
            throw new InvalidArgumentException(__('api.report_payload_range'));
        }
        foreach ($observations as $o) {
            if ($o === '') {
                throw new InvalidArgumentException(__('api.report_payload_empty_note'));
            }
            if (mb_strlen($o) > self::MAX_TEXT) {
                throw new InvalidArgumentException(__('api.report_payload_note_too_long'));
            }
        }
        $recommendations = trim((string) ($data['recommendations'] ?? ''));
        if (mb_strlen($recommendations) > self::MAX_TEXT) {
            throw new InvalidArgumentException(__('api.report_payload_reco_too_long'));
        }
        $location = trim((string) ($data['location'] ?? ''));
        if (mb_strlen($location) > self::MAX_LOCATION) {
            throw new InvalidArgumentException(__('api.report_payload_location_too_long'));
        }

        return new self(
            template: (string) ($data['template'] ?? ''),
            reportNumber: trim((string) ($data['report_number'] ?? '')),
            date: trim((string) ($data['date'] ?? '')),
            location: $location,
            observations: $observations,
            recommendations: $recommendations,
        );
    }

    public function toAiArray(): array
    {
        return [
            'template' => $this->template,
            'report_number' => $this->reportNumber,
            'date' => $this->date,
            'location' => $this->location,
            'observations' => $this->observations,
            'recommendations' => $this->recommendations,
        ];
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->toAiArray(), JSON_UNESCAPED_UNICODE));
    }

    public function observationCount(): int
    {
        return count($this->observations);
    }
}
