<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\Ai\AiException;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Report;
use App\Services\Ai\ReportAiService;
use App\Services\ReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ReportService $reports, private ReportAiService $ai)
    {
    }

    public function index(Request $request, \App\Services\Localization\LocalizedPresenter $presenter)
    {
        $this->authorize('viewAny', Report::class);
        $query = $this->reports->getVisibleQuery($request->user());
        if ($request->user()->isReportWriter() && $request->filled('status') && in_array($request->status, ['draft', 'published'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('report_date') && strtotime($request->report_date) !== false) {
            $query->whereDate('report_date', $request->report_date);
        }
        $reports = $query->orderByDesc('report_date')->paginate(15)->withQueryString();
        try { $presenter->preloadReports($reports->items()); } catch (\Throwable) {}

        return view('reports.index', compact('reports'));
    }

    public function create()
    {
        $this->authorize('create', Report::class);

        return view('reports.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Report::class);
        // اسم افتراضي رسمي عند ترك العنوان فارغاً (مطابق لسلوك الواجهة)
        if (trim((string) $request->input('title', '')) === '') {
            $request->merge(['title' => 'التقرير اليومي — ' . $request->input('report_date', now()->toDateString())]);
        }
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'content' => ['nullable', 'string', 'max:20000'],
            'visible_to_monitors' => ['nullable', 'boolean'],
        ]);
        $validated['visible_to_monitors'] = $request->boolean('visible_to_monitors', true);

        try {
            $report = $this->reports->createDraft($request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()->route('reports.show', $report)->with('success', $this->createdMessage($report));
    }

    private function createdMessage(Report $report): string
    {
        $n = $report->notes->count();
        if ($n === 0) {
            return __('api.report_draft_empty_day');
        }
        if ($n === 1) {
            return __('api.report_draft_created_auto_one');
        }
        if ($n === 2) {
            return __('api.report_draft_created_auto_two');
        }

        return __('api.report_draft_created_auto_many', ['n' => $n]);
    }

    public function show(Request $request, Report $report, \App\Services\Localization\LocalizedPresenter $presenter)
    {
        $this->authorize('view', $report);
        // ——— Single eager load — بلا N+1 — with pivot ordering kept by collection sort
        $report->loadMissing(['author:id,name', 'notes.attachments:id,note_id,mime_type,file_size', 'notes.owner:id,name', 'revisions.editor:id,name']);
        // Batch preload in ONE go (report + notes) — single resolveStoredMany instead of two
        try {
            $allNotes = $report->notes;
            $refs = [];
            if (!empty($report->title)) $refs[] = ['type'=>'report','id'=>$report->id,'field'=>'title','text'=>$report->title];
            foreach ($allNotes as $n) {
                $refs[] = ['type'=>'note','id'=>$n->id,'field'=>'description','text'=>$n->description];
                if (!empty($n->rejection_reason)) $refs[] = ['type'=>'note','id'=>$n->id,'field'=>'rejection_reason','text'=>$n->rejection_reason];
            }
            // Preload report title + notes in one batch
            $presenter->preload($refs);
        } catch (\Throwable) {}
        try { $presenter->preloadReports([$report]); } catch (\Throwable) {}

        // للمراقب: فتح التقرير فقط للمعتمد + المنشور — reuse computed state later
        // (defer heavy isApprovedPublished until we have imageState)

        $candidates = collect();
        $candidatesTruncated = false;
        $isOwnerWriter = $request->user()->isReportWriter() && (int) $report->author_id === (int) $request->user()->id;
        if ($isOwnerWriter) {
            $day = $report->report_date->toDateString();
            // Use already-loaded notes ids — no extra query
            $attached = $report->relationLoaded('notes') ? $report->notes->pluck('id')->all() : $report->notes()->pluck('notes.id')->all();
            $candidates = Note::with('attachments:id,note_id,mime_type,file_size')
                ->select(['id', 'observed_at', 'floor_number', 'camera_number', 'description'])
                ->where('status', Note::STATUS_ACCEPTED)
                ->whereNull('general_submission_id')
                ->whereDate('observed_at', $day)
                ->whereNotIn('id', $attached ?: [0])
                ->orderBy('observed_at')->orderBy('id')->limit(50)->get();
            if ($candidates->isNotEmpty()) {
                try { $presenter->preloadNotes($candidates); } catch (\Throwable) {}
                $candidatesTruncated = $candidates->count() >= 50;
            }
        }

        // System facts — computed ONCE
        $rendering = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
        $dataBuilder = app(\App\Services\ReportPreview\ReportDataBuilder::class);
        $templateSelector = app(\App\Services\ReportPreview\ReportTemplateSelector::class);
        $systemData = $dataBuilder->systemData($report);
        $expectedTemplate = $templateSelector->keyForCount(count($systemData['observations'] ?? []));
        $imageState = $rendering->htmlState($report);
        $sheetImageState = $imageState['state'];
        $latestRender = $imageState['render'];

        // Monitor gate — after we have state (cheaper than second htmlState inside isApprovedPublished)
        if ($request->user()->isMonitor() && !$this->isApprovedPublishedFromState($report, $imageState)) {
            abort(404, __('ui.report_unavailable'));
        }

        $preview = $this->reports->composeContent($report);
        $sheet = app(\App\Services\ReportSheetService::class)->resolve($report);
        $shCfg = config('report_sheets');
        $shImg = $sheet ? app(\App\Services\ReportSheetService::class)->imageUrl($sheet['n']) : null;
        $shNotes = $report->notes;
        $fill = app(\App\Services\Ai\ReportSheetFillService::class);
        $hasFilled = $fill->hasFilled($report);
        $fillStale = $hasFilled ? $fill->isStale($report) : false;
        $viewerIsOwner = $isOwnerWriter;
        $aiEnabledForFill = (bool) config('ai.enabled', false);
        $canFill = $viewerIsOwner && $report->isDraft() && $sheet !== null;
        $aiDataEnabled = $aiEnabledForFill && trim((string) config('ai.gemini.api_key', '')) !== '';
        $fillBlockReason = null;
        if ($viewerIsOwner && $report->isDraft() && !$canFill) {
            $notesCountForSheet = $report->notes->count();
            $fillBlockReason = $notesCountForSheet === 0
                ? __('ui.fill_block_empty')
                : __('ui.fill_block_range', ['n' => $notesCountForSheet]);
        }

        $renderError = null;
        if ($viewerIsOwner && $report->isDraft() && $expectedTemplate !== null && $sheetImageState === 'none') {
            try {
                $rendering->renderSystem($request->user(), $report);
                $report->refresh();
                $report->loadMissing(['author:id,name', 'notes.attachments:id,note_id,mime_type,file_size', 'notes.owner:id,name', 'revisions.editor:id,name']);
                $systemData = $dataBuilder->systemData($report);
                $imageState = $rendering->htmlState($report);
                $sheetImageState = $imageState['state'];
                $latestRender = $imageState['render'];
                $hasFilled = $fill->hasFilled($report);
                $fillStale = $hasFilled ? $fill->isStale($report) : false;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[HTML-RENDER] auto render failed', ['report_id' => $report->id, 'error' => $e->getMessage()]);
                $renderError = 'تعذّر إنشاء المعاينة تلقائياً — استخدم زر «اعتماد التقرير» أدناه، وإن تكرر أبلغ الإدارة التقنية.';
            }
        }

        // Editor payload — reuse latestPayload once
        try {
            $latestPayload = $rendering->latestPayloadForEditor($report);
            $editorData = $latestPayload ?? $systemData;
        } catch (\Throwable) {
            $editorData = $systemData;
        }
        $currentLocale = app()->getLocale();
        if ($currentLocale === 'en' && !empty($editorData['observations'])) {
            try {
                $editorData = $presenter->reportPayload($report->id, $editorData, 'en');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[L10N] editor localization fallback', ['report_id' => $report->id, 'error' => $e->getMessage()]);
            }
        }

        // HTML preview — single htmlFor, localized via same payload (no double latestPayload)
        try {
            $htmlPreview = $rendering->htmlFor($report, $latestRender);
            if ($currentLocale === 'en' && $htmlPreview) {
                try {
                    // Reuse editor payload source already computed (latestPayload ?? systemData)
                    $srcForPreview = $latestPayload ?? $systemData;
                    $loc = $presenter->reportPayload($report->id, $srcForPreview, 'en');
                    $docLoc = app(\App\Services\Report\ReportEngine::class)->build($report, [
                        'report_number' => (string) $report->id,
                        'date' => $report->report_date ? $report->report_date->toDateString() : '',
                        'location' => (string) ($srcForPreview['location'] ?? ''),
                        'observations' => $loc['observations'],
                        'recommendations' => $loc['recommendations'],
                    ], ['locale' => 'en', 'generated_at' => '', 'generation_id' => 'show-preview']);
                    $htmlPreview = view('reports.engine.document', ['doc' => $docLoc])->render();
                } catch (\Throwable $inner) {
                    \Illuminate\Support\Facades\Log::warning('[L10N] show preview localization fallback', ['report_id' => $report->id]);
                }
            }
        } catch (\Throwable) {
            $htmlPreview = null;
        }

        $canPrint = $this->isApprovedPublishedFromState($report, $imageState);

        return view('reports.show', compact('report', 'candidates', 'candidatesTruncated', 'preview', 'sheet', 'shCfg', 'shImg', 'shNotes', 'hasFilled', 'fillStale', 'canFill', 'fillBlockReason', 'systemData', 'editorData', 'expectedTemplate', 'sheetImageState', 'latestRender', 'renderError', 'aiDataEnabled', 'htmlPreview', 'canPrint'));
    }

    private function isApprovedPublishedFromState(Report $report, array $imageState): bool
    {
        if (!$report->isPublished()) return false;
        $state = $imageState['state'] ?? 'none';
        if (in_array($state, ['system','custom'], true)) return true;
        $n = $report->notes->count();
        if ($n > (int) config('report_sheets.max_notes',7) && trim((string) $report->content) !== '') return true;
        // fallback to original logic if state none
        return $this->isApprovedPublished($report);
    }

    public function edit(Report $report)
    {
        $this->authorize('update', $report);

        return view('reports.edit', compact('report'));
    }

    public function update(Request $request, Report $report)
    {
        $this->authorize('update', $report);
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'report_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'content' => ['nullable', 'string', 'max:20000'],
            'summary' => ['nullable', 'string', 'max:20000'],
            'recommendations' => ['nullable', 'string', 'max:20000'],
            'visible_to_monitors' => ['nullable', 'boolean'],
        ]);
        if ($request->has('visible_to_monitors')) {
            $validated['visible_to_monitors'] = $request->boolean('visible_to_monitors');
        }
        try {
            $this->reports->update($request->user(), $report, $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()->route('reports.show', $report)->with('success', 'تم الحفظ');
    }

    public function destroy(Request $request, Report $report)
    {
        $this->authorize('delete', $report);
        $this->reports->delete($request->user(), $report);

        return redirect()->route('reports.index')->with('success', 'تم حذف التقرير');
    }

    public function publish(Request $request, Report $report)
    {
        $this->authorize('publish', $report);
        try {
            $this->reports->publish($request->user(), $report);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with('success', 'تم نشر التقرير');
    }

    public function unpublish(Request $request, Report $report)
    {
        $this->authorize('unpublish', $report);
        try {
            $this->reports->unpublish($request->user(), $report);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with('success', 'تم سحب النشر وعاد مسودة');
    }

    public function attach(Request $request, Report $report)
    {
        $this->authorize('attachNotes', $report);
        $validated = $request->validate([
            'note_ids' => ['required', 'array', 'min:1', 'max:500'],
            'note_ids.*' => ['integer', 'distinct', 'exists:notes,id'],
        ]);
        try {
            $fresh = $this->reports->attachNotes($request->user(), $report, $validated['note_ids']);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['general' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'تمت إضافة الملاحظات وأُعيد التركيب', 'data' => ['notes_count' => $fresh->notes->count()]]);
        }

        return back()->with('success', 'تمت إضافة الملاحظات وأُعيد التركيب');
    }

    public function detach(Request $request, Report $report, int $noteId)
    {
        $this->authorize('attachNotes', $report);
        try {
            $fresh = $this->reports->detachNote($request->user(), $report, $noteId);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['general' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'تمت الإزالة وأُعيد التركيب', 'data' => ['notes_count' => $fresh->notes->count()]]);
        }

        return back()->with('success', 'تمت الإزالة وأُعيد التركيب');
    }

    public function reorder(Request $request, Report $report)
    {
        $this->authorize('attachNotes', $report);
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct', 'exists:notes,id'],
        ]);
        try {
            $fresh = $this->reports->reorderNotes($request->user(), $report, $validated['ordered_ids']);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['general' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة الترتيب وأُعيد التركيب',
                'data' => ['ordered_ids' => $fresh->notes()->orderByPivot('order_index')->pluck('notes.id')->all()],
            ]);
        }

        return back()->with('success', 'تم إعادة الترتيب وأُعيد التركيب');
    }

    public function generate(Request $request, Report $report)
    {
        // دفاع إضافي: التوليد يحتاج حتى ~60 ثانية لرد Gemini، وحد PHP الافتراضي 30 ثانية يقتله.
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');
        $this->authorize('generateAi', $report);
        $request->validate([
            'regenerate' => ['nullable', 'boolean'],
            'confirm_overwrite_manual' => ['nullable', 'boolean'],
            'include_images' => ['nullable', 'boolean'],
        ]);
        // AJAX (smart panel) expects JSON; classic form expects redirect.
        $wantsJson = $request->expectsJson() || $request->ajax();
        $withImages = !$request->has('include_images') || $request->boolean('include_images');
        $locale = \App\Services\Localization\SourceLanguage::normalizeLocale(app()->getLocale());
        try {
            $fresh = $this->ai->generateFor(
                $request->user(), $report,
                $request->boolean('regenerate'),
                $request->boolean('confirm_overwrite_manual'),
                $withImages,
                $locale
            );
        } catch (AiException | InvalidArgumentException $e) {
            $hint = $this->aiHint($e);
            $msg = $e->getMessage() . ($hint ? ' ' . $hint : '');
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'error' => $this->aiErrorMeta($e),
                ], $this->aiHttpStatus($e));
            }

            return back()->withErrors(['general' => $msg]);
        }

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => 'تم التوليد — اعتمد الملخص والتوصيات في حقولهما ثم احفظ.',
                'data' => [
                    'ai_draft_content' => $fresh->ai_draft_content,
                    'ai_summary' => $fresh->ai_summary,
                    'ai_recommendations' => $fresh->ai_recommendations,
                    'generation_mode' => $fresh->generation_mode,
                ],
            ]);
        }

        return back()->with('success', 'تم التوليد — اعتمد الملخص والتوصيات في حقولهما ثم احفظ.');
    }

    private function aiHint(\Throwable $e): string
    {
        if ($e instanceof \App\Exceptions\Ai\AiRateLimitException) {
            if ($e->retryAfter) {
                return __('api.ai_hint_retry_seconds', ['seconds' => $e->retryAfter]);
            }

            return __('api.ai_hint_retry_later');
        }
        if ($e instanceof \App\Exceptions\Ai\AiUnavailableException) {
            return __('api.ai_hint_manual');
        }
        if ($e instanceof \App\Exceptions\Ai\AiInvalidResponseException) {
            return __('api.ai_hint_retry_once');
        }

        return '';
    }

    private function aiErrorMeta(\Throwable $e): array
    {
        $map = [
            \App\Exceptions\Ai\AiRateLimitException::class => ['AI_RATE_LIMITED', true],
            \App\Exceptions\Ai\AiAuthenticationException::class => ['AI_AUTH', false],
            \App\Exceptions\Ai\AiUnavailableException::class => ['AI_UNAVAILABLE', true],
            \App\Exceptions\Ai\AiInvalidResponseException::class => ['AI_BAD_RESPONSE', true],
        ];
        foreach ($map as $class => [$code, $retryable]) {
            if ($e instanceof $class) {
                $meta = ['code' => $code, 'retryable' => $retryable];
                if ($e instanceof \App\Exceptions\Ai\AiRateLimitException && $e->retryAfter) {
                    $meta['retry_after'] = $e->retryAfter;
                }

                return $meta;
            }
        }
        if ($e instanceof \App\Exceptions\Ai\AiDisabledException) {
            return ['code' => 'AI_DISABLED', 'retryable' => false];
        }
        if ($e instanceof \App\Exceptions\Ai\AiGenerationBusyException) {
            return ['code' => 'AI_BUSY', 'retryable' => true];
        }

        return ['code' => 'AI_VALIDATION', 'retryable' => false];
    }

    private function aiHttpStatus(\Throwable $e): int
    {
        return match (true) {
            $e instanceof \App\Exceptions\Ai\AiRateLimitException => 429,
            $e instanceof \App\Exceptions\Ai\AiAuthenticationException,
            $e instanceof \App\Exceptions\Ai\AiInvalidResponseException => 502,
            $e instanceof \App\Exceptions\Ai\AiUnavailableException => 503,
            $e instanceof \App\Exceptions\Ai\AiDisabledException => 403,
            $e instanceof \App\Exceptions\Ai\AiGenerationBusyException => 409,
            default => 422,
        };
    }

    public function print(Report $report)
    {
        // تصدير — Backend يمنع المراقب حتى مع View (صلاحية Export منفصلة).
        $this->authorize('export', $report);
        $report->load(['author', 'notes']);
        if (!$this->isApprovedPublished($report)) {
            // كان 404 مبهماً لصاحب مسودة ضغط زر الطباعة — نعيده لصفحة التقرير بشرح.
            return redirect()->route('reports.show', $report)
                ->withErrors(['general' => __('api.export_unavailable')]);
        }

        // الورقة الرسمية بحسب عدد الملاحظات (1-7)، أو التنسيق العام خارج النطاق
        $sheet = app(\App\Services\ReportSheetService::class)->resolve($report);
        $fill = app(\App\Services\Ai\ReportSheetFillService::class);
        $hasFilled = $fill->hasFilled($report);
        $fillStale = $hasFilled ? $fill->isStale($report) : false;
        $htmlRendering = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
        $sheetImageState = $htmlRendering->htmlState($report)['state'];
        $latestRender = $htmlRendering->latestFor($report);
        // نفس المسار الموحد للطباعة والمعاينة — يتبع لغة الواجهة (locale-aware)
        $htmlPreview = $this->resolveOfficialHtml($report);
        if (!$htmlPreview) {
            try {
                $htmlPreview = $htmlRendering->htmlFor($report, $latestRender);
            } catch (\Throwable) {
                $htmlPreview = null;
            }
        }

        return view('reports.print', compact('report', 'sheet', 'hasFilled', 'fillStale', 'sheetImageState', 'latestRender', 'htmlPreview'));
    }

    /**
     * توليد بيانات التقرير بالذكاء الاصطناعي (مرحلة DATA فقط — JSON منظم
     * للمراجعة في المحرر، بدون حفظ وبدون توليد صورة).
     */
    public function generateData(Request $request, Report $report)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        @ini_set('max_execution_time', '180');
        $this->authorize('fillSheet', $report);
        $wantsJson = $request->expectsJson() || $request->ajax();
        $locale = \App\Services\Localization\SourceLanguage::normalizeLocale(app()->getLocale());
        try {
            $out = app(\App\Services\Ai\ReportAiDataService::class)->generateFor($request->user(), $report, $locale);
        } catch (AiException | InvalidArgumentException $e) {
            $hint = $this->aiHint($e);
            $msg = $e->getMessage() . ($hint ? ' ' . $hint : '');
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'error' => $this->aiErrorMeta($e),
                ], $this->aiHttpStatus($e));
            }

            return back()->withErrors(['general' => $msg]);
        }

        $template = app(\App\Services\ReportPreview\ReportTemplateSelector::class)->keyForCount($out['count']);
        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => __('api.report_data_generated'),
                'data' => [
                    'observations' => $out['observations'],
                    'recommendations' => $out['recommendations'],
                    'count' => $out['count'],
                    'expected_template' => $template,
                ],
            ]);
        }

        return back()->with('success', __('api.report_data_generated'));
    }

    /**
     * الوثيقة الرسمية باللغة الحالية — نفس المحرك (FinalReportData → Engine → HTML).
     * عرض فقط: لا حفظ، لا مساس بالـrenders المعتمدة ولا بالبيانات الأصلية.
     * locale → Localized FinalReportData → Existing Report Engine (ثنائي الاتجاه).
     * فشل الترجمة → هيكل اللغة المطلوبة + نص المصدر (fallback آمن، بلا كسر).
     * الاستجابة presentation فقط — بلا تفاصيل تقنية.
     */
    public function localized(Request $request, Report $report)
    {
        $this->authorize('view', $report);
        $report->load(['author', 'notes']);
        if ($request->user()->isMonitor() && !$this->isApprovedPublished($report)) {
            return response()->json(['ok' => false, 'message' => __('ui.report_unavailable')], 404);
        }

        $locale = \App\Services\Localization\SourceLanguage::normalizeLocale($request->query('locale', app()->getLocale()));

        // المصدر: آخر اعتماد (custom) أو بيانات النظام — نفس ما تعرضه الصفحة.
        $rendering = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
        try {
            $source = $rendering->latestPayloadForEditor($report)
                ?? app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($report);
        } catch (\Throwable) {
            $source = app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($report);
        }
        $observations = array_values((array) ($source['observations'] ?? []));
        $recommendations = trim((string) ($source['recommendations'] ?? ''));
        if ($observations === [] && $recommendations === '') {
            return response()->json(['ok' => false, 'message' => __('ui.no_approved_data')], 404);
        }

        // Localized FinalReportData عبر الـResolver المركزي (نفس المحرك يستقبلها).
        $localized = app(\App\Services\Localization\LocalizedPresenter::class)
            ->reportPayload($report->id, ['observations' => $observations, 'recommendations' => $recommendations], $locale);

        try {
            $doc = app(\App\Services\Report\ReportEngine::class)->build($report, [
                'report_number' => (string) $report->id,
                'date' => $report->report_date ? $report->report_date->toDateString() : '',
                'location' => (string) ($source['location'] ?? ''),
                'observations' => $localized['observations'],
                'recommendations' => $localized['recommendations'],
            ], ['locale' => $locale, 'generated_at' => '', 'generation_id' => 'localized-preview']);
            $html = view('reports.engine.document', ['doc' => $doc])->render();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[L10N] localized report build failed', ['report_id' => $report->id, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => __('ui.localized_unavailable')], 500);
        }

        return response()->json(['ok' => true, 'locale' => $locale, 'html' => $html]);
    }

    /**
     * توليد/تحديث المعاينة (مرحلة APPROVE): البيانات النهائية الحالية
     * + القالب الحتمي من السيرفر → حفظ FinalReportData → HTML جديد.
     * لا AI صور هنا إطلاقاً — Gemini (نص فقط) يبقى في generate-data.
     * لا GD — النص HTML حقيقي RTL.
     */
    public function render(Request $request, Report $report)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');
        $this->authorize('fillSheet', $report);
        $validated = $request->validate([
            'data_version' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'request_uid' => ['nullable', 'string', 'max:64'],
            'data' => ['required', 'array'],
            'data.location' => ['nullable', 'string', 'max:255'],
            'data.observations' => ['required', 'array', 'min:1', 'max:100'],
            'data.observations.*' => ['required', 'string', 'min:1', 'max:2000'],
            'data.recommendations' => ['nullable', 'string', 'max:2000'],
        ]);
        $wantsJson = $request->expectsJson() || $request->ajax();
        try {
            $meta = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class)->render(
                $request->user(),
                $report,
                $validated['data'],
                (int) ($validated['data_version'] ?? 1),
                $validated['request_uid'] ?? null
            );
        } catch (AiException | InvalidArgumentException $e) {
            $hint = $this->aiHint($e);
            $msg = $e->getMessage() . ($hint ? ' ' . $hint : '');
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'error' => $this->aiErrorMeta($e),
                ], $this->aiHttpStatus($e));
            }

            return back()->withErrors(['general' => $msg]);
        } catch (\Throwable $e) {
            // فشل محرك HTML المحلي — خطأ صريح قابل لإعادة المحاولة، لا فشل صامت.
            \Illuminate\Support\Facades\Log::error('[HTML-RENDER] local engine failed', ['report_id' => $report->id, 'error' => $e->getMessage()]);
            $msg = __('api.render_failed');
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'error' => ['code' => 'RENDER_FAILED', 'retryable' => true],
                ], 500);
            }

            return back()->withErrors(['general' => $msg]);
        }

        if ($wantsJson) {
            return response()->json(['success' => true, 'message' => $meta['cached'] ? __('api.report_render_cached') : __('api.report_render_approved'), 'data' => $meta]);
        }

        return back()->with('success', __('api.report_render_approved'));
    }

    /**
     * تعبئة الورقة الرسمية (زر يدوي — يرسم القالب المختار بحسب عدد الملاحظات
     * مع بيانات النظام ويحفظ الصورة المعبأة). الرسم محلي بالكامل.
     */
    public function fillSheet(Request $request, Report $report)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(240);
        }
        @ini_set('max_execution_time', '240');
        $this->authorize('fillSheet', $report);
        $wantsJson = $request->expectsJson() || $request->ajax();
        try {
            $fresh = app(\App\Services\Ai\ReportSheetFillService::class)->fill($request->user(), $report);
        } catch (AiException | InvalidArgumentException $e) {
            $hint = $this->aiHint($e);
            $msg = $e->getMessage() . ($hint ? ' ' . $hint : '');
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'error' => $this->aiErrorMeta($e),
                ], $this->aiHttpStatus($e));
            }

            return back()->withErrors(['general' => $msg]);
        }

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => 'تمت تعبئة الورقة الرسمية — راجع الصورة المعبأة في المعاينة قبل الطباعة.',
                'data' => [
                    'image_url' => route('reports.filled-sheet.image', $fresh),
                    'generated_at' => $fresh->ai_sheet_generated_at?->format('Y-m-d H:i'),
                ],
            ]);
        }

        return back()->with('success', __('api.sheet_filled'));
    }

    public function destroyFilledSheet(Request $request, Report $report)
    {
        $this->authorize('fillSheet', $report);
        app(\App\Services\Ai\ReportSheetFillService::class)->clear($request->user(), $report);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => __('api.sheet_deleted')]);
        }

        return back()->with('success', __('api.sheet_deleted'));
    }

    /**
     * بث الصورة المعبأة المحفوظة (توافق قديم — التقارير الجديدة HTML بلا صورة).
     */
    public function filledSheetImage(Request $request, Report $report)
    {
        // ملف تصدير — يمنع Backend التنزيل عن المراقب (Export فقط).
        $this->authorize('export', $report);
        if (!$this->isApprovedPublished($report)) {
            abort(404);
        }
        $path = (string) $report->ai_sheet_image_path;
        if ($path === '' || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            // لا صورة — التقرير HTML حديث. أعد التوجيه لمعاينة HTML الموحدة.
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                'success' => false,
                'message' => __('api.no_sheet_image'),
                'html_url' => route('reports.sheet-html', $report)
            ], 404);
            }
            abort(404);
        }
        $full = \Illuminate\Support\Facades\Storage::disk('public')->path($path);

        return response()->file($full, [
            'Content-Type' => str_ends_with(strtolower($path), '.jpg') || str_ends_with(strtolower($path), '.jpeg') ? 'image/jpeg' : 'image/png',
            'Cache-Control' => 'private, max-age=3600, must-revalidate',
        ]);
    }

    /**
     * معاينة HTML الموحدة — نفس القالب المستخدم في الطباعة (Preview ≈ Print).
     * يعيد HTML جاهزاً للحقن المباشر في #paper-preview-html.
     */
    public function sheetHtml(Request $request, Report $report)
    {
        $this->authorize('view', $report);
        $report->load(['author', 'notes']);
        // للمراقب: فقط المعتمد + المنشور يُعرض (approved + published).
        if ($request->user()->isMonitor() && !$this->isApprovedPublished($report)) {
            abort(404, __('ui.report_unavailable'));
        }
        $html = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class)->htmlFor($report);
        if (!$html) {
            abort(404, 'لا توجد معاينة HTML بعد — اعتمد التقرير أولاً.');
        }
        if ($request->expectsJson() || $request->ajax() || $request->query('fragment') === '1') {
            return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        return view('reports.sheet_page', compact('report', 'html'));
    }

    /**
     * بث صورة الورقة الرسمية (1-7) من القرص مباشرة
     * لتفادي مشاكل ترميز المسارات العربية على بعض الخوادم.
     * قوالب فارغة بلا بيانات تقرير — تبقى متاحة للمحرر (لا تحوي بيانات).
     */
    public function sheet(Request $request, int $n)
    {
        if (!$request->user()) {
            abort(403);
        }
        $this->authorize('viewAny', \App\Models\Report::class);
        if ($n < 1 || $n > (int) config('report_sheets.max_notes', 7)) {
            abort(404);
        }
        $path = app(\App\Services\ReportSheetService::class)->imagePath($n);
        if (!is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }

    /**
     * ── Export/Preview للتقارير فقط (داخل Tab التقارير) ──
     * Preview رسمي Inline بلا أي زر تنزيل — نفس Official HTML/CSS.
     * التحقق من الصلاحية في كل Request (Backend)، بلا روابط ملفات مكشوفة.
     */
    public function preview(Request $request, Report $report)
    {
        $this->authorize('preview', $report);
        $report->load(['author', 'notes']);
        if (!$this->isApprovedPublished($report)) {
            return redirect()->route('reports.show', $report)
                ->withErrors(['general' => __('api.preview_unavailable')]);
        }
        $html = $this->resolveOfficialHtml($report);
        if (!$html) {
            return redirect()->route('reports.show', $report)
                ->withErrors(['general' => __('api.no_approved_version')]);
        }
        $canExport = $request->user()->can('export', $report);

        return response()
            ->view('reports.preview', compact('report', 'html', 'canExport'))
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'private, no-store, must-revalidate')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * تصدير PDF — نفس التقرير الرسمي (FinalReportData → Engine → HTML/CSS).
     * Backend يمنع المراقب (403) حتى لو استدعى URL مباشرة. عرض للطباعة
     * عبر المتصفح (Print → Save as PDF) بلا ملف مكشوف على السيرفر.
     */
    public function exportPdf(Request $request, Report $report)
    {
        $this->authorize('export', $report);
        $report->load(['author', 'notes']);
        if (!$this->isApprovedPublished($report)) {
            // كان 404 مبهماً لصاحب مسودة ضغط زر الطباعة — نعيده لصفحة التقرير بشرح.
            return redirect()->route('reports.show', $report)
                ->withErrors(['general' => __('api.export_unavailable')]);
        }
        $html = $this->resolveOfficialHtml($report);
        if (!$html) {
            return redirect()->route('reports.show', $report)
                ->withErrors(['general' => __('api.no_approved_version')]);
        }

        return response()
            ->view('reports.export-pdf', compact('report', 'html'))
            ->header('Content-Disposition', 'inline; filename="report-'.$report->id.'.html"')
            ->header('Cache-Control', 'private, no-store, must-revalidate')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * تصدير كصورة — نفس التقرير الرسمي، يُحمَّل من المتصفح (Canvas)
     * فقط لمن يملك Export. Backend يمنع المراقب (403) على الـURL نفسه.
     */
    public function exportImage(Request $request, Report $report)
    {
        $this->authorize('export', $report);
        $report->load(['author', 'notes']);
        if (!$this->isApprovedPublished($report)) {
            // كان 404 مبهماً لصاحب مسودة ضغط زر الطباعة — نعيده لصفحة التقرير بشرح.
            return redirect()->route('reports.show', $report)
                ->withErrors(['general' => __('api.export_unavailable')]);
        }
        $html = $this->resolveOfficialHtml($report);
        if (!$html) {
            return redirect()->route('reports.show', $report)
                ->withErrors(['general' => __('api.no_approved_version')]);
        }

        return response()
            ->view('reports.export-image', compact('report', 'html'))
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'private, no-store, must-revalidate')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * المعتمد + المنشور فقط:
     * - 1..7 ملاحظات: حالة HTML system/custom (اعتماد حديث غير قديم).
     * - خارج 1..7: محتوى مركب غير فارغ (لا محرر اعتماد له).
     */
    private function isApprovedPublished(Report $report): bool
    {
        if (!$report->isPublished()) {
            return false;
        }
        $report->loadMissing(['author', 'notes']);
        try {
            $svc = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
            $state = $svc->htmlState($report)['state'] ?? 'none';
            if (in_array($state, ['system', 'custom'], true)) {
                return true;
            }
        } catch (\Throwable) {
        }
        $n = $report->notes->count();
        if ($n > (int) config('report_sheets.max_notes', 7) && trim((string) $report->content) !== '') {
            return true;
        }

        return false;
    }

    /**
     * نفس Official HTML/CSS — FinalReportData → Engine → document.blade.
     * لا تغيير في Engine: نعيد استخدام htmlFor، مع fallback خارج 1..7
     * يبني من بيانات النظام عبر Engine مباشرة (للعرض فقط).
     */
    private function resolveOfficialHtml(Report $report): ?string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        // مسار localized أولاً (en → Localized FinalReportData → Engine)
        if ($locale === 'en') {
            try {
                $svc = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
                $src = $svc->latestPayloadForEditor($report) ?? app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($report);
                if (!empty($src['observations']) || trim((string) ($src['recommendations'] ?? '')) !== '') {
                    $loc = app(\App\Services\Localization\LocalizedPresenter::class)->reportPayload($report->id, $src, 'en');
                    $doc = app(\App\Services\Report\ReportEngine::class)->build($report, [
                        'report_number' => (string) $report->id,
                        'date' => $report->report_date ? $report->report_date->toDateString() : '',
                        'location' => (string) ($src['location'] ?? ''),
                        'observations' => $loc['observations'],
                        'recommendations' => $loc['recommendations'],
                    ], ['locale' => 'en', 'generated_at' => '', 'generation_id' => 'official-en']);
                    return view('reports.engine.document', ['doc' => $doc])->render();
                }
            } catch (\Throwable) {}
        }
        try {
            $svc = app(\App\Services\ReportPreview\ReportHtmlRenderingService::class);
            $html = $svc->htmlFor($report, $svc->latestFor($report));
            if ($html) {
                return $html;
            }
        } catch (\Throwable) {
        }
        // خارج 1..7: بناء رسمي للعرض من بيانات النظام (نفس Engine + Blade).
        try {
            $n = $report->notes->count();
            if ($n > (int) config('report_sheets.max_notes', 7)) {
                $system = app(\App\Services\ReportPreview\ReportDataBuilder::class)->systemData($report);
                $observations = array_values($system['observations'] ?? []);
                $recommendations = (string) ($system['recommendations'] ?? '');
                if ($locale === 'en') {
                    try {
                        $loc = app(\App\Services\Localization\LocalizedPresenter::class)->reportPayload($report->id, ['observations' => $observations, 'recommendations' => $recommendations], 'en');
                        $observations = $loc['observations'];
                        $recommendations = $loc['recommendations'];
                    } catch (\Throwable) {}
                }
                $doc = app(\App\Services\Report\ReportEngine::class)->build($report, [
                    'report_number' => (string) $report->id,
                    'date' => $report->report_date ? $report->report_date->toDateString() : '',
                    'location' => (string) ($system['location'] ?? ''),
                    'observations' => $observations,
                    'recommendations' => $recommendations,
                ], ['locale' => $locale, 'generated_at' => '', 'generation_id' => '']);
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
