<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'ar';

        $query = strtolower(trim((string) $request->query('locale', '')));
        if (in_array($query, self::SUPPORTED, true)) {
            $locale = $query;
        } elseif ($request->user() && in_array($request->user()->locale ?? null, self::SUPPORTED, true)) {
            $locale = $request->user()->locale;
        } else {
            $al = strtolower(trim((string) $request->header('Accept-Language', '')));
            if (str_starts_with($al, 'en')) {
                $locale = 'en';
            }
        }

        App::setLocale($locale);
        try {
            \Carbon\Carbon::setLocale($locale);
        } catch (\Throwable) {
        }

        $response = $next($request);

        try {
            if (method_exists($response, 'headers')) {
                $response->headers->set('Content-Language', $locale);
            }
        } catch (\Throwable) {
        }

        return $response;
    }
}
