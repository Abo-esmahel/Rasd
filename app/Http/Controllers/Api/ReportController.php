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

    /** Mobile counterpart of the web reports insights dashboard. */
    public function insights(Request $request, \App\Services\ReportInsightsService $insights): JsonResponse
    {
        if (! $request->user()->can('viewInsights', Report::class)) {
            return response()->json([
                'success' => false,
                'message' => __('api.forbidden'),
            ], 403);
        }

        $range = \App\Services\ReportInsightsService::normalizeRange(
            $request->query('range')
        );

        return response()->json([
            'success' => true,
            'data' => $insights->get($range),
        ]);
    }

    /** Daily note/review counts for the report writer's creation flow. */
    public function dailyStatus(Request $request): JsonResponse
    {
        if (!$request->user()->isReportWriter()) {
            return response()->json(['success' => false, 'message' => __('api.report_unauthorized_create')], 403);
        }

        $data = $request->validate([
            'report_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reports->dailyOverview($data['report_date']),
        ]);
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
        if (!$request->user()->can('viewAny', Report::class)) {
            return response()->json(['success' => false, 'message' => __('api.forbidden')], 403);
        }
        $query = $this->reports->getVisibleQuery($request->user())
            ->withCount(['comments', 'reactions']);
        if ($request->filled('status') && in_array($request->status, ['draft', 'published'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('report_date') && strtotime($request->report_date) !== false) {
            $query->whereDate('report_date', $request->report_date);
        }
        if ($request->filled('search') && mb_strlen(trim($request->search)) >= 2) {
            $query->where('title', 'like', '%'.trim($request->search).'%');
        }
        $data = $query->orderByDesc('report_date')->paginate(15);


        $data->getCollection()->transform(fn ($r) => $this->serialize($r, false));

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
        $report->load(['author', 'notes.attachments', 'notes.reactions']);
        $report->load('comments');
        $isOwner = $request->user()->isReportWriter() && (int) $report->author_id === (int) $request->user()->id;
        $data = $this->serialize($report, $isOwner);
        $reportNotes = $report->notes->keyBy('id');
        $data['notes'] = collect($data['notes'] ?? [])->map(function (array $note) use ($reportNotes, $request) {
            $model = $reportNotes->get($note['id']);
            unset($note['reactions']);
            $note['reaction_counts'] = $model?->reactions->groupBy('reaction')->map->count()->all() ?? [];
            $note['my_reaction'] = $model?->reactions->firstWhere('user_id', $request->user()->id)?->reaction;
            return $note;
        })->all();
        $data['reaction_counts'] = $report->reactions()->selectRaw('reaction, count(*) as total')->groupBy('reaction')->pluck('total', 'reaction')->map(fn ($n) => (int) $n)->all();
        $data['my_reaction'] = $report->reactions()->where('user_id', $request->user()->id)->value('reaction');
        $data['comments'] = app(\App\Http\Controllers\Api\EngagementController::class)->commentsData($report, $request->user()->id);

        $data['can_comment'] = $request->user()->can('comment', $report);
        $data['can_react'] = $request->user()->can('reactReport', $report);

        $blocks = app(\App\Services\Report\ReportTextBlocks::class);
        $data['text_blocks'] = [
            'summary' => $blocks->build($data['summary'] ?? null),
            'content' => $blocks->build($data['content'] ?? null),
            'recommendations' => $blocks->build(
                $data['recommendations'] ?? null,
                'recommendations',
            ),
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Official printable document HTML for the Flutter app.
     * Same guards as Web export: export ability + approved published state.
     * Images, styles, and official fonts are embedded so Android WebView and
     * the native HTML-to-PDF renderer receive the same complete document.
     */
    public function document(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->can('export', $report)) {
            return response()->json(['success' => false, 'message' => __('api.forbidden')], 403);
        }
        $report->loadMissing(['author', 'notes']);
        if (!$report->isPublished()) {
            return response()->json(['success' => false, 'message' => __('api.export_unavailable')], 422);
        }
        try {
            $svc = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
            $state = $svc->htmlState($report)['state'] ?? 'none';
            $approved = in_array($state, ['system', 'custom'], true);
            if (!$approved) {
                $n = $report->notes->count();
                $approved = $n > (int) config('report_sheets.max_notes', 7)
                    && trim((string) $report->content) !== '';
            }
            if (!$approved) {
                return response()->json(['success' => false, 'message' => __('api.export_unavailable')], 422);
            }
            $html = $svc->htmlFor($report, $svc->latestFor($report));
            if (!$html) {
                return response()->json(['success' => false, 'message' => __('api.no_approved_version')], 422);
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => __('api.export_unavailable')], 500);
        }

        try {
            $document = $this->makeStandalonePrintableDocument(
                $report,
                $this->inlineLocalImages($html)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[REPORT-DOCUMENT] Could not prepare printable document', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => __('api.export_unavailable')], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => 'report-'.$report->id.'.html',
                'html' => $document,
            ],
        ])->header('Cache-Control', 'private, no-store, must-revalidate');
    }

    private function makeStandalonePrintableDocument(Report $report, string $html): string
    {
        $cssPath = public_path('report/css/report-engine.css');
        $css = is_file($cssPath) ? file_get_contents($cssPath) : false;
        if ($css === false) {
            throw new \RuntimeException('The official report stylesheet is missing.');
        }

        $css = (string) preg_replace_callback(
            '#url\(([\'\"]?)(/report/fonts/[^)\'\"]+)\1\)#u',
            function (array $match): string {
                $fontPath = public_path(ltrim($match[2], '/'));
                if (!is_file($fontPath) || filesize($fontPath) > 2 * 1024 * 1024) {
                    throw new \RuntimeException('An official report font is missing.');
                }
                $contents = file_get_contents($fontPath);
                if ($contents === false) {
                    throw new \RuntimeException('An official report font could not be read.');
                }

                $mime = match (strtolower(pathinfo($fontPath, PATHINFO_EXTENSION))) {
                    'woff2' => 'font/woff2',
                    'woff' => 'font/woff',
                    default => 'font/ttf',
                };

                return 'url("data:'.$mime.';base64,'.base64_encode($contents).'")';
            },
            $css
        );
        $css = str_replace('font-display: swap;', 'font-display: block;', $css);

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $direction = $locale === 'en' ? 'ltr' : 'rtl';
        $title = e((string) $report->title);

        return '<!doctype html><html lang="'.$locale.'" dir="'.$direction.'"><head>'
            .'<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta name="color-scheme" content="light"><title>'.$title.'</title>'
            .'<style>'.$css.'</style>'
            .'<style>html,body{margin:0;min-height:100%;background:#ece7d9}'
            .'body{padding:12px}.report-preview{max-width:210mm;margin:0 auto;padding:0;overflow:visible}'
            .'@media screen and (max-width:640px){body{padding:0;background:#fff}}'
            .'@media print{@page{size:A4;margin:11mm 10mm 12mm}html,body{margin:0!important;padding:0!important;background:#fff!important}'
            .'.report-preview{max-width:none;margin:0;padding:0;background:#fff;border:0;overflow:visible}}'
            .'</style></head><body><main class="report-preview">'.$html.'</main></body></html>';
    }

    private function inlineLocalImages(string $html): string
    {
        return (string) preg_replace_callback(
            '#src="(https?://[^"]+|/[^"]+)"#u',
            function (array $m): string {
                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                $path = parse_url($url, PHP_URL_PATH) ?: '';
                $file = null;
                if (str_contains($path, '/report-assets/logo')) {
                    $file = public_path('report/assets/logo-report.png');
                } elseif (preg_match('#/report-sheets/(\d+)#', $path, $mm)) {
                    try {
                        $candidate = app(\App\Services\ReportSheetService::class)->imagePath((int) $mm[1]);
                        if (is_file($candidate)) {
                            $file = $candidate;
                        }
                    } catch (\Throwable) {
                    }
                }
                if (!$file || !is_file($file) || filesize($file) > 2 * 1024 * 1024) {
                    return $m[0];
                }
                try {
                    $raw = file_get_contents($file);
                    if ($raw === false) {
                        return $m[0];
                    }
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : ($ext === 'svg' ? 'image/svg+xml' : 'image/png');

                    return 'src="data:'.$mime.';base64,'.base64_encode($raw).'"';
                } catch (\Throwable) {
                    return $m[0];
                }
            },
            $html
        );
    }

    /**
     * Official sheet artwork (1..7 notes) for Flutter covers.
     * Mirrors Web sheet() guards with API auth.
     */
    public function sheet(Request $request, int $n)
    {
        if (!$request->user()->can('viewAny', Report::class)) {
            return response()->json(['success' => false, 'message' => __('api.forbidden')], 403);
        }
        if ($n < 1 || $n > (int) config('report_sheets.max_notes', 7)) {
            abort(404);
        }
        $path = app(\App\Services\ReportSheetService::class)->imagePath($n);
        if (!is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
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
        if (trim((string) config('ai.gemini.api_key', '')) === '') {
            $msg = __('api.ai_not_configured');

            return response()->json([
                'success' => false,
                'message' => $msg,
                'errors' => ['ai' => [$msg]],
                'error' => ['code' => 'AI_NOT_CONFIGURED', 'retryable' => false],
            ], 422);
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
        if ($e instanceof \App\Exceptions\Ai\AiAuthenticationException
            && trim((string) config('ai.gemini.api_key', '')) === '') {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['ai' => [$e->getMessage()]],
                'error' => ['code' => 'AI_NOT_CONFIGURED', 'retryable' => false],
            ], 422);
        }
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
