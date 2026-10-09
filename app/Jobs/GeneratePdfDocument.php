<?php

namespace App\Jobs;

use App\Models\GeneralSubmission;
use App\Models\Note;
use App\Models\Report;
use App\Services\Pdf\PdfDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * توليد مستند PDF خارج نطاق طلب HTTP.
 *
 * لماذا: توليد PDF شغّل Chrome headless حتى 90 ثانية. لو حدث داخل الطلب
 * لحجب الخادم كله (php -S أحادي الطلب) أو تجاوز max_execution_time عند
 * تقرير كبير. هنا يُولَّد في عامل الطابور ويكتب حالته في pdf_documents،
 * والعميل يستطلع /pdf-status فينتظر خارج زمان الطلب.
 */
class GeneratePdfDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly string $modelClass,
        public readonly int $modelId,
        public readonly string $locale,
    ) {
    }

    public function handle(PdfDocumentService $pdf): void
    {
        $model = $this->resolveModel();

        if (! $model instanceof Model) {
            return;
        }

        $lock = \Illuminate\Support\Facades\Cache::lock(
            'pdf-generate-'.$this->modelClass.'-'.$this->modelId.'-'.$this->locale,
            200,
        );

        if (! $lock->get()) {
            return;
        }

        try {
            $pdf->resolve($model, $this->locale);
        } catch (\Throwable) {
        } finally {
            optional($lock)->release();
        }
    }

    private function resolveModel(): ?Model
    {
        $allowed = [Note::class, GeneralSubmission::class, Report::class];

        if (! in_array($this->modelClass, $allowed, true)) {
            return null;
        }

        try {
            /** @var Model|null $model */
            $model = $this->modelClass::query()->find($this->modelId);
        } catch (\Throwable) {
            return null;
        }

        return $model;
    }
}