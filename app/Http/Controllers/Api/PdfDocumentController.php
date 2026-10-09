<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Models\PdfDocument;
use App\Models\Report;
use App\Services\Pdf\PdfDocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * تقديم مستندات PDF المولّدة على السيرفر — لا توليد في العميل إطلاقاً.
 * - ready + مطابق للبصمة → بايتات PDF.
 * - توليد جارٍ → 202 مع الحالة (العميل يعيد المحاولة).
 * - غير قابل للنشر (تقرير مسودة) → 422.
 */
class PdfDocumentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private PdfDocumentService $pdf) {}


    public function notePdf(Request $request, Note $note): Response
    {
        $this->authorize('view', $note);

        return $this->serve($note, (string) $request->query('locale', 'ar'), 'note-'.$note->id.'.pdf');
    }

    public function noteStatus(Request $request, Note $note): Response
    {
        $this->authorize('view', $note);

        return $this->status($note, (string) $request->query('locale', 'ar'));
    }

    public function noteZip(Note $note): BinaryFileResponse
    {
        $this->authorize('view', $note);

        return $this->zip($note, 'note-'.$note->id.'-files.zip');
    }


    public function submissionPdf(Request $request, GeneralSubmission $submission): Response
    {
        $this->authorize('view', $submission);

        return $this->serve($submission, (string) $request->query('locale', 'ar'), 'submission-'.$submission->id.'.pdf');
    }

    public function submissionStatus(Request $request, GeneralSubmission $submission): Response
    {
        $this->authorize('view', $submission);

        return $this->status($submission, (string) $request->query('locale', 'ar'));
    }

    public function submissionZip(GeneralSubmission $submission): BinaryFileResponse
    {
        $this->authorize('view', $submission);

        return $this->zip($submission, 'submission-'.$submission->id.'-files.zip');
    }


    public function reportPdf(Request $request, Report $report): Response
    {
        $this->authorize('export', $report);

        return $this->serve($report, (string) $request->query('locale', 'ar'), 'report-'.$report->id.'.pdf');
    }

    public function reportStatus(Request $request, Report $report): Response
    {
        $this->authorize('export', $report);

        return $this->status($report, (string) $request->query('locale', 'ar'));
    }

    public function reportZip(Report $report): BinaryFileResponse
    {
        $this->authorize('export', $report);

        return $this->zip($report, 'report-'.$report->id.'-files.zip');
    }


    private function serve(Model $model, string $locale, string $filename): Response
    {
        try {
            $row = $this->pdf->ensureQueued($model, $locale);
        } catch (\RuntimeException $e) {
            return $this->rejection($e);
        }

        if ($row->status === PdfDocument::STATUS_FAILED && ! $row->fileExists()) {
            return response()->json([
                'success' => false,
                'status' => PdfDocument::STATUS_FAILED,
                'ready' => false,
                'message' => __('api.export_unavailable'),
            ], 500);
        }

        if (! $row->isReady()) {
            return response()->json([
                'success' => true,
                'status' => $row->status,
                'ready' => false,
                'generating_since' => $row->generating_since?->toIso8601String(),
            ], 202)->header('Retry-After', '3');
        }

        try {
            $bytes = $this->pdf->pdfBytes($row);
        } catch (\RuntimeException) {
            return response()->json(['success' => false, 'message' => __('api.export_unavailable')], 500);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => strlen($bytes),
            'Cache-Control' => 'private, no-store, must-revalidate',
        ]);
    }

    /**
     * حالة التوليد — إطلاق التوليد عند الحاجة بدل انتظار watcher لا وجود له.
     * تستدعي نفس ensureQueued التي يستخدمها /pdf، فالاستطلاع يُطلق التوليد
     * ولا ينتظره. readiness تُشتق من مطابقة البصمة ووجود الملف لا من status فقط،
     * حتى لا يُقال "ready" لنسخة قديمة_content_hash مختلفة.
     */
    private function status(Model $model, string $locale): Response
    {
        $locale = $this->pdf->normalizeLocale($locale);

        try {
            $row = $this->pdf->ensureQueued($model, $locale);
        } catch (\RuntimeException $e) {
            return $this->rejection($e);
        }

        $current = $this->pdf->contentHash($model, $locale);
        $ready = $row->isReady() && $row->content_hash === $current && $row->fileExists();

        return response()->json([
            'success' => true,
            'status' => $ready ? PdfDocument::STATUS_READY : $row->status,
            'ready' => (bool) $ready,
            'generated_at' => $ready ? $row->generated_at?->toIso8601String() : null,
            'generating_since' => $row->generating_since?->toIso8601String(),
            'file_size' => $ready ? $row->file_size : null,
        ]);
    }

    /**
     * سبب الرفض = 422 (غير قابل للتوليد) أو 500 (عطل).
     *
     * كان التمييز بينهما مكرراً في ثلاثة مواضع؛ صار هنا مرة واحدة.
     */
    private function rejection(\RuntimeException $e): Response
    {
        if ($e->getMessage() === 'export_unavailable') {
            return response()->json(['success' => false, 'message' => __('api.export_unavailable')], 422);
        }

        return response()->json(['success' => false, 'message' => __('api.export_unavailable')], 500);
    }

    private function zip(Model $model, string $filename): BinaryFileResponse
    {
        try {
            $path = $this->pdf->zipOriginals($model);
        } catch (\RuntimeException $e) {
            abort($e->getMessage() === 'no_files' ? 404 : 500, $e->getMessage());
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }
}
