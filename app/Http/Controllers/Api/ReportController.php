<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Ai\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Report\AttachNotesRequest;
use App\Http\Requests\Api\Report\ReorderNotesRequest;
use App\Http\Requests\Api\Report\StoreReportRequest;
use App\Http\Requests\Api\Report\UpdateReportRequest;
use App\Models\Report;
use App\Services\Ai\ReportAiService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private ReportAiService $ai)
    {
    }

    private function serialize(Report $r, bool $withAi, bool $withRevisions = false): array
    {
        $a = $r->toArray();
        if (!$withAi) {
            unset($a['ai_draft_content'], $a['ai_summary'], $a['ai_recommendations'], $a['ai_sheet_image_path'], $a['ai_sheet_data_hash']);
        }
        if (!$withRevisions) {
            unset($a['revisions']);
        }

        return $a;
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->reports->getVisibleQuery($request->user());
        if ($request->filled('status') && in_array($request->status, ['draft', 'published'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('report_date') && strtotime($request->report_date) !== false) {
            $query->whereDate('report_date', $request->report_date);
        }
        $data = $query->orderByDesc('report_date')->paginate(15);


        $isWriter = $request->user()->isReportWriter();
        $data->getCollection()->transform(fn ($r) => $this->serialize($r, $isWriter));

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        if (!$request->user()->can('create', Report::class)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_create')], 403);
        }
        try {
            $r = $this->reports->createDraft($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $r->load(['author', 'notes']);
        $auto = $r->notes->count();

        return response()->json(['success' => true, 'message' => $auto > 0 ? __('api.report_draft_created_auto_api', ['n' => $auto]) : __('api.report_draft_created'), 'data' => $r], 201);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('view', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_view')], 403);
        }
        $report->load(['author', 'notes.attachments']);
        $isWriter = $request->user()->isReportWriter();

        return response()->json(['success' => true, 'data' => $this->serialize($report, $isWriter)]);
    }

    public function update(UpdateReportRequest $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('update', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_update')], 403);
        }
        try {
            $r = $this->reports->update($request->user(), $report, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('api.report_updated'), 'data' => $r->load(['author', 'notes'])]);
    }

    public function destroy(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('delete', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_delete')], 403);
        }
        $this->reports->delete($request->user(), $report);

        return response()->json(['success' => true, 'message' => __('api.report_deleted')]);
    }

    public function publish(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('publish', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_publish')], 403);
        }
        try {
            $r = $this->reports->publish($request->user(), $report);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('api.report_published'), 'data' => $r]);
    }

    public function unpublish(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('unpublish', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_unpublish')], 403);
        }
        try {
            $r = $this->reports->unpublish($request->user(), $report);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('api.report_unpublished'), 'data' => $r]);
    }

    public function attach(AttachNotesRequest $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('attachNotes', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_manage')], 403);
        }
        try {
            $r = $this->reports->attachNotes($request->user(), $report, $request->validated()['note_ids']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('api.report_notes_added'), 'data' => $r]);
    }

    public function detach(Request $request, Report $report, int $noteId): JsonResponse
    {
        if (!$request->user()->can('attachNotes', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_manage')], 403);
        }
        try {
            $r = $this->reports->detachNote($request->user(), $report, $noteId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('api.report_notes_removed'), 'data' => $r]);
    }

    public function reorder(ReorderNotesRequest $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('attachNotes', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_manage')], 403);
        }
        try {
            $r = $this->reports->reorderNotes($request->user(), $report, $request->validated()['ordered_ids']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('api.report_reordered'), 'data' => $r]);
    }

    public function generate(Request $request, Report $report): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');
        if (!$request->user()->can('generateAi', $report)) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_generate')], 403);
        }
        $request->validate([
            'regenerate' => ['nullable', 'boolean'],
            'confirm_overwrite_manual' => ['nullable', 'boolean'],
            'include_images' => ['nullable', 'boolean'],
        ]);
        $withImages = !$request->has('include_images') || $request->boolean('include_images');
        $locale = \App\Services\Localization\SourceLanguage::normalizeLocale($request->query('locale', app()->getLocale()));
        try {
            $r = $this->ai->generateFor(
                $request->user(), $report,
                (bool) $request->boolean('regenerate'),
                (bool) $request->boolean('confirm_overwrite_manual'),
                $withImages,
                $locale
            );
        } catch (AiException | InvalidArgumentException $e) {
            return $this->aiErrorResponse($e);
        }

        $fresh = $r->fresh(['author', 'notes']);

        return response()->json(['success' => true, 'message' => __('api.report_generated_check'), 'data' => array_merge(
            $this->serialize($fresh, true),
            ['ai_summary' => $fresh->ai_summary, 'ai_recommendations' => $fresh->ai_recommendations]
        )]);
    }

    /**
     * Smart structured AI errors: exact code + retryable flag, never vague.
     */
    private function aiErrorResponse(\Throwable $e): JsonResponse
    {
        $map = [
            \App\Exceptions\Ai\AiRateLimitException::class => [429, 'AI_RATE_LIMITED', true],
            \App\Exceptions\Ai\AiAuthenticationException::class => [502, 'AI_AUTH', false],
            \App\Exceptions\Ai\AiUnavailableException::class => [503, 'AI_UNAVAILABLE', true],
            \App\Exceptions\Ai\AiInvalidResponseException::class => [502, 'AI_BAD_RESPONSE', true],
        ];
        foreach ($map as $class => [$status, $code, $retryable]) {
            if ($e instanceof $class) {
                $payload = [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => ['code' => $code, 'retryable' => $retryable],
                ];
                if ($e instanceof \App\Exceptions\Ai\AiRateLimitException && $e->retryAfter) {
                    $payload['error']['retry_after'] = $e->retryAfter;
                }

                return response()->json($payload, $status);
            }
        }

        $msg = $e->getMessage();
        if ($e instanceof \App\Exceptions\Ai\AiDisabledException) {
            return response()->json(['success' => false, 'message' => $msg, 'error' => ['code' => 'AI_DISABLED', 'retryable' => false]], 403);
        }
        if ($e instanceof \App\Exceptions\Ai\AiGenerationBusyException) {
            return response()->json(['success' => false, 'message' => $msg, 'error' => ['code' => 'AI_BUSY', 'retryable' => true]], 409);
        }

        return response()->json(['success' => false, 'message' => $msg, 'error' => ['code' => 'AI_VALIDATION', 'retryable' => false]], 422);
    }
}
