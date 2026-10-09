<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * الملف الشخصي لأي مستخدم — لتطبيق الجوال.
 *
 * المنقاردة للويب في ProfileController@show؛ نفس منطق إخفاء المسودات
 * حتى لا تكشف الأرقام نفسَها في الاتجاهين.
 */
class UserProfileController extends Controller
{
    public function show(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $viewer = Auth::user();

        $isOwn = $viewer && $viewer->id === $user->id;
        $canSeeDrafts = $isOwn || ($viewer && ($viewer->isReportWriter() || $viewer->isGeneralManager()));

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->localized_name,
                    'username' => $user->username,
                    'role' => $user->role,
                    'avatar_url' => $user->avatar_url,
                    'personal_number' => $user->personal_number,
                ],
                'is_own' => $isOwn,
                'stats' => $this->stats($user, $canSeeDrafts),
            ],
        ]);
    }

    /**
     * نفس منطق Web\ProfileController@getStats — المفتاح بلا اسم المشاهد حتى
     * يبطل الكاش للجميع معاً عند تغيّر الملاحظات.
     */
    private function stats(User $user, bool $canSeeDrafts): array
    {
        $cacheKey = 'profile-stats:'.$user->id.':'.($canSeeDrafts ? 'priv' : 'pub');

        $compute = function () use ($user, $canSeeDrafts) {
            $q = $user->notes()->selectRaw('status, COUNT(*) as c')->groupBy('status');
            if (!$canSeeDrafts) {
                $q->where('status', '!=', 'draft');
            }
            $counts = $q->pluck('c', 'status')->toArray();

            return [
                'total' => array_sum($counts),
                'draft' => $canSeeDrafts ? ($counts['draft'] ?? 0) : 0,
                'pending' => $counts['pending'] ?? 0,
                'accepted' => $counts['accepted'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
            ];
        };

        try {
            return Cache::remember($cacheKey, 120, $compute);
        } catch (\Throwable $e) {
            return $compute();
        }
    }
}