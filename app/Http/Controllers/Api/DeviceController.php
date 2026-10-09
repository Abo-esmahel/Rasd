<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    /**
     * Register FCM device token for Flutter.
     * RASD backend uses WebPush (VAPID) for web, but Flutter uses FCM polling as fallback.
     * This endpoint simply validates and logs the token so the app's strict polling can start.
     * No DB table required - success is enough for the client to proceed with polling.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2000'],
            'platform' => ['sometimes', 'string', 'max:50'],
            'provider' => ['sometimes', 'string', 'max:50'],
        ]);

        $user = $request->user();
        Log::info('[FCM] device registered', [
            'user_id' => $user->id,
            'platform' => $validated['platform'] ?? 'android',
            'provider' => $validated['provider'] ?? 'fcm',
            'token_prefix' => substr($validated['token'], 0, 24) . '...',
        ]);

        try {
            \Illuminate\Support\Facades\Cache::put(
                "fcm_token:{$user->id}:" . substr(md5($validated['token']), 0, 8),
                ['token' => $validated['token'], 'platform' => $validated['platform'] ?? 'android', 'updated_at' => now()->toIso8601String()],
                60 * 24 * 30
            );
        } catch (\Throwable $e) {
        }

        return response()->json(['success' => true, 'message' => 'Device registered']);
    }

    public function unregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        Log::info('[FCM] device unregistered', ['user_id' => $user->id, 'token_prefix' => substr($validated['token'], 0, 24) . '...']);

        return response()->json(['success' => true]);
    }
}
