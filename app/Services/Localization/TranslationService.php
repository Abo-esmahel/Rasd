<?php

namespace App\Services\Localization;

use App\Models\ContentTranslation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TranslationService — الخدمة المركزية الوحيدة للترجمة (Presentation Concern).
 *
 *   SOURCE ENTITY → TranslationService → Provider (Gemini) → Translation Projection
 *       → Presentation Resolver (LocalizedPresenter) → Localized UI/Data/Reports
 *
 * ضمانات:
 * - لا تمس Business Data إطلاقاً (قراءة للمصدر + كتابة في content_translations فقط).
 * - القراءة الطبيعية: Valid Projection → عرض مباشر، بلا Provider.
 * - دفعة Provider واحدة لكل استدعاء مهما بلغ عدد الحقول + dedup للنصوص المكررة.
 * - الترجمة دائماً من المصدر مباشرة (ar→en أو en→ar) حسب لغة المصدر المكتشفة.
 * - ربط كل projection بـ source_hash — أي تغيير في المصدر يُبطلها تلقائياً.
 * - أي فشل (provider/cache/db) → fallback صامت للنص الأصلي، ولا تُرمى استثناءات.
 * - لا IDs/URLs/phones/files/timestamps/numbers تُرسل للترجمة. الأسماء تُحفظ.
 */
class TranslationService
{
    /** حقول قابلة للترجمة لكل entity — أي حقل خارجها يُرفض (حماية + توفير). */
    public const TRANSLATABLE_FIELDS = [
        'report' => ['title', 'summary', 'recommendations', 'content', 'observation', 'revision'],
        'note' => ['description', 'rejection_reason'],
        'submission' => ['description', 'title', 'rejection_reason'],
        'notification' => ['title', 'message', 'reason'],
    ];

    /** حقول أسماء أشخاص — تُعاد كما هي دائماً، لا تُرسل إلى Provider. */
    public const PERSON_NAME_FIELDS = [
        'name', 'username', 'author_name', 'owner_name', 'user_name', 'observer',
        'editor_name', 'processor_name', 'sender_name',
    ];

    public const MAX_ITEMS = 30;

    public const MAX_FIELD_CHARS = 5000;

    public const MAX_TOTAL_CHARS = 15000;

    public const CACHE_TTL_DAYS = 30;

    public function __construct(private TranslationProviderInterface $provider)
    {
    }

    public static function sourceHash(string $text): string
    {
        return hash('sha256', $text);
    }

    public static function cacheKey(string $type, int|string $id, string $field, string $locale, string $hash): string
    {
        return "l10n:v2:{$type}:{$id}:{$field}:{$locale}:{$hash}";
    }

    public static function itemKey(string $type, int|string $id, string $field): string
    {
        return "{$type}:{$id}:{$field}";
    }

    /**
     * معرّف كيان صالح: رقمي (>0) أو UUID نصي. أي شيء آخر يُرفض.
     * content_translations.translatable_id نصّي — لا casts رقمية هنا.
     */
    public static function validId(mixed $id): ?string
    {
        if (is_int($id)) {
            return $id > 0 ? (string) $id : null;
        }
        if (is_string($id)) {
            $id = trim($id);
            if ($id !== '' && $id !== '0' && strlen($id) <= 64 && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $id)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * حقل واحد — للاستخدام الفردي. لا يرمي استثناءات أبداً.
     *
     * ملاحظة معمارية (Background-only Gemini):
     * هذا المسار قد يستدعي Provider — يُستخدم حصرياً داخل Jobs الخلفية
     * (WarmTranslationProjection بعد CREATE/UPDATE). مسار العرض (قراءة +
     * Language Switch) ممنوع من استدعائه — يستخدم resolveStored* فقط.
     */
    public function localize(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): string
    {
        $locale = SourceLanguage::normalizeLocale($locale ?? app()->getLocale());
        $map = $this->localizeMany([['type' => $type, 'id' => $id, 'field' => $field, 'text' => $source]], $locale);

        return $map[self::itemKey($type, $id, $field)] ?? (string) $source;
    }

    /**
     * قراءة محفوظة فقط — المسار الوحيد المسموح للعرض و Language Switch.
     *
     *   Locale → Existing localized data → Render (ZERO Gemini)
     *
     * - يقرأ Cache ثم content_translations المطابقة لـ source_hash الحالي فقط.
     * - لا يستدعي Provider إطلاقاً، لا يرمي استثناءات، لا fallback للمصدر هنا
     *   (الـ fallback/placeholder قرار الـ Presenter حسب الـ locale).
     *
     * @return array<string,string> "type:id:field" => translated (hits فقط)
     */
    public function resolveStoredMany(array $refs, ?string $locale = null): array
    {
        $locale = SourceLanguage::normalizeLocale($locale ?? app()->getLocale());
        $out = [];
        $pending = []; // key => ['type','id','field','text','hash','cache_key']

        foreach ($refs as $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $type = strtolower(trim((string) ($ref['type'] ?? '')));
            $id = self::validId($ref['id'] ?? null);
            $field = (string) ($ref['field'] ?? '');
            $text = trim((string) ($ref['text'] ?? ''));
            if (!isset(self::TRANSLATABLE_FIELDS[$type]) || $id === null || $field === '' || $text === '') {
                continue;
            }
            $base = explode(':', $field, 2)[0];
            $isName = in_array(strtolower($field), self::PERSON_NAME_FIELDS, true);
            if (!in_array($base, self::TRANSLATABLE_FIELDS[$type], true) && !$isName) {
                continue;
            }
            if (mb_strlen($text) > self::MAX_FIELD_CHARS) {
                $text = mb_substr($text, 0, self::MAX_FIELD_CHARS);
            }

            $key = self::itemKey($type, $id, $field);

            // لا ترجمة مطلوبة أصلاً (اسم/تقني/نفس اللغة/محايد) — ليست miss.
            if ($isName || !$this->shouldTranslate($text)) {
                continue;
            }
            $sourceLang = SourceLanguage::detect($text);
            if ($sourceLang === $locale || $sourceLang === SourceLanguage::NEUTRAL) {
                continue;
            }

            $hash = self::sourceHash($text);
            $ck = self::cacheKey($type, $id, $field, $locale, $hash);

            try {
                $hit = Cache::get($ck);
            } catch (\Throwable) {
                $hit = null;
            }
            if (is_string($hit) && $hit !== '') {
                $out[$key] = $hit;
                continue;
            }

            $pending[$key] = [
                'type' => $type, 'id' => $id, 'field' => $field,
                'text' => $text, 'hash' => $hash, 'cache_key' => $ck,
            ];
        }

        if ($pending === []) {
            return $out;
        }

        // دفعة DB واحدة — بلا N+1 وبلا Provider (تمنع translation storm).
        try {
            $byType = [];
            foreach ($pending as $key => $p) {
                $byType[$p['type']][] = $p;
            }
            foreach ($byType as $type => $items) {
                $ids = array_values(array_unique(array_map(fn ($p) => $p['id'], $items)));
                $fields = array_values(array_unique(array_map(fn ($p) => $p['field'], $items)));
                $hashes = array_values(array_unique(array_map(fn ($p) => $p['hash'], $items)));
                $rows = ContentTranslation::where('translatable_type', $type)
                    ->where('locale', $locale)
                    ->whereIn('translatable_id', $ids)
                    ->whereIn('field', $fields)
                    ->whereIn('source_hash', $hashes)
                    ->get(['translatable_id', 'field', 'source_hash', 'translated_text']);
                foreach ($rows as $row) {
                    $rk = self::itemKey($type, (string) $row->translatable_id, (string) $row->field);
                    // طابق البصمة الحالية فقط — القديمة stale وتُتجاهل.
                    foreach ($pending as $key => $p) {
                        if ($key === $rk && $p['hash'] === (string) $row->source_hash
                            && is_string($row->translated_text) && $row->translated_text !== '') {
                            $out[$key] = $row->translated_text;
                            try {
                                Cache::put($p['cache_key'], $row->translated_text, now()->addDays(self::CACHE_TTL_DAYS));
                            } catch (\Throwable) {
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // فشل القراءة = miss — الـ Presenter يعرض placeholder حسب اللغة.
        }

        return $out;
    }

    /**
     * حقل واحد محفوظ فقط — null تعني: لا ترجمة محفوظة مطابقة للبصمة الحالية.
     * لا Provider هنا إطلاقاً (Language Switch آمن).
     */
    public function resolveStored(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): ?string
    {
        $locale = SourceLanguage::normalizeLocale($locale ?? app()->getLocale());
        $map = $this->resolveStoredMany([['type' => $type, 'id' => $id, 'field' => $field, 'text' => $source]], $locale);

        return $map[self::itemKey($type, $id, $field)] ?? null;
    }

    /**
     * حالة الترجمة للعرض (بلا Gemini — قراءة فقط):
     * - 'source' … لا ترجمة مطلوبة (نفس اللغة/محايد/تقني/اسم).
     * - 'ready' …. ترجمة محفوظة مطابقة للبصمة الحالية.
     * - 'pending' .. ترجمة مطلوبة لكن لا projection صالحة بعد.
     */
    public function translationState(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): string
    {
        $locale = SourceLanguage::normalizeLocale($locale ?? app()->getLocale());
        $source = trim((string) $source);
        if ($source === '') {
            return 'source';
        }
        $base = explode(':', (string) $field, 2)[0];
        $type = strtolower(trim($type));
        if (!isset(self::TRANSLATABLE_FIELDS[$type])) {
            return 'source';
        }
        if (in_array(strtolower((string) $field), self::PERSON_NAME_FIELDS, true)) {
            return 'source';
        }
        if (!in_array($base, self::TRANSLATABLE_FIELDS[$type], true)) {
            return 'source';
        }
        if (!$this->shouldTranslate($source)) {
            return 'source';
        }
        $sourceLang = SourceLanguage::detect($source);
        if ($sourceLang === $locale || $sourceLang === SourceLanguage::NEUTRAL) {
            return 'source';
        }

        return $this->resolveStored($type, $id, $field, $source, $locale) !== null ? 'ready' : 'pending';
    }

    /**
     * دفعة حقول — المسار الرئيسي (صفحة/قائمة/تقرير). دفعة Provider واحدة كحد أقصى.
     *
     * @param  array<int, array{type: string, id: int|string, field: string, text: mixed}>  $refs
     * @return array<string,string>  "type:id:field" => localized presentation text
     */
    public function localizeMany(array $refs, ?string $locale = null): array
    {
        $locale = SourceLanguage::normalizeLocale($locale ?? app()->getLocale());
        $out = [];
        $jobs = []; // providerKey => ['type','id','field','text','hash','cache_key','out_keys'=>[]]

        foreach ($refs as $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $type = strtolower(trim((string) ($ref['type'] ?? '')));
            $id = self::validId($ref['id'] ?? null);
            $field = (string) ($ref['field'] ?? '');
            $text = trim((string) ($ref['text'] ?? ''));
            if (!isset(self::TRANSLATABLE_FIELDS[$type]) || $id === null || $field === '' || $text === '') {
                continue;
            }
            $base = explode(':', $field, 2)[0];
            $isName = in_array(strtolower($field), self::PERSON_NAME_FIELDS, true);
            if (!in_array($base, self::TRANSLATABLE_FIELDS[$type], true) && !$isName) {
                continue;
            }
            if (mb_strlen($text) > self::MAX_FIELD_CHARS) {
                $text = mb_substr($text, 0, self::MAX_FIELD_CHARS);
            }

            $key = self::itemKey($type, $id, $field);

            // 1) أسماء الأشخاص والقيم التقنية/المحايدة: كما هي، بلا تكلفة.
            if ($isName || !$this->shouldTranslate($text)) {
                $out[$key] = trim((string) ($ref['text'] ?? ''));
                continue;
            }

            // 2) المصدر بلغة الهدف أصلاً (أو محايد): لا ترجمة — اعرض المصدر.
            $sourceLang = SourceLanguage::detect($text);
            if ($sourceLang === $locale || $sourceLang === SourceLanguage::NEUTRAL) {
                $out[$key] = trim((string) ($ref['text'] ?? ''));
                continue;
            }

            if (count($jobs) >= self::MAX_ITEMS && !isset($jobs[$this->dedupKey($type, $field, $text)])) {
                // حد الحماية: الزائد يُعرض بمصدره (يُدفَّأ لاحقاً عبر الـJob).
                $out[$key] = trim((string) ($ref['text'] ?? ''));
                continue;
            }

            $hash = self::sourceHash($text);
            $ck = self::cacheKey($type, $id, $field, $locale, $hash);

            // 3) Valid Projection أولاً: Cache سريع (مفتاحه يشمل البصمة).
            try {
                $hit = Cache::get($ck);
            } catch (\Throwable) {
                $hit = null;
            }
            if (is_string($hit) && $hit !== '') {
                $out[$key] = $hit;
                continue;
            }

            // 4) Projection دائمة مطابقة للبصمة الحالية.
            try {
                $row = ContentTranslation::where('translatable_type', $type)
                    ->where('translatable_id', $id)
                    ->where('field', $field)
                    ->where('locale', $locale)
                    ->where('source_hash', $hash)
                    ->first();
            } catch (\Throwable) {
                $row = null;
            }
            if ($row && is_string($row->translated_text) && $row->translated_text !== '') {
                $out[$key] = $row->translated_text;
                try {
                    Cache::put($ck, $row->translated_text, now()->addDays(self::CACHE_TTL_DAYS));
                } catch (\Throwable) {
                }
                continue;
            }

            // 5) تجميع للدفعة — dedup: نفس النص + نفس الحقل يُرسل مرة واحدة.
            $dk = $this->dedupKey($type, $field, $text);
            if (!isset($jobs[$dk])) {
                $jobs[$dk] = [
                    'type' => $type, 'field' => $field, 'text' => $text,
                    'hash' => $hash, 'source_lang' => $sourceLang,
                    'targets' => [],
                ];
            }
            $jobs[$dk]['targets'][] = ['id' => $id, 'key' => $key, 'cache_key' => $ck, 'hash' => $hash];
        }

        if ($jobs === []) {
            return $out;
        }

        // دفعة Provider واحدة — مجمعة حسب اتجاه الترجمة (ar→en / en→ar).
        $byDir = [];
        foreach ($jobs as $dk => $job) {
            $byDir[$job['source_lang']][$dk] = $job['text'];
        }
        $translated = [];
        foreach ($byDir as $sourceLang => $texts) {
            try {
                $chunk = $this->provider->translateBatch($texts, $sourceLang, $locale);
                foreach ($chunk as $dk => $t) {
                    $translated[$dk] = $t;
                }
            } catch (\Throwable $e) {
                Log::warning('[L10N] batch translation failed, fallback to source', [
                    'dir' => "{$sourceLang}->{$locale}",
                    'count' => count($texts),
                    'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 200),
                ]);
            }
        }

        foreach ($jobs as $dk => $job) {
            $t = isset($translated[$dk]) ? trim((string) $translated[$dk]) : '';
            foreach ($job['targets'] as $target) {
                if ($t === '') {
                    // عنصر فاشل → fallback فردي للمصدر، بلا كسر.
                    $out[$target['key']] = $job['text'];
                    continue;
                }
                $out[$target['key']] = $t;
                try {
                    Cache::put($target['cache_key'], $t, now()->addDays(self::CACHE_TTL_DAYS));
                } catch (\Throwable) {
                }
                try {
                    ContentTranslation::updateOrCreate(
                        [
                            'translatable_type' => $job['type'],
                            'translatable_id' => $target['id'],
                            'field' => $job['field'],
                            'locale' => $locale,
                            'source_hash' => $target['hash'],
                        ],
                        [
                            'source_text' => mb_substr($job['text'], 0, 20000),
                            'translated_text' => $t,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('[L10N] projection persist failed', ['key' => $target['key']]);
                }
            }
        }

        return $out;
    }

    private function dedupKey(string $type, string $field, string $text): string
    {
        return $type."\0".explode(':', $field, 2)[0]."\0".self::sourceHash($text);
    }

    /**
     * هل يستحق النص الإرسال إلى Provider؟ القيم التقنية تُعاد كما هي بلا تكلفة.
     * IDs, UUIDs, camera numbers, codes, URLs, emails, phones, filenames,
     * timestamps, dates, numeric values — كلها مرفوضة هنا.
     */
    public function shouldTranslate(string $text): bool
    {
        $t = trim($text);
        if ($t === '' || is_numeric($t)) {
            return false;
        }
        if (filter_var($t, FILTER_VALIDATE_EMAIL) || filter_var($t, FILTER_VALIDATE_URL)) {
            return false;
        }
        if (preg_match('/^[\d\s\-+()\/:.]+$/u', $t)) {
            return false;
        }
        if (preg_match('/^\+?\d[\d\s\-()]{5,}$/u', $t)) {
            return false;
        }
        if (preg_match('/^[\w\-. ]+\.(png|jpe?g|gif|webp|mp4|mov|webm|mp3|wav|ogg|pdf)$/i', $t)) {
            return false;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $t)) {
            return false;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $t)) {
            return false;
        }
        // UUID / code صرف.
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $t)) {
            return false;
        }
        // Technical codes: uppercase words + dash + numbers (CAM-001, ID-123, REF-ABC-456)
        if (preg_match('/^[A-Z]{2,}(-[A-Z0-9]+){1,3}$/', $t)) {
            return false;
        }
        // بلا حروف بشرية (عربية/لاتينية) = تقني/مرقم — لا حاجة لـProvider.
        if (!preg_match('/[\x{0600}-\x{06FF}A-Za-z]/u', $t)) {
            return false;
        }

        return true;
    }

    /**
     * Presentation القيم الداخلية (enums) — القيم المخزنة لا تتغير أبداً.
     */
    public static function statusLabel(string $status, ?string $locale = null): string
    {
        $locale = SourceLanguage::normalizeLocale($locale ?? app()->getLocale());
        $status = strtolower(trim($status));

        if ($locale === 'ar') {
            return match ($status) {
                'draft' => 'مسودة',
                'pending' => 'قيد المراجعة',
                'accepted' => 'مقبولة',
                'rejected' => 'مرفوضة',
                'published' => 'منشور',
                'monitor' => 'مراقب ميداني',
                'report_writer' => 'كاتب تقارير',
                default => $status,
            };
        }

        return match ($status) {
            'draft' => 'Draft',
            'pending' => 'Pending review',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'published' => 'Published',
            'monitor' => 'Field Monitor',
            'report_writer' => 'Report Writer',
            default => $status,
        };
    }
}
