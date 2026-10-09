<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use App\Services\JwtService;
use App\Services\Localization\NameTransliterationService;
use App\Support\SyrianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => [__('api.invalid_credentials')],
            ]);
        }

        if ($user->isBanned()) {
            throw ValidationException::withMessages([
                'username' => [__('api.account_banned')],
            ]);
        }

        $token = $this->jwtService->generateToken($user);

        return response()->json([
            'success' => true,
            'message' => __('api.logged_in'),
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->localized_name,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token) {
            $this->jwtService->blacklistToken($token);
        }

        return response()->json([
            'success' => true,
            'message' => __('api.logged_out'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->serializeUser($user),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->localized_name,
            'username' => $user->username,
            'role' => $user->role,
            'avatar_url' => $user->avatar_url,
            'personal_number' => $user->personal_number,
        ];
    }

    /**
     * Update own profile — mirrors Web ProfileController@update
     * (name, username, personal_number, avatar, password).
     * POST multipart (avatar) or JSON.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
                'personal_number' => ['nullable', 'string', 'max:20'],
                'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
                'remove_avatar' => ['nullable', 'boolean'],
                'current_password' => ['nullable', 'string'],
                'password' => ['nullable', 'string', 'min:8', 'max:50', 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('api.profile_update_invalid'),
                'errors' => $e->errors(),
            ], 422);
        }

        $rawPhone = $request->input('personal_number');
        $normalized = null;
        if ($rawPhone !== null && trim($rawPhone) !== '') {
            $normalized = SyrianPhone::normalize($rawPhone);
            if (!SyrianPhone::isValidNormalized($normalized)) {
                return response()->json(['success' => false, 'message' => __('api.profile_phone_invalid')], 422);
            }
            $exists = User::where('personal_number', $normalized)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return response()->json(['success' => false, 'message' => __('api.profile_phone_taken')], 422);
            }
        }
        if (!empty($validated['password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return response()->json(['success' => false, 'message' => __('api.profile_password_wrong')], 422);
            }
            $user->password = $validated['password'];
        }
        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->personal_number = $normalized;

        $translit = app(NameTransliterationService::class);
        if ($translit->isArabic($validated['name'])) {
            $user->name_ar = $validated['name'];
            $user->name_en = $translit->generateLatinName($validated['name']);
        } else {
            $user->name_en = $validated['name'];
            $user->name_ar = $translit->generateArabicName($validated['name']);
        }
        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            try {
                if (Storage::disk('public')->exists($user->avatar_path)) {
                    Storage::disk('public')->delete($user->avatar_path);
                }
            } catch (\Throwable $e) {
                Log::warning('Avatar delete failed: '.$e->getMessage(), ['userId' => $user->id]);
            }
            $user->avatar_path = null;
        }
        if ($request->hasFile('avatar')) {
            try {
                $localPath = $request->file('avatar')->store('avatars', 'public');
            } catch (\Throwable $e) {
                Log::error('Avatar upload failed', ['userId' => $user->id, 'message' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => __('api.profile_avatar_failed')], 422);
            }
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                try { Storage::disk('public')->delete($user->avatar_path); } catch (\Throwable $e) {}
            }
            $user->avatar_path = $localPath;
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => __('ui.profile_updated'),
            'data' => $this->serializeUser($user->fresh()),
        ]);
    }

    /**
     * Own notes statistics for the Flutter profile — same counts as Web profile.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $counts = $user->notes()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => array_sum($counts),
                'draft' => $counts['draft'] ?? 0,
                'pending' => $counts['pending'] ?? 0,
                'accepted' => $counts['accepted'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
            ],
        ]);
    }
}
