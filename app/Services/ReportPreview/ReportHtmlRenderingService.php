<?php

namespace App\Services\ReportPreview;

use App\Models\Report;
use App\Models\ReportSheetRender;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ReportHtmlRenderingService
{
    public function __construct(
        private ReportTemplateSelector $selector,
        private ReportDataBuilder $dataBuilder,
    ) {
    }

    public static function lockKey(int $reportId): string
    {
        return "report-html-render-{$reportId}";
    }

    public function render(User $user, Report $report, array $data, int $dataVersion = 1, ?string $requestUid = null): array
    {
        if (!$user->isReportWriter() || (int) $report->author_id !== (int) $user->id) {
            throw new InvalidArgumentException(__('api.report_unauthorized_fill'));
        }
        if ($dataVersion < 1) {
            throw new InvalidArgumentException(__('api.report_render_invalid_version'));
        }

        $fresh = Report::with(['author', 'notes'])->findOrFail($report->id);
        if (!$fresh->isDraft()) {
            throw new InvalidArgumentException(__('api.report_fill_draft_only'));
        }

        $data['observations'] = \App\Services\Report\ReportEngine::normalizeObservations($data['observations'] ?? []);
        $data['recommendations'] = \App\Services\Report\ReportEngine::normalizeText($data['recommendations'] ?? '');

        $payload = ReportRenderPayload::fromArray([
            'report_number' => (string) $fresh->id,
            'date' => $fresh->report_date ? $fresh->report_date->toDateString() : '',
            'location' => $data['location'] ?? '',
            'observations' => $data['observations'] ?? [],
            'recommendations' => $data['recommendations'] ?? '',
        ]);

        $selected = $this->selector->selectForPayload($payload);
        if (!$selected) {
            throw new InvalidArgumentException(__('api.report_render_range'));
        }
        $payload = ReportRenderPayload::fromArray(array_merge($payload->toAiArray(), ['template' => $selected['key']]));

        $hash = $payload->hash();

        $lock = Cache::lock(self::lockKey($fresh->id), 60);
        if (!$lock->get()) {
            throw new InvalidArgumentException(__('api.ai_busy'));
        }

        try {
            $existing = ReportSheetRender::where('report_id', $fresh->id)->where('payload_hash', $hash)->first();
            if ($existing) {
                if (empty($existing->payload)) {
                    try {
                        $existing->update(['payload' => $payload->toAiArray()]);
                    } catch (\Throwable $ignored) {
                    }
                }
                $this->pointCurrentAt($fresh, $existing);
                $this->syncRecommendations($fresh, $payload);
                $this->refreshSystemHash($fresh, $existing);
                Log::info('[HTML-RENDER] cache hit', ['report_id' => $fresh->id, 'generation_id' => $existing->generationId()]);

                return $this->meta($fresh, $existing->fresh(), $payload, $selected, $dataVersion, true, $requestUid);
            }

            $systemHash = $this->systemHash($fresh);
            $payloadJson = $payload->toAiArray();

            return DB::transaction(function () use ($fresh, $selected, $hash, $systemHash, $payloadJson, $payload, $dataVersion, $requestUid) {
                $dup = ReportSheetRender::where('report_id', $fresh->id)->where('payload_hash', $hash)->lockForUpdate()->first();
                if ($dup) {
                    if (empty($dup->payload)) {
                        try {
                            $dup->update(['payload' => $payloadJson]);
                        } catch (\Throwable $ignored) {
                        }
                    }
                    $this->pointCurrentAt($fresh, $dup);
                    $this->syncRecommendations($fresh, $payload);
                    $this->refreshSystemHash($fresh, $dup);

                    return $this->meta($fresh, $dup->fresh(), $payload, $selected, $dataVersion, true, $requestUid);
                }

                $nextNo = (int) (ReportSheetRender::where('report_id', $fresh->id)->max('generation_no') ?? 0) + 1;

                try {
                    $row = ReportSheetRender::create([
                        'report_id' => $fresh->id,
                        'generation_no' => $nextNo,
                        'data_version' => $dataVersion,
                        'template' => $selected['key'],
                        'payload_hash' => $hash,
                        'system_hash' => $systemHash,
                        'payload' => $payloadJson,
                        'image_path' => null,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $dup = ReportSheetRender::where('report_id', $fresh->id)->where('payload_hash', $hash)->first();
                    if (!$dup) {
                        throw $e;
                    }
                    if (empty($dup->payload)) {
                        try {
                            $dup->update(['payload' => $payloadJson]);
                        } catch (\Throwable $ignored) {
                        }
                    }
                    $this->pointCurrentAt($fresh, $dup);
                    $this->syncRecommendations($fresh, $payload);
                    $this->refreshSystemHash($fresh, $dup);

                    return $this->meta($fresh, $dup->fresh(), $payload, $selected, $dataVersion, true, $requestUid);
                }

                $this->pointCurrentAt($fresh, $row);
                $this->syncRecommendations($fresh, $payload);
                $this->refreshSystemHash($fresh, $row);
                $row = $row->fresh() ?? $row;
                Log::info('[HTML-RENDER] generated', ['report_id' => $fresh->id, 'generation_id' => $row->generationId(), 'template' => $selected['key']]);

                return $this->meta($fresh, $row, $payload, $selected, $dataVersion, false, $requestUid);
            });
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
            }
        }
    }

    public function renderSystem(User $user, Report $report, int $dataVersion = 1): array
    {
        $system = $this->dataBuilder->systemData($report->loadMissing(['author', 'notes']));

        return $this->render($user, $report, $system, $dataVersion);
    }

    public function systemHash(Report $report): string
    {
        try {
            $system = $this->dataBuilder->systemData($report->loadMissing(['author', 'notes']));
            $system['observations'] = \App\Services\Report\ReportEngine::normalizeObservations($system['observations']);
            $key = $this->selector->keyForCount(count($system['observations']));
            if ($key === null) {
                return hash('sha256', json_encode(array_merge($system, ['template' => 'template:none']), JSON_UNESCAPED_UNICODE));
            }

            return ReportRenderPayload::fromArray(array_merge($system, ['template' => $key]))->hash();
        } catch (\Throwable $e) {
            return 'invalid:' . $report->id . ':' . $report->updated_at?->timestamp;
        }
    }

    public function htmlState(Report $report): array
    {
        $latest = $this->latestFor($report);
        if (!$latest) {
            return ['state' => 'none', 'render' => null];
        }
        $systemHash = $this->systemHash($report);
        if ($latest->payload_hash === $systemHash) {
            return ['state' => 'system', 'render' => $latest];
        }
        if ($latest->system_hash !== null && $latest->system_hash === $systemHash) {
            return ['state' => 'custom', 'render' => $latest];
        }

        return ['state' => 'stale', 'render' => $latest];
    }

    public function imageState(Report $report): array
    {
        return $this->htmlState($report);
    }

    public function latestFor(Report $report): ?ReportSheetRender
    {
        try {
            return ReportSheetRender::where('report_id', $report->id)->orderByDesc('generation_no')->first();
        } catch (\Illuminate\Database\QueryException $e) {
            return null;
        }
    }

    public function latestPayloadForEditor(Report $report): ?array
    {
        $latest = $this->latestFor($report);
        if ($latest && is_array($latest->payload)
            && isset($latest->payload['observations']) && is_array($latest->payload['observations'])) {
            $obs = \App\Services\Report\ReportEngine::normalizeObservations($latest->payload['observations']);
            if ($obs !== []) {
                return [
                    'observations' => $obs,
                    'recommendations' => \App\Services\Report\ReportEngine::normalizeText($latest->payload['recommendations'] ?? ''),
                ];
            }
        }
        try {
            $system = $this->dataBuilder->systemData($report);
            if (empty($system['observations'])) {
                return null;
            }

            return [
                'observations' => $system['observations'],
                'recommendations' => $system['recommendations'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function htmlFor(Report $report, ?ReportSheetRender $row = null): ?string
    {
        $row ??= $this->latestFor($report);
        if (!$row || !is_array($row->payload) || empty($row->payload['observations'])) {
            return null;
        }
        $report->loadMissing(['author']);

        try {
            $locale = \App\Services\Localization\SourceLanguage::normalizeLocale(app()->getLocale());
            $observations = array_values($row->payload['observations']);
            $recommendations = (string) ($row->payload['recommendations'] ?? '');

            if ($locale === 'en') {
                try {
                    $presenter = app(\App\Services\Localization\LocalizedPresenter::class);
                    $localized = $presenter->reportPayload($report->id, [
                        'observations' => $observations,
                        'recommendations' => $recommendations,
                    ], 'en');
                    $observations = $localized['observations'];
                    $recommendations = $localized['recommendations'];
                } catch (\Throwable) {
                }
            }

            $doc = app(\App\Services\Report\ReportEngine::class)->build($report, [
                'report_number' => (string) ($row->payload['report_number'] ?? $report->id),
                'date' => (string) ($row->payload['date'] ?? ($report->report_date ? $report->report_date->toDateString() : '')),
                'location' => (string) ($row->payload['location'] ?? ''),
                'observations' => $observations,
                'recommendations' => $recommendations,
            ], [
                'locale' => $locale,
                'generated_at' => $row->updated_at?->format('Y-m-d H:i') ?? '',
                'generation_id' => $row->generationId(),
            ]);

            return view('reports.engine.document', ['doc' => $doc])->render();
        } catch (\Throwable $e) {
            Log::warning('[HTML-RENDER] htmlFor failed', ['report_id' => $report->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function syncRecommendations(Report $report, ReportRenderPayload $payload): void
    {
        try {
            $fresh = Report::find($report->id);
            if (!$fresh) {
                return;
            }
            $customReco = trim($payload->recommendations);
            $storedReco = trim((string) $fresh->recommendations);
            if ($customReco !== $storedReco) {
                $fresh->update(['recommendations' => $customReco !== '' ? $customReco : null]);
                $fresh = $fresh->fresh(['notes', 'author']);
            }
            if (trim((string) $fresh->content) === '') {
                $composed = app(\App\Services\ReportService::class)->composeContent($fresh);
                $fresh->update(['content' => $composed]);
            }
        } catch (\Throwable $e) {
            Log::warning('[HTML-RENDER] sync recommendations failed', ['report_id' => $report->id, 'error' => $e->getMessage()]);
        }
    }

    private function pointCurrentAt(Report $report, ReportSheetRender $row): void
    {
        $report->update([
            'ai_sheet_image_path' => null,
            'ai_sheet_generated_at' => now(),
            'ai_sheet_data_hash' => $row->payload_hash,
        ]);
    }

    private function refreshSystemHash(Report $report, ReportSheetRender $row): void
    {
        try {
            $current = $this->systemHash($report->fresh(['notes', 'author']));
            if ($row->system_hash !== $current) {
                $row->update(['system_hash' => $current]);
            }
        } catch (\Throwable $e) {
            Log::warning('[HTML-RENDER] refresh system hash failed', ['report_id' => $report->id, 'error' => $e->getMessage()]);
        }
    }

    /** @return array{generation_id: string, data_version: int, template: string, screen: ?string, preview_url: string, html: string, cached: bool, generated_at: string, request_uid: ?string} */
    private function meta(Report $fresh, ReportSheetRender $row, ReportRenderPayload $payload, array $selected, int $dataVersion, bool $cached, ?string $requestUid): array
    {
        $this->warmPayloadProjections((int) $fresh->id, $payload);
        $fresh->loadMissing(['author']);
        $generatedAt = $row->updated_at?->format('Y-m-d H:i') ?? '';
        $doc = app(\App\Services\Report\ReportEngine::class)->build($fresh, $payload->toAiArray(), [
            'generated_at' => $generatedAt,
            'generation_id' => $row->generationId(),
        ]);
        $html = view('reports.engine.document', ['doc' => $doc])->render();

        return [
            'generation_id' => $row->generationId(),
            'data_version' => $dataVersion,
            'template' => $row->template,
            'screen' => $selected['sheet']['screen'] ?? null,
            'preview_url' => route('reports.sheet-html', $fresh->id),
            'html' => $html,
            'cached' => $cached,
            'generated_at' => $row->updated_at?->format('Y-m-d H:i') ?? '',
            'request_uid' => $requestUid,
        ];
    }

    private function warmPayloadProjections(int $reportId, ReportRenderPayload $payload): void
    {
        try {
            if ($reportId <= 0) {
                return;
            }
            $observations = array_values((array) ($payload->observations ?? []));
            $recommendations = trim((string) ($payload->recommendations ?? ''));
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
            $uiLocale = \App\Services\Localization\SourceLanguage::normalizeLocale(app()->getLocale());
            $target = null;
            foreach ($fields as $text) {
                $lang = \App\Services\Localization\TranslationService::detectCached($text);
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
                    $refs[] = ['type' => 'report', 'id' => $reportId, 'field' => $field, 'text' => $text];
                }
                $ready = $service->resolveStoredMany($refs, $target);
                foreach (array_keys($fields) as $field) {
                    $k = \App\Services\Localization\TranslationService::itemKey('report', $reportId, (string) $field);
                    if (isset($ready[$k])) {
                        unset($fields[$field]);
                    }
                }
                if ($fields === []) {
                    return;
                }
            } catch (\Throwable) {
            }
            \App\Jobs\WarmTranslationProjection::dispatchAfterResponse('report', $reportId, $fields, $target);
        } catch (\Throwable) {
        }
    }
}
