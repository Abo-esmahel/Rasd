<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحدد لغة الواجهة فقط — لا يمس البيانات إطلاقاً.
 * الأولوية (مستوى الإعدادات): مستخدم مسجل (users.locale) > session > cookie > ar.
 * - المسجل: users.locale هو مصدر الحقيقة ويُزامَن مع session + cookie ليبقى كل مرة وعبر الأجهزة.
 * يضبط أيضاً Carbon (diffForHumans والتواريخ النسبية) على نفس الـlocale.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'ar';
        $fromUser = false;

        $userLocale = $request->user()?->locale ?? null;
        if (in_array($userLocale, self::SUPPORTED, true)) {
            $locale = $userLocale;
            $fromUser = true;
        } elseif ($request->hasSession() && in_array($request->session()->get('locale'), self::SUPPORTED, true)) {
            $locale = $request->session()->get('locale');
        } elseif (in_array($request->cookie('rasd_locale'), self::SUPPORTED, true)) {
            $locale = $request->cookie('rasd_locale');
        }

        App::setLocale($locale);
        try {
            \Carbon\Carbon::setLocale($locale);
        } catch (\Throwable) {
        }
        view()->share('htmlLocale', $locale);
        view()->share('htmlDir', $locale === 'ar' ? 'rtl' : 'ltr');

        // مزامنة مستوى الإعدادات: إذا اللغة من حساب المستخدم، وحّد session عليها
        // حتى لا يعود لغة قديمة بعد أي طلب.
        if ($fromUser && $request->hasSession() && $request->session()->get('locale') !== $locale) {
            $request->session()->put('locale', $locale);
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // وحّد الكوكي (سنة) مع اللغة الفعلية ليبقى بعد انتهاء الجلسة / تسجيل الخروج.
        try {
            if ($request->cookie('rasd_locale') !== $locale && method_exists($response, 'withCookie')) {
                $response->withCookie(cookie('rasd_locale', $locale, 60 * 24 * 365, '/', null, false, false));
            }
        } catch (\Throwable) {
        }

        return $response;
    }
}
