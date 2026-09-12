<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * صفحة التوجيه الذكي — بوابة واحدة لأي رابط يحتاج رسالة وتوجيهاً تلقائياً.
 *
 * - ‏/r?c=moved&to=/notes&s=5 : رسالة جاهزة + عدّاد + انتقال تلقائي.
 * - أي مسار غير موجود يسقط على missing() برسالة ذكية واقتراحات.
 *
 * أمان: الرسائل ثابتة من قائمة مغلقة (لا نصوص حرة من الرابط حتى لا تُستغل
 * للتصيّد)، ووجهة الانتقال مسارات داخلية فقط (منع Open Redirect).
 */
class SmartRedirectController extends Controller
{
    private const CODES = [
        'moved' => [
            'title' => 'انتقلنا إلى مكان أجمل',
            'message' => 'الرابط الذي استخدمته تغيّر — سنأخذك إلى الوجهة الجديدة تلقائياً.',
            'icon' => 'moved',
        ],
        'expired' => [
            'title' => 'انتهت صلاحية هذا الرابط',
            'message' => 'الروابط المؤقتة تنتهي حفاظاً على خصوصيتك — تابع من هنا أو اطلب رابطاً جديداً.',
            'icon' => 'expired',
        ],
        'denied' => [
            'title' => 'لا تملك صلاحية الوصول',
            'message' => 'هذه الصفحة مخصصة لدور آخر — سجّل الدخول بالحساب المناسب أو عُد للرئيسية.',
            'icon' => 'denied',
        ],
        'missing' => [
            'title' => 'الصفحة غير موجودة',
            'message' => 'يبدو أن الرابط خاطئ أو أن الصفحة حُذفت — لا تقلق، سنوجّهك للمكان الصحيح.',
            'icon' => 'missing',
        ],
        'done' => [
            'title' => 'تم بنجاح',
            'message' => 'اكتمل الإجراء بنجاح — جارٍ نقلك للخطوة التالية.',
            'icon' => 'done',
        ],
        'wait' => [
            'title' => 'لحظة واحدة',
            'message' => 'جارٍ تجهيز وجهتك ونقلك تلقائياً.',
            'icon' => 'wait',
        ],
    ];

    public function show(Request $request)
    {
        $code = (string) $request->query('c', 'wait');
        if (!isset(self::CODES[$code])) {
            $code = 'wait';
        }

        $authed = auth()->check();
        $default = $authed ? route('notes.index') : route('login');
        $to = self::safeTarget($request->query('to'), $default);

        $delay = (int) $request->query('s', 5);
        $delay = max(0, min(30, $delay));

        return response()->view('shared.redirect', [
            'code' => $code,
            'title' => self::CODES[$code]['title'],
            'message' => self::CODES[$code]['message'],
            'icon' => self::CODES[$code]['icon'],
            'target' => $to,
            'delay' => $delay,
            'home' => $authed ? route('notes.index') : route('login'),
        ]);
    }

    public function missing(Request $request)
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'الصفحة غير موجودة',
            ], 404);
        }

        $authed = auth()->check();
        $home = $authed ? route('notes.index') : route('login');

        return response()->view('shared.redirect', [
            'code' => 'missing',
            'title' => self::CODES['missing']['title'],
            'message' => self::CODES['missing']['message'],
            'icon' => 'missing',
            'target' => $home,
            'delay' => 8,
            'home' => $home,
            'wanted' => mb_substr($request->path(), 0, 80),
        ], 404);
    }

    /**
     * يقبل مساراً داخلياً فقط: يبدأ بـ / واحدة، بلا مسافات أو رموز خطرة.
     * أي قيمة مرفوضة تعيد الوجهة الافتراضية — لا تحويل خارجي أبداً.
     */
    public static function safeTarget(mixed $to, string $fallback): string
    {
        if (!is_string($to)) {
            return $fallback;
        }
        $to = trim($to);
        if ($to === '' || strlen($to) > 500) {
            return $fallback;
        }
        if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
            return $fallback;
        }
        if (str_contains($to, '\\') || preg_match('/[\s<>"\'`]/u', $to)) {
            return $fallback;
        }

        return $to;
    }
}
