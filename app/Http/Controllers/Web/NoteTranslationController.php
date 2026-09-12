<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\WarmTranslationProjection;
use App\Models\Note;
use App\Services\Localization\LocalizedPresenter;
use App\Services\Localization\SourceLanguage;
use App\Services\Localization\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Smart Note Translation — الزر/الحالة (عرض + جدولة خلفية فقط).
 *
 * Architecture:
 *   Note Source → Translation Service → Gemini Provider (background only)
 *   → Stored Translation → Presentation Resolver → UI
 *
 * - status: قراءة محفوظة فقط — ZERO Gemini (آمن لـ Language Switch polling).
 * - retry: جدولة Job خلفي فقط — لا ترجمة متزامنة، لا كسر للحفظ.
 * - لا مصطلحات تقنية للمستخدم (بلا Gemini/Provider/Hash/API).
 */
class NoteTranslationController extends Controller
{
    /**
     * حالة الترجمة + النص العرضي الحالي (محفوظ أو placeholder بلغة الواجهة).
     * GET /notes/{note}/translation-status — قراءة فقط، بلا Gemini.
     */
    public function status(Note $note, LocalizedPresenter $presenter)
    {
        $this->authorize('view', $note);
        $locale = SourceLanguage::normalizeLocale(app()->getLocale());

        $fields = ['description' => (string) $note->description];
        if (!empty($note->rejection_reason)) {
            $fields['rejection_reason'] = (string) $note->rejection_reason;
        }

        $states = [];
        $texts = [];
        foreach ($fields as $field => $source) {
            $states[$field] = $presenter->translationState('note', $note->id, $field, $source, $locale);
            $texts[$field] = $presenter->text('note', $note->id, $field, $source, $locale);
        }

        $overall = in_array('pending', $states, true) ? 'pending'
            : (in_array('ready', $states, true) ? 'ready' : 'source');

        return response()->json([
            'ok' => true,
            'locale' => $locale,
            'note_id' => $note->id,
            'state' => $overall,
            'states' => $states,
            'texts' => $texts,
            'message' => $this->stateMessage($overall, $locale),
        ]);
    }

    /**
     * إعادة جدولة الترجمة الخلفية للحقول الناقصة فقط (dedup بالبصمة).
     * POST /notes/{note}/translation-retry — خلفية فقط، بلا Gemini متزامن.
     */
    public function retry(Request $request, Note $note, TranslationService $service)
    {
        $this->authorize('view', $note);
        $locale = SourceLanguage::normalizeLocale($request->input('locale', app()->getLocale()));

        $candidates = ['description' => (string) $note->description];
        if (!empty($note->rejection_reason)) {
            $candidates['rejection_reason'] = (string) $note->rejection_reason;
        }

        // الحقول التي تحتاج فعلاً هذه اللغة (تجاهل التقني/نفس اللغة).
        $missing = [];
        foreach ($candidates as $field => $source) {
            $source = trim($source);
            if ($source === '' || mb_strlen($source) > TranslationService::MAX_FIELD_CHARS) {
                continue;
            }
            if (!$service->shouldTranslate($source)) {
                continue;
            }
            $sourceLang = SourceLanguage::detect($source);
            if ($sourceLang === $locale || $sourceLang === SourceLanguage::NEUTRAL) {
                continue;
            }
            if ($service->resolveStored('note', $note->id, $field, $source, $locale) !== null) {
                continue;
            }
            $missing[$field] = $source;
        }

        if ($missing === []) {
            return response()->json([
                'ok' => true,
                'locale' => $locale,
                'queued' => false,
                'message' => __('ui.note_translation_nothing_to_do'),
            ]);
        }

        try {
            WarmTranslationProjection::dispatch('note', $note->id, $missing, $locale);
        } catch (\Throwable $e) {
            Log::warning('[L10N] note retry dispatch failed', ['note_id' => $note->id]);
        }

        $wantsJson = $request->expectsJson() || $request->ajax() || $request->wantsJson();
        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'locale' => $locale,
                'queued' => true,
                'message' => __('ui.note_translation_retry_queued'),
            ]);
        }

        return back()->with('success', __('ui.note_translation_retry_queued'));
    }

    private function stateMessage(string $state, string $locale): string
    {
        return match ($state) {
            'ready' => __('ui.note_translation_ready', [], $locale),
            'pending' => __('ui.note_translation_preparing', [], $locale),
            default => '',
        };
    }
}
