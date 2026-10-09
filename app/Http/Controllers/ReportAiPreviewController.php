<?php

namespace App\Http\Controllers;

use App\Exceptions\Ai\AiAuthenticationException;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiGenerationBusyException;
use App\Exceptions\Ai\AiInvalidResponseException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiUnavailableException;
use App\Models\Report;
use App\Services\Ai\ReportAiService;
use App\Services\Localization\SourceLanguage;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReportAiPreviewController extends Controller
{
    public function __invoke(Request $request, ReportService $reports, ReportAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'report_date' => ['required_without:report_id', 'date', 'before_or_equal:today'],
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
        ]);

        $user = $request->user();
        if (!$user?->isReportWriter()) {
            return $this->error(__('api.report_unauthorized_generate'), 403, 'AI_VALIDATION', false);
        }

        if (!empty($data['report_id'])) {
            $report = Report::with(['notes.attachments'])->findOrFail($data['report_id']);
            if (!$user->can('generateAi', $report)) {
                return $this->error(__('api.report_unauthorized_generate'), 403, 'AI_VALIDATION', false);
            }
            $notes = $report->notes;
        } else {
            if (!$user->can('create', Report::class)) {
                return $this->error(__('api.report_unauthorized_create'), 403, 'AI_VALIDATION', false);
            }
            $date = \Carbon\Carbon::parse($data['report_date'])->toDateString();
            if (Report::whereDate('report_date', $date)->exists()) {
                return $this->error(__('api.report_duplicate_day'), 409, 'REPORT_DUPLICATE_DAY', false);
            }
            $notes = $reports->aiPreviewNotesForDate($date);
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '') {
                $title = __('report.daily_title_default', ['date' => $date]);
            }
            $report = new Report([
                'author_id' => $user->id,
                'title' => $title,
                'report_date' => $date,
                'status' => Report::STATUS_DRAFT,
            ]);
            $report->setRelation('author', $user);
            $report->setRelation('notes', $notes);
        }

        try {
            $locale = SourceLanguage::normalizeLocale(app()->getLocale());
            $result = $ai->previewFor($user, $report, $notes, false, $locale);
            $content = implode("\n\n", array_filter([
                trim($result->summary),
                trim($result->body),
                trim($result->recommendations) !== ''
                    ? (($locale === 'en' ? 'Recommendations:' : 'التوصيات:')."\n".trim($result->recommendations))
                    : '',
            ], fn ($part) => $part !== ''));
            if (mb_strlen($content) > 20000) {
                throw new AiInvalidResponseException(__('api.ai_invalid'));
            }

            return response()->json([
                'success' => true,
                'message' => __('api.report_data_generated'),
                'data' => [
                    'title' => trim($result->title),
                    'summary' => trim($result->summary),
                    'recommendations' => trim($result->recommendations),
                    'content' => $content,
                    'note_count' => $notes->count(),
                    'accepted_count' => $notes->where('status', \App\Models\Note::STATUS_ACCEPTED)->count(),
                    'pending_count' => $notes->where('status', \App\Models\Note::STATUS_PENDING)->count(),
                    'note_ids' => $notes->pluck('id')->values(),
                    'report_date' => $report->report_date->toDateString(),
                ],
            ]);
        } catch (AiException|InvalidArgumentException $e) {
            [$status, $code, $retryable] = $this->errorDetails($e);
            if ($e instanceof AiAuthenticationException && trim((string) config('ai.gemini.api_key', '')) === '') {
                $code = 'AI_NOT_CONFIGURED';
                $status = 422;
            }
            return $this->error($e->getMessage(), $status, $code, $retryable);
        }
    }

    private function error(string $message, int $status, string $code, bool $retryable): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'error' => ['code' => $code, 'retryable' => $retryable],
        ];
        if ($code === 'AI_NOT_CONFIGURED') {
            $payload['errors'] = ['ai' => [$message]];
        }
        return response()->json($payload, $status);
    }

    /** @return array{int, string, bool} */
    private function errorDetails(\Throwable $e): array
    {
        return match (true) {
            $e instanceof AiRateLimitException => [429, 'AI_RATE_LIMITED', true],
            $e instanceof AiAuthenticationException => [502, 'AI_AUTH', false],
            $e instanceof AiUnavailableException => [503, 'AI_UNAVAILABLE', true],
            $e instanceof AiInvalidResponseException => [502, 'AI_BAD_RESPONSE', true],
            $e instanceof AiDisabledException => [403, 'AI_DISABLED', false],
            $e instanceof AiGenerationBusyException => [409, 'AI_BUSY', true],
            default => [422, 'AI_VALIDATION', false],
        };
    }
}
