<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
        $rawTo = $request->query('to');
        $to = self::safeTarget($rawTo, $default);
        $notifPath = parse_url(route('notifications.index'), PHP_URL_PATH) ?: '/notifications';
        $notifUrl = route('notifications.index');
        $isNotifTarget = $to === $notifPath || $to === $notifUrl || $to === $notifPath . '/';
        $explicitlyRequested = is_string($rawTo) && (trim((string) $rawTo) === $notifPath || trim((string) $rawTo) === $notifUrl || trim((string) $rawTo) === $notifPath . '/');
        if ($isNotifTarget && ! $explicitlyRequested) {
            $to = $default;
        }

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

        return response()->view('errors.404', [
            'wanted' => mb_substr($request->path(), 0, 80),
        ], 404);
    }

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
