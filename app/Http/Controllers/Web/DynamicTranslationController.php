<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Localization\SourceLanguage;
use App\Services\Localization\TranslationService;
use Illuminate\Http\Request;

class DynamicTranslationController extends Controller
{
    public function translatePage(Request $request, TranslationService $service)
    {
        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'in:ar,en'],
            'items' => ['required', 'array', 'min:1', 'max:'.TranslationService::MAX_ITEMS],
            'items.*.type' => ['required', 'string', 'in:report,note,submission,notification'],
            'items.*.id' => ['required'],
            'items.*.fields' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.fields.*' => ['nullable', 'string', 'max:'.TranslationService::MAX_FIELD_CHARS],
        ]);

        $locale = SourceLanguage::normalizeLocale($validated['locale'] ?? app()->getLocale());

        $refs = [];
        foreach ($validated['items'] as $it) {
            foreach ((array) ($it['fields'] ?? []) as $field => $text) {
                $refs[] = ['type' => $it['type'], 'id' => $it['id'], 'field' => (string) $field, 'text' => $text];
            }
        }

        $translations = $service->resolveStoredMany($refs, $locale);

        return response()->json([
            'ok' => true,
            'locale' => $locale,
            'translations' => $translations,
        ]);
    }

    public function retry(Request $request, TranslationService $service)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:note,submission'],
            'id' => ['required'],
            'locale' => ['nullable', 'string', 'in:ar,en'],
        ]);
        $type = $validated['type'];
        $locale = SourceLanguage::normalizeLocale($validated['locale'] ?? app()->getLocale());

        $model = $type === 'note'
            ? \App\Models\Note::find($validated['id'])
            : \App\Models\GeneralSubmission::find($validated['id']);
        if (!$model) {
            return response()->json(['ok' => false, 'message' => __('ui.no_notes')], 404);
        }
        $this->authorize('view', $model);

        $candidates = ['description' => (string) ($model->description ?? '')];
        if (!empty($model->rejection_reason)) {
            $candidates['rejection_reason'] = (string) $model->rejection_reason;
        }
        $missing = [];
        foreach ($candidates as $field => $source) {
            $source = trim($source);
            if ($source === '' || mb_strlen($source) > TranslationService::MAX_FIELD_CHARS) {
                continue;
            }
            if (!$service->shouldTranslateCached($source)) {
                continue;
            }
            $sourceLang = TranslationService::detectCached($source);
            if ($sourceLang === $locale || $sourceLang === SourceLanguage::NEUTRAL) {
                continue;
            }
            if ($service->resolveStored($type, $model->id, $field, $source, $locale) !== null) {
                continue;
            }
            $missing[$field] = $source;
        }

        if ($missing === []) {
            $reason = 'nothing';
            foreach ($candidates as $field => $src) {
                $s = trim($src);
                if ($s === '') { $reason='empty'; break; }
                if (!$service->shouldTranslateCached($s)) { $reason='technical'; break; }
                $sl = TranslationService::detectCached($s);
                if ($sl === $locale) { $reason='same_lang'; break; }
                if ($service->resolveStored($type, $model->id, $field, $s, $locale) !== null) { $reason='already_ready'; break; }
            }
            $msg = $reason==='same_lang' ? ($locale==='en'?'Already in English':'بالفعل بالعربية') : ($reason==='already_ready'?__('ui.note_translation_ready'):__('ui.note_translation_nothing_to_do'));
            return response()->json([
                'ok' => true, 'locale' => $locale, 'queued' => false, 'reason'=>$reason,
                'message' => $msg,
            ]);
        }

        try {
            \App\Jobs\WarmTranslationProjection::dispatch($type, $model->id, $missing, $locale);
        } catch (\Throwable) {
        }

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => true, 'locale' => $locale, 'queued' => true,
                'message' => __('ui.note_translation_retry_queued'),
            ]);
        }

        return back()->with('success', __('ui.note_translation_retry_queued'));
    }
}
