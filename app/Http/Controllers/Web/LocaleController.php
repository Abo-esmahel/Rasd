<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:ar,en'],
        ]);

        $locale = $validated['locale'];
        App::setLocale($locale);

        $request->session()->put('locale', $locale);

        if ($request->user()) {
            try {
                $request->user()->forceFill(['locale' => $locale])->save();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[LOCALE] DB persist failed, session/cookie fallback used', [
                    'user_id' => $request->user()->getKey(),
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $cookie = cookie('rasd_locale', $locale, 60 * 24 * 365, '/', null, false, false);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'locale' => $locale,
                'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
                'message' => __('ui.lang_switched'),
            ])->cookie($cookie);
        }

        return back()->with('success', __('ui.lang_switched'))->cookie($cookie);
    }
}
