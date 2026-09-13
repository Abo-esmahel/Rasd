<?php

namespace App\Http\Middleware\Api;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => __('api.unauthorized'),
            ], 401);
        }

        $user = $this->jwtService->validateToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('api.token_invalid'),
            ], 401);
        }

        auth()->setUser($user);

        return $next($request);
    }
}
