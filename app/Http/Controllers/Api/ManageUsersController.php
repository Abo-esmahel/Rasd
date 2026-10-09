<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SyrianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إدارة المستخدمين — للمدير العام وحده (UserPolicy).
 *
 * كلمة السر تُقبل عند الإنشاء فقط. التحديث لا يلمسها إطلاقاً: تغييرها
 * مسار منفصل له (تأكيد الهوية)، ودمجُه هنا يفتح باب إعادة تعيين كلمات
 * الناس بلا علمهم.
 */
class ManageUsersController extends Controller
{
    private const ROLES = ['monitor', 'report_writer'];

    /** قائمة المستخدمين مع بحث وتصفية — GM لا يدير GM ولا يُعرض في القائمة. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');
        $banned = $request->query('banned');

        $query = User::query()
            ->whereIn('role', self::ROLES)
            ->orderBy('name')
            ->orderBy('username');

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(fn ($w) => $w
                ->where('name', 'like', $like)
                ->orWhere('username', 'like', $like)
                ->orWhere('name_ar', 'like', $like)
                ->orWhere('name_en', 'like', $like));
        }
        if (in_array($role, self::ROLES, true)) {
            $query->where('role', $role);
        }
        if ($banned !== null && $banned !== '') {
            $query->whereNotNull('banned_at');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn (User $u) => $this->present($u))->all(),
        ]);
    }

    /** إنشاء مراقب أو كاتب — كلمة السر هنا فقط. */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'role' => ['required', Rule::in(self::ROLES)],
            'personal_number' => ['nullable', 'string', 'max:30'],
        ], [], [
            'username' => __('api.username'),
            'password' => __('api.password'),
            'role' => __('api.role'),
        ]);

        $user = new User([
            'name' => trim((string) $data['name']),
            'username' => trim((string) $data['username']),
            'role' => $data['role'],
        ]);
        $user->password = $data['password'];

        if (! empty($data['personal_number'])) {
            $normalized = SyrianPhone::normalize((string) $data['personal_number']);
            if ($normalized === null) {
                throw ValidationException::withMessages([
                    'personal_number' => [__('api.profile_phone_invalid')],
                ]);
            }
            $dup = User::where('personal_number', $normalized)->whereKeyNot($user->getKey())->exists();
            if ($dup) {
                throw ValidationException::withMessages([
                    'personal_number' => [__('api.profile_phone_taken')],
                ]);
            }
            $user->personal_number = $normalized;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'data' => $this->present($user),
        ], 201);
    }

    /** تحديث بيانات المستخدم — بلا كلمة سر (user-> لا تُلمس). */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'username' => [
                'sometimes', 'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'role' => ['sometimes', 'required', Rule::in(self::ROLES)],
            'personal_number' => ['nullable', 'string', 'max:30'],
        ], [], [
            'username' => __('api.username'),
            'role' => __('api.role'),
        ]);

        foreach (['name', 'username', 'role'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $field === 'name' ? trim((string) $data[$field]) : $data[$field];
            }
        }

        if (array_key_exists('personal_number', $data)) {
            $raw = trim((string) ($data['personal_number'] ?? ''));
            if ($raw === '') {
                $user->personal_number = null;
            } else {
                $normalized = SyrianPhone::normalize($raw);
                if ($normalized === null) {
                    throw ValidationException::withMessages([
                        'personal_number' => [__('api.profile_phone_invalid')],
                    ]);
                }
                $dup = User::where('personal_number', $normalized)->whereKeyNot($user->id)->exists();
                if ($dup) {
                    throw ValidationException::withMessages([
                        'personal_number' => [__('api.profile_phone_taken')],
                    ]);
                }
                $user->personal_number = $normalized;
            }
        }

        $user->save();

        return response()->json([
            'success' => true,
            'data' => $this->present($user->fresh()),
        ]);
    }

    /** حظر: يمنع الدخول فوراً (التوكن القائم يُرفض عند التحقق). */
    public function ban(Request $request, User $user): JsonResponse
    {
        $this->authorize('ban', $user);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $user->forceFill([
            'banned_at' => now(),
            'ban_reason' => $this->cleanReason($data['reason'] ?? null),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => $this->present($user->fresh()),
        ]);
    }

    public function unban(User $user): JsonResponse
    {
        $this->authorize('unban', $user);

        $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();

        return response()->json([
            'success' => true,
            'data' => $this->present($user->fresh()),
        ]);
    }

    private function cleanReason(mixed $reason): ?string
    {
        $reason = trim((string) ($reason ?? ''));

        return $reason === '' ? null : mb_substr($reason, 0, 300);
    }

    /** لا تُعاد كلمة السر ولا بصمتها — ولا حتى فارغة. */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->localized_name ?: $user->username,
            'username' => $user->username,
            'role' => $user->role,
            'personal_number' => $user->personal_number,
            'avatar_url' => $user->avatar_url,
            'banned' => $user->isBanned(),
            'banned_at' => $user->banned_at?->toIso8601String(),
            'ban_reason' => $user->ban_reason,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
