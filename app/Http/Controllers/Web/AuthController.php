<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember', true))) {
            $request->session()->regenerate();

            $sessionLocale = $request->session()->get('locale');
            $cookieLocale = $request->cookie('rasd_locale');
            $user = Auth::user();

            if (in_array($sessionLocale, ['ar', 'en'], true)) {
                if (($user->locale ?? null) !== $sessionLocale) {
                    $user->forceFill(['locale' => $sessionLocale])->save();
                }
                $locale = $sessionLocale;
            } elseif (in_array($user->locale ?? null, ['ar', 'en'], true)) {
                $locale = $user->locale;
                $request->session()->put('locale', $locale);
            } else {
                $locale = in_array($cookieLocale, ['ar', 'en'], true) ? $cookieLocale : 'ar';
                $request->session()->put('locale', $locale);
                $user->forceFill(['locale' => $locale])->save();
            }

            return redirect()->intended(route('dashboard'))
                ->cookie(cookie('rasd_locale', $locale, 60 * 24 * 365, '/', null, false, false));
        }

        return back()->withErrors([
            'username' => __('api.invalid_credentials'),
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        $locale = $request->session()->get('locale', $request->cookie('rasd_locale', Auth::user()?->locale ?? 'ar'));
        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = 'ar';
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('locale', $locale);

        return redirect()->route('login')
            ->cookie(cookie('rasd_locale', $locale, 60 * 24 * 365, '/', null, false, false));
    }
}
