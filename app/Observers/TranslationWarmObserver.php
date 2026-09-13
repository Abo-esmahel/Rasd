<?php

namespace App\Observers;

use App\Jobs\WarmTranslationProjection;
use App\Services\Localization\SourceLanguage;
use App\Services\Localization\TranslationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TranslationWarmObserver
{
    public function saved(Model $model): void
    {
        try {
            $plan = $this->planFor($model);
            if ($plan === null) {
                return;
            }
            [$type, $id, $fields] = $plan;

            $uiLocale = SourceLanguage::normalizeLocale(app()->getLocale());
            $byTarget = ['ar' => [], 'en' => []];
            foreach ($fields as $field => $text) {
                $lang = TranslationService::detectCached($text);
                if ($lang === SourceLanguage::ARABIC) {
                    $byTarget['en'][$field] = $text;
                } elseif ($lang === SourceLanguage::ENGLISH) {
                    $byTarget['ar'][$field] = $text;
                } else {
                    $t = $uiLocale === 'ar' ? 'en' : 'ar';
                    $byTarget[$t][$field] = $text;
                }
            }

            $service = null;
            try { $service = app(\App\Services\Localization\TranslationService::class); } catch (\Throwable) {}
            foreach (['ar', 'en'] as $target) {
                if (empty($byTarget[$target])) continue;
                $targetFields = $byTarget[$target];
                // dedup per target
                if ($service) {
                    try {
                        $refs = [];
                        foreach ($targetFields as $field => $text) {
                            $refs[] = ['type' => $type, 'id' => $id, 'field' => $field, 'text' => $text];
                        }
                        $ready = $service->resolveStoredMany($refs, $target);
                        foreach (array_keys($targetFields) as $field) {
                            $k = \App\Services\Localization\TranslationService::itemKey($type, $id, (string) $field);
                            if (isset($ready[$k])) unset($targetFields[$field]);
                        }
                        if ($targetFields === []) continue;
                    } catch (\Throwable) {}
                }
                WarmTranslationProjection::dispatchAfterResponse($type, $id, $targetFields, $target);
            }
        } catch (\Throwable $e) {
            Log::warning('[L10N] warm dispatch skipped', [
                'model' => get_class($model),
                'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 150),
            ]);
        }
    }

    /**
     * @return array{0: string, 1: int, 2: array<string,string>}|null
     */
    private function planFor(Model $model): ?array
    {
        $class = get_class($model);

        $fields = match (true) {
            $model instanceof \App\Models\Note => [
                'description' => (string) ($model->description ?? ''),
                'rejection_reason' => (string) ($model->rejection_reason ?? ''),
            ],
            $model instanceof \App\Models\GeneralSubmission => [
                'description' => (string) ($model->description ?? ''),
                'rejection_reason' => (string) ($model->rejection_reason ?? ''),
            ],
            $model instanceof \App\Models\Report => [
                'title' => (string) ($model->title ?? ''),
                'summary' => (string) ($model->summary ?? ''),
                'content' => (string) ($model->content ?? ''),
                'recommendations' => (string) ($model->recommendations ?? ''),
                ...$this->reportObservationFields($model),
            ],
            default => null,
        };
        if ($fields === null) {
            return null;
        }

        $type = match ($class) {
            \App\Models\Note::class => 'note',
            \App\Models\GeneralSubmission::class => 'submission',
            \App\Models\Report::class => 'report',
            default => null,
        };
        if ($type === null || !is_numeric($model->getKey())) {
            return null;
        }

        $fields = array_filter(
            array_map(fn ($t) => trim((string) $t), $fields),
            fn ($t) => $t !== '' && mb_strlen($t) <= 5000
        );
        if ($fields === []) {
            return null;
        }

        return [$type, (int) $model->getKey(), $fields];
    }

    private function reportObservationFields(Model $model): array
    {
        try {
            if (!($model instanceof \App\Models\Report)) {
                return [];
            }
            $builder = app(\App\Services\ReportPreview\ReportDataBuilder::class);
            $system = $builder->systemData($model);
            $observations = array_values((array) ($system['observations'] ?? []));
            if ($observations === []) {
                return [];
            }
            $out = [];
            foreach (array_slice($observations, 0, 25) as $i => $obs) {
                $text = trim((string) $obs);
                if ($text !== '' && mb_strlen($text) <= 5000) {
                    $out["observation:{$i}"] = $text;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
