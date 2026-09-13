<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\AiAuthenticationException;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiGenerationBusyException;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ReportAiService
{
    public function __construct(
        private ReportService $reports,
        private ReportAiContextResolver $resolver,
        private ReportAiPromptBuilder $prompts,
        private AiAttachmentResolver $attachments,
        private AiTextGeneratorInterface $generator,
    ) {
    }

    public function generateFor(User $user, Report $report, bool $regenerate = false, bool $confirmOverwriteManual = false, bool $withImages = true, ?string $locale = null): Report
    {
        if (!$user->isReportWriter() || (int) $report->author_id !== (int) $user->id) {
            throw new InvalidArgumentException(__('api.report_unauthorized_generate'));
        }
        if (!config('ai.enabled', false)) {
            throw new AiDisabledException(__('api.ai_disabled'));
        }
        if ((string) config('ai.provider', 'gemini') !== 'gemini') {
            throw new InvalidArgumentException(__('api.ai_unsupported_provider'));
        }
        if (trim((string) config('ai.gemini.api_key', '')) === '') {
            throw new AiAuthenticationException(__('api.ai_not_configured'));
        }
        // Backend decides language — AI never guesses from notes
        $locale = $locale ? strtolower(trim($locale)) : strtolower((string) app()->getLocale());
        if (!in_array($locale, ['ar', 'en'], true)) $locale = 'ar';

        $geminiTimeout = max(10, (int) config('ai.gemini.timeout', 60));
        $wantedPhpLimit = max(120, $geminiTimeout + 60);
        if (function_exists('set_time_limit')) {
            @set_time_limit($wantedPhpLimit);
        }
        @ini_set('max_execution_time', (string) $wantedPhpLimit);


        $lock = \Illuminate\Support\Facades\Cache::lock(ReportService::aiLockKey($report->id), 120);
        if (!$lock->get()) {
            throw new AiGenerationBusyException(__('api.ai_busy'));
        }

        try {
            DB::transaction(function () use ($report) {
                $locked = Report::with(['notes.attachments'])->lockForUpdate()->findOrFail($report->id);

                if (!$locked->isDraft()) {
                    throw new InvalidArgumentException(__('api.ai_draft_only'));
                }
                $notes = $locked->notes()->with('attachments')->orderByPivot('order_index')->get();
                if ($notes->isEmpty()) {
                    throw new InvalidArgumentException(__('api.ai_need_accepted_note'));
                }
                $day = $locked->report_date->toDateString();
                foreach ($notes as $n) {
                    if ($n->status !== \App\Models\Note::STATUS_ACCEPTED) {
                        throw new InvalidArgumentException(__('api.report_note_not_accepted', ['id' => $n->id]));
                    }
                    if ($this->reports->reportDay($n) !== $day) {
                        throw new InvalidArgumentException(__('api.report_note_wrong_day', ['id' => $n->id]));
                    }
                }
            });

            $fresh = Report::with(['notes.attachments'])->findOrFail($report->id);
            $notes = $fresh->notes()->with('attachments')->orderByPivot('order_index')->get();

            $hasManualFields = trim((string) $fresh->summary . (string) $fresh->recommendations) !== '';
            if ($fresh->ai_draft_content && !$regenerate) {
                throw new InvalidArgumentException(__('api.ai_previous_draft'));
            }
            if ($hasManualFields && !$confirmOverwriteManual) {
                throw new InvalidArgumentException(__('api.ai_manual_exists'));
            }

            $slice = $this->resolver->resolveFor($fresh);
            $resolved = $withImages
                ? $this->attachments->resolve($notes, (int) config('ai.max_images_per_generation', 3))
                : ['images' => [], 'metadata' => $this->attachments->resolve($notes, 0)['metadata'], 'skipped' => []];
            $built = $this->prompts->build($fresh, $notes, $slice, $resolved['metadata'], $locale);

            $started = microtime(true);
            try {
                $result = !empty($resolved['images'])
                    ? $this->generator->generateWithImages($built['system'], $built['user'], $resolved['images'])
                    : $this->generator->generate($built['system'], $built['user']);
            } catch (AiException $e) {
                throw $e;
            }
            $latency = (int) ((microtime(true) - $started) * 1000);

            Log::info('[AI] generation', [
                'report_id' => $fresh->id,
                'provider' => 'gemini',
                'model' => config('ai.gemini.model'),
                'locale' => $locale,
                'latency_ms' => $latency,
                'success' => true,
                'images_sent' => count($resolved['images']),
                'images_skipped' => count($resolved['skipped']),
            ]);

            return DB::transaction(function () use ($report, $result) {
                $locked = Report::lockForUpdate()->findOrFail($report->id);
                if (!$locked->isDraft()) {
                    throw new InvalidArgumentException(__('api.ai_draft_only'));
                }
                $locked->update([
                    'ai_draft_content' => $result->toContent(),
                    'ai_summary' => $result->summary !== '' ? $result->summary : null,
                    'ai_recommendations' => $result->recommendations !== '' ? $result->recommendations : null,
                    'generation_mode' => Report::MODE_AI,
                ]);

                return $locked->fresh(['notes', 'author']);
            });
        } catch (AiException $e) {
            Log::warning('[AI] generation failed', ['report_id' => $report->id, 'error' => get_class($e)]);
            throw $e;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
            }
        }
    }
}
