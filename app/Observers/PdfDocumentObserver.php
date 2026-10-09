<?php

namespace App\Observers;

use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\Report;
use App\Models\ReportSheetRender;
use App\Services\Pdf\PdfDocumentService;
use Illuminate\Database\Eloquent\Model;

/**
 * يُبقي PDF السيرفر مطابقاً لآخر نسخة: أي كتابة على المحتوى أو المرفقات
 * تُبطل النسخة وتُعيد التوليد المتزامن (معزول الأخطاء — لا يكسر الحفظ).
 */
class PdfDocumentObserver
{
    public function created(Model $model): void
    {
        $this->refreshFor($model);
    }

    public function updated(Model $model): void
    {
        $this->refreshFor($model);
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Attachment || $model instanceof GeneralSubmissionAttachment) {
            $this->refreshFor($model);
            return;
        }
        try {
            app(PdfDocumentService::class)->deleteFor($model);
        } catch (\Throwable) {
        }
    }

    public function forceDeleted(Model $model): void
    {
        $this->deleted($model);
    }

    private function refreshFor(Model $model): void
    {
        try {
            $svc = app(PdfDocumentService::class);
            if ($model instanceof Attachment) {
                $note = $model->note;
                if ($note) {
                    $svc->refresh($note);
                }
                return;
            }
            if ($model instanceof GeneralSubmissionAttachment) {
                $sub = $model->submission;
                if ($sub) {
                    $svc->refresh($sub);
                }
                return;
            }
            if ($model instanceof ReportSheetRender) {
                $report = $model->report;
                if ($report) {
                    $svc->refresh($report);
                }
                return;
            }
            if ($model instanceof Note || $model instanceof GeneralSubmission || $model instanceof Report) {
                $svc->refresh($model);
            }
        } catch (\Throwable) {
        }
    }
}
