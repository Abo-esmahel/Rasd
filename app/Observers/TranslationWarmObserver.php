<?php

namespace App\Observers;

use App\Jobs\WarmTranslationProjection;
use App\Services\Localization\SourceLanguage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * TranslationWarmObserver — جدولة تدفئة projections بعد حفظ المصدر.
 *
 * - يُستدعى بعد commit (ShouldQueue dispatchAfterResponse داخل saved آمن).
 * - أي استثناء هنا يُلتقط — الحفظ لا يفشل بسبب الترجمة أبداً (Rule 10).
 * - الهدف دائماً عكس لغة المصدر المكتشفة (من المصدر مباشرة، Rule 8).
 */
class TranslationWarmObserver
{
    /**
     * خلفية فقط (CREATE/UPDATE):
     *   Source → Translation Service → Gemini Provider → Stored Translation.
     * - الحفظ لا ينتظر ولا يفشل بفشل الترجمة أبداً.
     * - Source Locale تُعرف تلقائياً: لغة الواجهة الحالية أولاً (لغة إدخال
     *   المستخدم)، ثم اكتشاف المحتوى كتحقق — بلا سؤال المستخدم.
     * - dedup: الحقول المحفوظة مسبقاً بنفس البصمة لا تُعاد جدولتها
     *   (يمنع requests غير ضرورية لنفس المحتوى).
     */
    public function saved(Model $model): void
    {
        try {
            $plan = $this->planFor($model);
            if ($plan === null) {
                return;
            }
            [$type, $id, $fields] = $plan;

            // Source Locale: لغة الإدخال = لغة الواجهة لحظة الحفظ (تلقائي).
            $uiLocale = SourceLanguage::normalizeLocale(app()->getLocale());

            // حدد اللغة الهدف من المصدر: عربي → en، إنجليزي → ar.
            // الأولوية للغة الواجهة (لغة المستخدم الحالية)، والاكتشاف يؤكد.
            $target = null;
            foreach ($fields as $text) {
                $lang = SourceLanguage::detect($text);
                if ($lang === SourceLanguage::ARABIC) {
                    $target = 'en';
                    break;
                }
                if ($lang === SourceLanguage::ENGLISH) {
                    $target = $target ?? 'ar';
                }
            }
            // لا لغة بشرية واضحة: استخدم عكس لغة الواجهة (مصدر الإدخال).
            if ($target === null) {
                $target = $uiLocale === 'ar' ? 'en' : 'ar';
            }

            // dedup: أسقط الحقول المحفوظة مسبقاً بنفس البصمة (stale القديمة
            // تُتجاهل تلقائياً عبر source_hash — الجديدة فقط تُجدول).
            try {
                $service = app(\App\Services\Localization\TranslationService::class);
                $refs = [];
                foreach ($fields as $field => $text) {
                    $refs[] = ['type' => $type, 'id' => $id, 'field' => $field, 'text' => $text];
                }
                $ready = $service->resolveStoredMany($refs, $target);
                foreach (array_keys($fields) as $field) {
                    $k = \App\Services\Localization\TranslationService::itemKey($type, $id, (string) $field);
                    if (isset($ready[$k])) {
                        unset($fields[$field]);
                    }
                }
                if ($fields === []) {
                    return;
                }
            } catch (\Throwable) {
                // فشل الفحص = جدولة عادية (الـ Job نفسه idempotent بالبصمة).
            }

            WarmTranslationProjection::dispatchAfterResponse($type, $id, $fields, $target);
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
                // observations مشتقة من الملاحظات المرفقة (systemData) — نفس
                // المصدر الذي يقرأه reportPayload عند العرض. تُجدول هنا في
                // الخلفية فقط (CREATE/UPDATE) — القراءة تستخدم المحفوظ فقط.
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

        // observations تُبنى بنفس ترتيب العرض (systemData) وبنفس مفاتيح
        // الـ Presenter (observation:{i}) — حتى تطابق projections القراءة.
        // تُقطع لـ 25 عنصراً كحد أقصى (4 حقول أساسية + 25 = 29 ≤ MAX_ITEMS=30).
        $fields = array_filter(
            array_map(fn ($t) => trim((string) $t), $fields),
            fn ($t) => $t !== '' && mb_strlen($t) <= 5000
        );
        if ($fields === []) {
            return null;
        }

        return [$type, (int) $model->getKey(), $fields];
    }

    /**
     * حقول observations المشتقة لتقرير — بنفس مفاتيح الـ Presenter.
     *
     * المصدر: ReportDataBuilder::systemData (notes المرفقة) — لا يمس Source،
     * لا يستدعي Gemini، لا يرمي استثناءات (الفشل = لا observations إضافية).
     *
     * @return array<string,string> "observation:{i}" => text
     */
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
