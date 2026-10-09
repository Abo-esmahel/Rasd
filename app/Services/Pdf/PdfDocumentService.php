<?php

namespace App\Services\Pdf;

use App\Jobs\GeneratePdfDocument;
use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Models\PdfDocument;
use App\Models\Report;
use App\Services\ReportPreview\ReportHtmlRenderingService;
use App\Support\LocalBinary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * توليد وتخزين مستندات PDF على السيرفر حصراً — بنسخ مربوطة ببصمة المحتوى.
 *
 * - صف واحد لكل (كيان، لغة)، والحقل content_hash يمثل المحتوى الذي بُني عليه الملف.
 * - أي تغيّر في المحتوى أو المرفقات يجعل البصمة مختلفة → يُعاد التوليد، ولا تُقدَّم نسخة قديمة أبداً.
 * - التحديثات المتزامنة: قفل صف + حالة generating بمهلة 5 دقائق (يُستولى على العالق).
 * - الفشل لا يكسر طلب الحفظ (try/catch في refresh) ويُسجَّل مع عدّ المحاولات.
 */
class PdfDocumentService
{
    /**
     * توليد PDF داخل الطلب نفسه بدل الطابور.
     *
     * هذا لتفادي انتظار الطابور في الاختبارات فقط. في التشغيل الحقيقي
     * التوليد job في الطابور — انظر ensureQueued().
     */
    public static bool $syncOnWrite = false;

    /** ما استهلكه تضمين المرفقات من ميزانية base64 في هذه الوثيقة. */
    private int $inlineBytesSpent = 0;

    /** ميزانية base64 ل.document واحد — أقل من MAX_HTML_BYTES بهامش أمان. */
    private function inlineBudgetLeft(): int
    {
        $budget = (int) config('pdf.inline_total_max_kb', 8192) * 1024;

        return max(0, $budget - $this->inlineBytesSpent);
    }

    /** صورة أكبر من ميزانية المرفق المفرد — لا تُضمَّن، ويُعلَن ذلك. */
    private function isImageOversized(string $path): bool
    {
        $size = @filesize($path);

        return $size !== false && $size > (int) config('pdf.inline_original_max_kb', 3072) * 1024;
    }

    public const DEFAULT_LOCALE = 'ar';
    public const SUPPORTED_LOCALES = ['ar', 'en'];
    public const STALE_MINUTES = 5;
    public const MAX_HTML_BYTES = 15 * 1024 * 1024;

    public function typeOf(Model $model): string
    {
        return match (true) {
            $model instanceof Note => 'note',
            $model instanceof GeneralSubmission => 'submission',
            $model instanceof Report => 'report',
            default => throw new \InvalidArgumentException('Unsupported model for PDF: '.get_class($model)),
        };
    }

    public function normalizeLocale(?string $locale): string
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : self::DEFAULT_LOCALE;
    }

    /** بصمة قانونية للمحتوى: أي تغيير يستلزم PDF جديداً. */
    public function contentHash(Model $model, string $locale): string
    {
        $locale = $this->normalizeLocale($locale);
        $parts = [$this->typeOf($model), $model->getKey(), $locale, (string) $model->updated_at];

        if ($model instanceof Report) {
            $parts[] = (string) ($model->title ?? '');
            $parts[] = (string) ($model->content ?? '');
            $parts[] = (string) ($model->summary ?? '');
            $parts[] = (string) ($model->status ?? '');
            $parts[] = (string) ($model->report_date ?? '');
            try {
                $svc = app(ReportHtmlRenderingService::class);
                $latest = $svc->latestFor($model);
                $parts[] = $latest ? $latest->getKey().':'.((string) $latest->updated_at) : 'none';
            } catch (\Throwable) {
                $parts[] = 'unknown-render';
            }
            $model->loadMissing(['notes' => fn ($q) => $q->orderBy('notes.id')]);
            foreach ($model->notes as $n) {
                $parts[] = 'note:'.$n->getKey().':'.((string) $n->updated_at);
            }
        } else {
            foreach (['description', 'status', 'camera_number', 'floor_number', 'observed_at', 'observed_end_at', 'rejection_reason'] as $f) {
                $parts[] = $f.'='.(string) ($model->{$f} ?? '');
            }
            $model->loadMissing(['attachments' => fn ($q) => $q->orderBy('id')]);
            foreach ($model->attachments as $a) {
                $parts[] = implode('|', [
                    'att', $a->getKey(), $a->file_path, $a->mime_type, $a->file_size, (string) $a->updated_at,
                ]);
            }
        }

        return sha1(implode("\n", $parts));
    }

    /**
     * المسار الكامل: يُرجع الصف، ويولّد synchronously عند الحاجة.
     * إن كان توليد آخر جارياً وحديثاً يُعاد الصف كما هو (202 للعميل).
     *
     * هذا يُستدعى من عامل الطابور (GeneratePdfDocument) ومن الاختبارات فقط —
     * أي من مسار HTTP لا يناديه، بل ensureQueued().
     */
    public function resolve(Model $model, ?string $locale = null): PdfDocument
    {
        $locale = $this->normalizeLocale($locale);
        $hash = $this->contentHash($model, $locale);

        $row = DB::transaction(function () use ($model, $locale, $hash) {
            /** @var PdfDocument|null $row */
            $row = PdfDocument::where('documentable_type', $model::class)
                ->where('documentable_id', $model->getKey())
                ->where('locale', $locale)
                ->lockForUpdate()
                ->first();

            if ($row && $row->isReady() && $row->content_hash === $hash && $row->fileExists()) {
                return $row;
            }
            if ($row && $row->isGeneratingFresh() && $row->content_hash === $hash) {
                return $row;
            }
            if (! $row) {
                $row = new PdfDocument([
                    'documentable_type' => $model::class,
                    'documentable_id' => $model->getKey(),
                    'locale' => $locale,
                    'status' => PdfDocument::STATUS_PENDING,
                    'attempts' => 0,
                ]);
            }
            $row->fill([
                'content_hash' => $hash,
                'status' => PdfDocument::STATUS_GENERATING,
                'error' => null,
                'generating_since' => now(),
            ]);
            $row->attempts++;
            $row->save();

            return $row;
        });

        if ($row->isReady() && $row->content_hash === $hash && $row->fileExists()) {
            return $row->fresh() ?? $row;
        }

        $this->generate($row, $model, $locale, $hash);

        return $row->fresh() ?? $row;
    }

    /**
     * يُستدعى من الـObservers عند الكتابة: إبطال فوري، ثم توليد.
     *
     * الإبطال فوري ودائم (الصف يصبح pending) أما التوليد فيُمرَّر للطابور
     * حتى لا يحجب طلب الحفظ نفسه بتوليد Chrome.
     */
    public function refresh(Model $model): void
    {
        try {
            PdfDocument::where('documentable_type', $model::class)
                ->where('documentable_id', $model->getKey())
                ->update(['status' => PdfDocument::STATUS_PENDING, 'generating_since' => null]);

            if (static::$syncOnWrite) {
                $this->resolve($model, self::DEFAULT_LOCALE);

                return;
            }

            $this->ensureQueued($model, self::DEFAULT_LOCALE);
        } catch (\Throwable $e) {
            Log::warning('[PDF] refresh failed (isolated)', [
                'type' => $this->safeType($model),
                'id' => $model->getKey(),
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
        }
    }

    /**
     * يضمن وجود PDF مطابق للمحتوى دون توليده داخل الطلب.
     *
     * يُستدعى من مسار HTTP (/pdf و/pdf-status). إن كان الصف جاهزاً ومطابقاً
     * للبصمة يُعاد كما هو بلا عمل. وإلا يُوسَم generating ويُرمى job واحد
     * للطابور، فيردّ الطلب بـ202 والعميل يستطلع. لا يُحجب أي طلب بتوليد.
     *
     * الاستثناء الوحيد: إن لم يكن هناك عامل طابور يعمل، فلن يلتقط أحد المهمة
     * أبداً، والصف سيمرّ على حالة generating بلا نهاية — فيتولّى هذا الطلب
     * التوليد بنفسه بعد مهلة سماح قصيرة. انظر config/pdf.php.
     */
    public function ensureQueued(Model $model, ?string $locale = null): PdfDocument
    {
        $locale = $this->normalizeLocale($locale);

        $this->assertExportable($model);

        $hash = $this->contentHash($model, $locale);

        $shouldDispatch = DB::transaction(function () use ($model, $locale, $hash) {
            /** @var PdfDocument|null $row */
            $row = PdfDocument::where('documentable_type', $model::class)
                ->where('documentable_id', $model->getKey())
                ->where('locale', $locale)
                ->lockForUpdate()
                ->first();

            if ($row && $row->isReady() && $row->content_hash === $hash && $row->fileExists()) {
                return false;
            }
            if ($row && $row->isGeneratingFresh() && $row->content_hash === $hash) {
                return false;
            }

            if (! $row) {
                $row = new PdfDocument([
                    'documentable_type' => $model::class,
                    'documentable_id' => $model->getKey(),
                    'locale' => $locale,
                    'status' => PdfDocument::STATUS_PENDING,
                    'attempts' => 0,
                ]);
            }
            $row->fill([
                'content_hash' => $hash,
                'status' => PdfDocument::STATUS_GENERATING,
                'error' => null,
                'generating_since' => now(),
            ]);
            $row->attempts++;
            $row->save();

            return true;
        });

        if ($this->queueEnabled()) {
            if ($shouldDispatch) {
                try {
                    GeneratePdfDocument::dispatch($model::class, (int) $model->getKey(), $locale);
                } catch (\Throwable $e) {
                    Log::warning('[PDF] dispatch failed, generating inline', [
                        'type' => $this->safeType($model),
                        'id' => $model->getKey(),
                        'error' => mb_substr($e->getMessage(), 0, 200),
                    ]);

                    return $this->resolve($model, $locale);
                }
            }

            $current = PdfDocument::where('documentable_type', $model::class)
                ->where('documentable_id', $model->getKey())
                ->where('locale', $locale)
                ->first() ?? throw new \RuntimeException('PDF row vanished');

            if ($current->isGeneratingStuck($this->graceSeconds())) {
                $inline = $this->generateWhenIdle($model, $locale);
                if ($inline !== null) {
                    return $inline;
                }
            }

            return $current;
        }

        return $this->resolve($model, $locale);
    }

    /** هل يُولَّد PDF في عامل الطابور؟ */
    public function queueEnabled(): bool
    {
        return (bool) config('pdf.queue', true);
    }

    protected function graceSeconds(): int
    {
        return (int) config('pdf.queue_grace_seconds', 10);
    }

    /**
     * توليد داخل الطلب حين لا يكون هناك عامل — دون أن يلتقطه عامل متأخر.
     *
     * يُقفل نفس قفل GeneratePdfDocument: فإن بدأ عامل توليداً بين الفحص هنا
     * وأخذ القفل، نُعيد الصف كما هو (202) ولا نضاعف عمل Chrome.
     * يُرجع null عند انتهاك القفل، والصف عند النجاح أو الفشل.
     */
    protected function generateWhenIdle(Model $model, string $locale): ?PdfDocument
    {
        $lock = \Illuminate\Support\Facades\Cache::lock(
            'pdf-generate-'.$model::class.'-'.$model->getKey().'-'.$locale,
            200,
        );

        if (! $lock->get()) {
            return null;
        }

        try {
            Log::info('[PDF] no worker picked the job up, generating inline', [
                'type' => $this->safeType($model),
                'id' => $model->getKey(),
                'locale' => $locale,
            ]);

            return $this->resolve($model, $locale);
        } finally {
            optional($lock)->release();
        }
    }

    /** حذف المستند المخزن عند حذف الكيان نهائياً. */
    public function deleteFor(Model $model): void
    {
        try {
            $rows = PdfDocument::where('documentable_type', $model::class)
                ->where('documentable_id', $model->getKey())
                ->get();
            foreach ($rows as $row) {
                if ($row->file_path) {
                    Storage::disk('local')->delete($row->file_path);
                }
                $row->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('[PDF] delete failed', ['error' => mb_substr($e->getMessage(), 0, 150)]);
        }
    }

    public function pdfBytes(PdfDocument $row): string
    {
        if (! $row->isReady() || ! $row->fileExists()) {
            throw new \RuntimeException('PDF not ready');
        }
        $bytes = Storage::disk('local')->get($row->file_path);
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('PDF file unreadable');
        }

        return $bytes;
    }

    protected function generate(PdfDocument $row, Model $model, string $locale, string $hash): void
    {
        try {
            $html = $this->buildHtml($model, $locale);
            $bytes = $this->htmlToPdf($html);
            $type = $this->typeOf($model);
            $path = "pdfs/{$type}/{$model->getKey()}/".substr($hash, 0, 12).'.pdf';
            Storage::disk('local')->put($path, $bytes);

            DB::transaction(function () use ($row, $model, $locale, $hash, $path, $bytes) {
                /** @var PdfDocument $fresh */
                $fresh = PdfDocument::whereKey($row->id)->lockForUpdate()->first();
                if (! $fresh) {
                    Storage::disk('local')->delete($path);

                    return;
                }
                $current = $model->exists
                    ? $this->contentHash($model->fresh() ?? $model, $locale)
                    : null;
                if ($current === null) {
                    Storage::disk('local')->delete($path);
                    $fresh->delete();

                    return;
                }
                if ($current !== $hash) {
                    Storage::disk('local')->delete($path);
                    $fresh->fill([
                        'status' => PdfDocument::STATUS_PENDING,
                        'generating_since' => null,
                    ])->save();

                    return;
                }
                if ($fresh->file_path && $fresh->file_path !== $path) {
                    Storage::disk('local')->delete($fresh->file_path);
                }
                $fresh->fill([
                    'content_hash' => $hash,
                    'status' => PdfDocument::STATUS_READY,
                    'file_path' => $path,
                    'file_size' => strlen($bytes),
                    'error' => null,
                    'generating_since' => null,
                    'generated_at' => now(),
                ])->save();

                $this->pruneStaleFiles($model, $path);
            });
        } catch (\Throwable $e) {
            PdfDocument::whereKey($row->id)->update([
                'status' => PdfDocument::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 500),
                'generating_since' => null,
            ]);
            Log::warning('[PDF] generate failed', [
                'type' => $this->safeType($model),
                'id' => $model->getKey(),
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        }
    }

    /**
     * ينظّف ملفات PDF اليتيمة لكيان معيّن — دون المساس بملفات اللغات الأخرى.
     *
     * لماذا: المسار مشتق من بصمة المحتوى، فكل تغيير ينتج ملفاً جديداً، والحذف
     * القديم يغطي `file_path` المسجّل فقط — فتبقى ملفات لا يشير إليها أي صف
     * (تراكم، وكل pdf 1MB+).
     *
     * تحذار: المجلد `pdfs/{type}/{id}/` مشترك بين اللغات ar/en، فحذف «كل
     * ما عدا الحالي» كان يمسح PDF اللغة الأخرى عند أول توليد. لذلك نلمس فقط
     * ما لا يشير إليه أي صف PdfDocument لهذا الكيان (أياً كانت لغته).
     */
    protected function pruneStaleFiles(Model $model, string $keepPath): void
    {
        try {
            $dir = dirname($keepPath);

            $referenced = PdfDocument::where('documentable_type', $model::class)
                ->where('documentable_id', $model->getKey())
                ->whereNotNull('file_path')
                ->pluck('file_path')
                ->all();

            foreach (Storage::disk('local')->files($dir) as $file) {
                if ($file === $keepPath || in_array($file, $referenced, true)) {
                    continue;
                }
                Storage::disk('local')->delete($file);
            }
        } catch (\Throwable $e) {
            Log::warning('[PDF] prune stale files failed', [
                'keep' => $keepPath,
                'error' => mb_substr($e->getMessage(), 0, 150),
            ]);
        }
    }

    /** HTML الكامل حسب النوع — التقارير تُعاد فيها محركات النظام الحالية. */
    public function buildHtml(Model $model, string $locale): string
    {
        $locale = $this->normalizeLocale($locale);
        if ($model instanceof Report) {
            return $this->reportHtml($model, $locale);
        }

        return $this->noteLikeHtml($model, $locale);
    }

    /**
     * حراسة التصدير — رخيصة (استعلامات فقط، بلا Chrome) وتُنفَّذ قبل الطابور.
     *
     * لولاها لأصبح رفض "تقرير غير منشور" خطأً في الخلفية: الطلب يرد 202،
     * والعامل يفشل، فيرى العميل "تعذّرت الطباعة" بدل سبب حقيقي مفهوم.
     */
    public function assertExportable(Model $model): void
    {
        if (! $model instanceof Report) {
            return;
        }

        $model->loadMissing(['author', 'notes']);
        if (! $model->isPublished()) {
            throw new \RuntimeException('export_unavailable');
        }

        $svc = app(ReportHtmlRenderingService::class);
        $state = $svc->htmlState($model)['state'] ?? 'none';
        if (in_array($state, ['system', 'custom'], true)) {
            return;
        }

        $n = $model->notes->count();
        if ($n > (int) config('report_sheets.max_notes', 7)
            && trim((string) $model->content) !== '') {
            return;
        }

        throw new \RuntimeException('export_unavailable');
    }

    protected function reportHtml(Report $report, string $locale): string
    {
        $report->loadMissing(['author', 'notes']);
        if (! $report->isPublished()) {
            throw new \RuntimeException('export_unavailable');
        }
        $svc = app(ReportHtmlRenderingService::class);
        $state = $svc->htmlState($report)['state'] ?? 'none';
        $approved = in_array($state, ['system', 'custom'], true);
        if (! $approved) {
            $n = $report->notes->count();
            $approved = $n > (int) config('report_sheets.max_notes', 7)
                && trim((string) $report->content) !== '';
        }
        if (! $approved) {
            throw new \RuntimeException('export_unavailable');
        }
        $html = $svc->htmlFor($report, $svc->latestFor($report));
        if (! $html) {
            throw new \RuntimeException('export_unavailable');
        }

        return $this->inlineReportImages($html);
    }

    /** تضمين صور/خطوط التقرير محلياً — نفس منطق makeStandalonePrintableDocument. */
    protected function inlineReportImages(string $html): string
    {
        $cssPath = public_path('report/css/report-engine.css');
        $css = is_file($cssPath) ? file_get_contents($cssPath) : false;
        if ($css === false) {
            throw new \RuntimeException('The official report stylesheet is missing.');
        }
        $css = (string) preg_replace_callback(
            '#url\(([\'"]?)(/report/fonts/[^)\'"]+)\1\)#u',
            function (array $match): string {
                $fontPath = public_path(ltrim($match[2], '/'));
                if (! is_file($fontPath) || filesize($fontPath) > 2 * 1024 * 1024) {
                    throw new \RuntimeException('An official report font is missing.');
                }
                $contents = file_get_contents($fontPath);
                if ($contents === false) {
                    throw new \RuntimeException('An official report font could not be read.');
                }
                $mime = match (strtolower(pathinfo($fontPath, PATHINFO_EXTENSION))) {
                    'woff2' => 'font/woff2',
                    'woff' => 'font/woff',
                    'ttf' => 'font/ttf',
                    default => 'application/octet-stream',
                };

                return 'url(data:'.$mime.';base64,'.base64_encode($contents).')';
            },
            $css
        );
        $html = (string) preg_replace_callback(
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
                if ($file && is_file($file) && filesize($file) <= 5 * 1024 * 1024) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $mime = match ($ext) {
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'svg' => 'image/svg+xml',
                        default => 'application/octet-stream',
                    };

                    return 'src="data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file)).'"';
                }

                return $m[0];
            },
            $html
        );

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'.$css.'</style></head><body>'.$html.'</body></html>';
    }

    /** HTML الملاحظات/الإرساليات — نفس هوية مستند الطباعة الرسمي (A4/RTL/عربي). */
    protected function noteLikeHtml(Model $model, string $locale): string
    {
        $isSubmission = $model instanceof GeneralSubmission;
        $model->loadMissing(['owner', 'attachments']);
        $owner = $model->owner;
        $items = $this->processAttachments($model->attachments, $locale, $isSubmission);

        return view('pdf.note', [
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'lang' => $locale,
            'ministry' => __('ui.print_ministry', [], $locale),
            'ministrySub' => __('ui.print_ministry_sub', [], $locale),
            'kindTitle' => $isSubmission
                ? __('ui.print_submission_title', [], $locale).' #'.$model->getKey()
                : __('ui.print_note_title', [], $locale).' #'.$model->getKey(),
            'meta' => $this->metaRows($model, $locale, $owner),
            'paragraphs' => $this->descriptionParagraphs($model->description ?? '', $locale),
            'statusLabel' => $this->statusLabel($model, $locale),
            'statusClass' => $this->statusClass($model),
            'items' => $items,
            'attachmentsCount' => count($items),
            'generatedAt' => $this->formatPdfDate(now(), $locale),
            't' => [
                'description' => __('ui.print_description', [], $locale),
                'attachments' => __('ui.print_attachments', [], $locale),
                'attachmentsCount' => __('ui.print_attachments_count', [], $locale),
                'noAttachments' => __('ui.print_no_attachments', [], $locale),
                'pageNum' => __('ui.print_page_num', [], $locale),
                'videoFrame' => __('ui.print_video_frame', [], $locale),
                'imageScaled' => __('ui.imageScaled', [], $locale),
                'imageTooLarge' => __('ui.imageTooLarge', [], $locale),
                'videoSnapshot' => __('ui.videoSnapshot', [], $locale),
                'videoNoFrame' => __('ui.videoNoFrame', [], $locale),
                'fileMissing' => __('ui.file_missing', [], $locale),
                'openAttachment' => __('ui.print_open_attachment', [], $locale),
                'openAttachmentEn' => $locale === 'ar'
                    ? __('ui.print_open_attachment', [], 'en')
                    : __('ui.print_open_attachment', [], 'ar'),
            ],
        ])->render();
    }

    /**
     * الوصف إلى فقرات مفصولة — كل فقرة تُطبع كفقرة مستقلة مُبرَّرة بمسافة واضحة.
     * السبب: نص طويل داخل عنصر واحد يظهر كجدار غير مقروء، والفقرات تُقرأ أدقّ.
     * لا يُضاف ولا يُحذف أي محتوى — تنظيم فقط.
     */
    public function descriptionParagraphs(?string $raw, string $locale): array
    {
        $text = $this->normalizePrintText((string) $raw, $locale);
        if ($text === '') {
            return [];
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $blocks = preg_split('/\n[ \t\x{00A0}]*\n+/u', $text) ?: [$text];

        $out = [];
        foreach ($blocks as $block) {
            $block = trim(preg_replace('/[ \t\x{00A0}]*\n[ \t\x{00A0}]*/u', ' ', $block) ?? '');
            if ($block !== '') {
                $out[] = $block;
            }
        }

        return $out;
    }

    /**
     * تنظيف لغوي للطباعة — النص يبقى كما كتبه المراقب بلا تغيير في المضمون:
     * حذف محارف التحكم الاتجاهي غير المرئية، توحيد المسافات، ضبط الترقيم،
     * وفصل الأرقام عن الحروف العربية الملتصقة بها.
     */
    protected function normalizePrintText(string $text, string $locale): string
    {
        $text = (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text);
        $text = (string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}]/u', '', $text);

        $text = str_replace(["\x{00A0}", "\x{2007}", "\x{202F}"], ' ', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);

        $text = (string) preg_replace('/ +([\x{060C}\x{061B}\x{061F}\x{003A}\x{002E}\x{002C}])/u', '$1', $text);
        $text = (string) preg_replace('/([\x{060C}\x{061B}\x{061F}\x{0021}\x{003F}])(?=\S)/u', '$1 ', $text);

        $text = (string) preg_replace('/(?<![ء-ي])(و)(?=\p{Nd})/u', '$1', $text);
        $text = (string) preg_replace('/(\p{Arabic})(?=\p{Nd})/u', '$1 ', $text);
        $text = (string) preg_replace('/(\p{Nd})(?=\p{Arabic})/u', '$1 ', $text);
        $text = (string) preg_replace('/(^|[\s\p{P}])(و)\s+(?=\p{Nd})/u', '$1$2', $text);

        $text = (string) preg_replace('/[ \t]{2,}/u', ' ', $text);

        return trim($text);
    }

    /**
     * تفاصيل الوثيقة — كل ما يحتاجه القارئ وحده بلا الرجوع للنظام.
     */
    protected function metaRows(Model $model, string $locale, $owner): array
    {
        $fmt = fn ($v) => $this->formatPdfDate($v, $locale);
        $rows = [
            [__('ui.cam_no', [], $locale), (string) ($model->camera_number ?? '—')],
            [__('ui.floor_no', [], $locale), (string) ($model->floor_number ?? '—')],
            [__('ui.report_date_label', [], $locale), $fmt($model->observed_at ?? $model->created_at)],
        ];
        if (! empty($model->observed_end_at)) {
            $rows[] = [__('ui.print_end', [], $locale), $fmt($model->observed_end_at)];
        }
        $rows[] = [__('ui.observer', [], $locale), $owner ? (string) ($owner->localized_name ?? $owner->name) : '—'];

        if (! empty($model->sent_at)) {
            $rows[] = [__('ui.print_sent_at', [], $locale), $fmt($model->sent_at)];
        }
        if (! empty($model->processed_at)) {
            $rows[] = [__('ui.print_processed_at', [], $locale), $fmt($model->processed_at)];
        }
        if (! empty($model->rejection_reason)) {
            $rows[] = [__('ui.print_rejection', [], $locale), (string) $model->rejection_reason];
        }
        $rows[] = [__('ui.print_updated_at', [], $locale), $fmt($model->updated_at)];

        return $rows;
    }

    protected function statusLabel(Model $model, string $locale): string
    {
        try {
            return app(\App\Services\Localization\LocalizedPresenter::class)->statusLabel((string) ($model->status ?? ''), $locale);
        } catch (\Throwable) {
            return (string) ($model->status ?? '');
        }
    }

    /**
     * لون شارة الحالة في الوثيقة — الأخضر للمنشور/المقبول فقط، لا للكل.
     * كانت الشارة خضراء دائماً حتى للمسودة والمرفوضة (مضللة رسمياً).
     */
    protected function statusClass(Model $model): string
    {
        return match ((string) ($model->status ?? '')) {
            'published', 'accepted' => 'ok',
            'pending', 'sent' => 'wait',
            'rejected' => 'bad',
            default => 'muted',
        };
    }

    /**
     * تاريخ رسمي مترجم: «9 أكتوبر 2026 - 10:05» بدل «2026-09-09 10:05».
     * Carbon يحمل ملفات لغته ذاتياً (لا يعتمد intl)، ومع أي فشل نرجع
     * الصيغة الرقمية بدل كسر التوليد.
     */
    protected function formatPdfDate($v, string $locale): string
    {
        if ($v instanceof \DateTimeInterface) {
            try {
                return \Illuminate\Support\Carbon::parse($v)
                    ->locale($locale === 'en' ? 'en' : 'ar')
                    ->translatedFormat('j F Y - H:i');
            } catch (\Throwable) {
                return $v->format('Y-m-d H:i');
            }
        }

        return (string) ($v ?? '—');
    }

    /**
     * معالجة ذكية للمرفقات — لا إسقاط صامت أبداً:
     * صورة→مضمّنة مصغّرة، SVG→مضمّنة، فيديو→مصغّرة ffmpeg أو شارة+بيانات+رابط،
     * صوت/ملفات→صف بيانات+رابط، مفقود/تالف→صف معلن.
     *
     * كل مرفق يحمل ترقيمه ورابطه حتى لو كان مصوّراً داخل الوثيقة: الرابط هو
     * طريق المستخدم إلى الملف الأصلي كاملاً، وهو لا يظهر إلا لمن يبحث عنه.
     */
    protected function processAttachments($attachments, string $locale, bool $isSubmission): array
    {
        $items = [];
        $index = 0;
        $this->inlineBytesSpent = 0;

        foreach ($attachments as $att) {
            $index++;
            $name = (string) ($att->original_name ?? '');
            if ($name === '') {
                $name = basename((string) $att->file_path);
            }
            $mime = strtolower((string) ($att->mime_type ?? ''));
            $size = (int) ($att->file_size ?? 0);
            $url = $this->attachmentUrl($att, $isSubmission);
            $local = $this->localPath($att);

            $base = [
                'index' => $index,
                'name' => $name,
                'mime' => $mime,
                'size' => $size,
                'sizeLabel' => $this->formatSize($size),
                'url' => $url,
                'dataUri' => null,
            ];

            if ($local === null) {
                $items[] = $base + ['kind' => 'missing', 'meta' => $base['sizeLabel']];
                continue;
            }

            if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
                $used = 0;
                $uri = $this->embedImage($local, $used);
                $budgetLeft = $this->inlineBudgetLeft() - $this->inlineBytesSpent;
                if ($uri !== null && $used > $budgetLeft) {
                    $uri = null;
                }
                if ($uri !== null) {
                    $this->inlineBytesSpent += $used;
                    $items[] = array_merge($base, [
                        'kind' => 'image',
                        'meta' => $this->formatSize($size),
                        'dataUri' => $uri,
                        'isOriginal' => $used >= $size,
                    ]);
                } elseif ($this->isImageOversized($local)) {
                    $items[] = $base + ['kind' => 'too_large', 'meta' => $this->formatSize($size)];
                } else {
                    $items[] = $base + ['kind' => 'corrupt', 'meta' => $mime];
                }
                continue;
            }

            if ($mime === 'image/svg+xml' || str_ends_with(strtolower($name), '.svg')) {
                $raw = @file_get_contents($local);
                $items[] = ($raw !== false && strlen($raw) <= 1024 * 1024)
                    ? $base + [
                        'kind' => 'image',
                        'meta' => $this->formatSize($size),
                        'dataUri' => 'data:image/svg+xml;base64,'.base64_encode($raw),
                    ]
                    : $base + ['kind' => 'file', 'meta' => $this->metaLabel($mime, $size)];
                continue;
            }

            if (str_starts_with($mime, 'video/')) {
                $thumb = $this->videoThumbnail($local);
                $items[] = $base + [
                    'kind' => 'video',
                    'meta' => $this->metaLabel($mime, $size),
                    'dataUri' => $thumb ? 'data:image/jpeg;base64,'.base64_encode($thumb) : null,
                ];
                continue;
            }

            $items[] = $base + ['kind' => 'file', 'meta' => $this->metaLabel($mime, $size)];
        }

        return $items;
    }

    /** «video/mp4 · 12.4 MB» — النوع ثم الحجم، بلا تكرار أو فاصل ميت. */
    protected function metaLabel(string $mime, int $size): string
    {
        $sizeLabel = $this->formatSize($size);

        return $mime !== '' ? $mime.' · '.$sizeLabel : $sizeLabel;
    }

    protected function localPath($att): ?string
    {
        try {
            $p = (string) ($att->file_path ?? '');
            if ($p === '' || ! Storage::disk('attachments')->exists($p)) {
                return null;
            }

            return Storage::disk('attachments')->path($p);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * رابط المرفق داخل الـPDF — موقّع ذاتياً.
     *
     * رابط route() العادي يفتحه قارئ PDF على الجوال بلا كوكيز جلسة، فينتقل
     * إلى صفحة الدخول ولا يرى الملف أبداً. الرابط الموقّع يعمل بذاته.
     * ترجع null عند تعذّر التوقيع، فالعنصر يبقى بلا رابط بدل رابط ميت.
     */
    protected function attachmentUrl($att, bool $isSubmission): ?string
    {
        try {
            return AttachmentSignedUrl::make($att, $isSubmission);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * تضمين صورة داخل الوثيقة — الأصل كاملاً إن سُمح له بالحجم، وإلا نسخة
     * عالية الجودة مع تصريح صريح في الوثيقة.
     *
     * لماذا الأصل أولاً: طلب المستخدم «الرابط مع الملف الأصلي»، فلا معنى
     * أن تُطبع نسخة مصغّرة صامتة. لكن أصلاً بحجم 18 ميغابايت يصبح ~25
     * ميغابايت base64 فيتخطى حدّ HTML وتفشل خطوة توليد الوثيقة كلها.
     * لذلك ميزانية صريحة: الأصل ضمنها، وخارجهما نسخة مصغّرة **معلنة**
     * + رابط الأصل كاملاً (الرابط موجود دائماً فلا تضيع المعلومة).
     */
    protected function embedImage(string $path, ?int &$bytesUsed = null): ?string
    {
        try {
            if (! is_file($path)) {
                return null;
            }
            $size = (int) filesize($path);
            if ($size === 0) {
                return null;
            }
            [$w, $h] = @getimagesize($path) + [null, null];
            if (! $w || ! $h) {
                return null;
            }

            $perFile = (int) config('pdf.inline_original_max_kb', 3072) * 1024;
            if ($size <= $perFile) {
                $bytesUsed = (int) ceil($size * 1.37);

                return 'data:'.$this->guessMime($path).';base64,'.base64_encode((string) file_get_contents($path));
            }

            if (! function_exists('imagecreatefromstring')) {
                return null;
            }
            $max = (int) config('pdf.inline_downscale_px', 1400);
            $src = @imagecreatefromstring((string) file_get_contents($path));
            if (! $src) {
                return null;
            }
            $s = min(1.0, $max / max($w, $h));
            $tw = max(1, (int) round($w * $s));
            $th = max(1, (int) round($h * $s));
            $dst = imagecreatetruecolor($tw, $th);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
            ob_start();
            imagejpeg($dst, null, 88);
            $bin = (string) ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            if ($bin === '') {
                return null;
            }
            $bytesUsed = (int) ceil(strlen($bin) * 1.37);

            return 'data:image/jpeg;base64,'.base64_encode($bin);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * لقطة واحدة من الفيديو — لا تُضمَّن حركة.
     *
     * قرار المستخدم: «صورة لكن وُضّح أن هذا فيديو وصورة لقطة». فلا ادّعاء
     * بأن الوثيقة تحمل الفيديو، واللقطة معلَّمة كلقطة في النص نفسه.
     *
     * الدقة تتبع معيار الصور (inline_downscale_px) لا 640px القديمة التي
     * كانت ربعها فتخرج ناقصة في الورقة. ونجرّب أكثر من موضع زمني لأن
     * الثانية الأولى قد تكون سوداء (شاشة بدء أو مشهد مُعتم).
     */
    protected function videoThumbnail(string $path): ?string
    {
        try {
            $ffmpeg = LocalBinary::findFfmpeg();
            if ($ffmpeg === null) {
                return null;
            }
            $px = max(320, (int) config('pdf.inline_downscale_px', 1400));
            foreach ([1.0, 3.0, 0.2, 6.0] as $seek) {
                $out = tempnam(sys_get_temp_dir(), 'vthumb').'.jpg';
                $result = Process::timeout(25)->run([
                    $ffmpeg, '-y', '-ss', (string) $seek, '-i', $path,
                    '-vframes', '1', '-vf', 'scale='.$px.':-2', '-q:v', '3', $out,
                ]);
                if ($result->successful() && is_file($out) && filesize($out) > 0) {
                    $bin = (string) file_get_contents($out);
                    @unlink($out);
                    if ($bin !== '') {
                        return $bin;
                    }

                    continue;
                }
                @unlink($out);
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function guessMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, 3);

        return round($bytes / (1024 ** $i), 1).' '.$u[$i];
    }

    /** Chrome headless — نفس محرك نقطة الطباعة المجرّبة. */
    public function htmlToPdf(string $html): string
    {
        if (trim($html) === '' || strlen($html) > self::MAX_HTML_BYTES) {
            throw new \RuntimeException('Invalid HTML document');
        }
        $chrome = LocalBinary::findChrome();
        if ($chrome === null) {
            throw new \RuntimeException('PDF engine unavailable');
        }
        $dir = sys_get_temp_dir().'/rasd-print';
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $base = $dir.'/'.bin2hex(random_bytes(8));
        $htmlFile = $base.'.html';
        $pdfFile = $base.'.pdf';
        file_put_contents($htmlFile, $html);
        try {
            $result = Process::timeout(90)->run([
                $chrome, '--headless', '--disable-gpu', '--no-sandbox',
                '--disable-dev-shm-usage',
                '--hide-scrollbars', '--no-pdf-header-footer',
                '--print-to-pdf='.$pdfFile, \App\Support\LocalBinary::fileUrl($htmlFile),
            ]);
            if (! $result->successful() || ! is_file($pdfFile) || filesize($pdfFile) === 0) {
                throw new \RuntimeException('PDF engine failed');
            }
            $bytes = (string) file_get_contents($pdfFile);
            if ($bytes === '') {
                throw new \RuntimeException('PDF engine failed');
            }

            return $bytes;
        } finally {
            @unlink($htmlFile);
            @unlink($pdfFile);
        }
    }

    private function chromeBinary(): ?string
    {
        return \App\Support\LocalBinary::findChrome();
    }

    /** ZIP الملفات الأصلية — للمشاركة الكاملة، بحد أمان للحجم. */
    public function zipOriginals(Model $model): string
    {
        $model->loadMissing('attachments');
        $zipPath = tempnam(sys_get_temp_dir(), 'rasd-zip').'.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('ZIP unavailable');
        }
        $total = 0;
        $limit = 500 * 1024 * 1024;
        $added = 0;
        try {
            foreach ($model->attachments as $att) {
                $local = $this->localPath($att);
                if ($local === null) {
                    continue;
                }
                $size = filesize($local);
                if ($size === false || $total + $size > $limit) {
                    continue;
                }
                $name = preg_replace('/[\\\\\\/:\*\?"<>\|]/u', '_', (string) ($att->original_name ?: basename($local)));
                if ($name === '') {
                    $name = 'file-'.$att->getKey();
                }
                $zip->addFile($local, $att->getKey().'_'.$name);
                $total += $size;
                $added++;
            }
        } finally {
            $zip->close();
        }
        if ($added === 0) {
            @unlink($zipPath);
            throw new \RuntimeException('no_files');
        }

        return $zipPath;
    }

    private function safeType(Model $model): string
    {
        try {
            return $this->typeOf($model);
        } catch (\Throwable) {
            return get_class($model);
        }
    }
}
