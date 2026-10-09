<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ranking + writers directory for the Flutter app.
 * Mirrors Web\ProfileController@ranking queries (JSON instead of Blade).
 */
class RankingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $canSeeDrafts = $viewer && ($viewer->isReportWriter() || $viewer->isGeneralManager());

        $monitors = User::where('role', 'monitor')
            ->withCount([
                'notes as total_notes' => function ($q) use ($canSeeDrafts) {
                    if (!$canSeeDrafts) {
                        $q->where('status', '!=', 'draft');
                    }
                },
                'notes as pending_notes' => fn ($q) => $q->where('status', 'pending'),
                'notes as accepted_notes' => fn ($q) => $q->where('status', 'accepted'),
                'notes as rejected_notes' => fn ($q) => $q->where('status', 'rejected'),
            ])
            ->orderByDesc('accepted_notes')
            ->orderByDesc('total_notes')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->localized_name,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'total_notes' => (int) ($u->total_notes ?? 0),
                'pending_notes' => (int) ($u->pending_notes ?? 0),
                'accepted_notes' => (int) ($u->accepted_notes ?? 0),
                'rejected_notes' => (int) ($u->rejected_notes ?? 0),
            ])
            ->values();

        $cacheKey = $canSeeDrafts ? 'ranking-global-stats:privileged' : 'ranking-global-stats:public';
        try {
            $rawCounts = Cache::remember($cacheKey, 300, function () use ($canSeeDrafts) {
                $q = Note::selectRaw('status, COUNT(*) as c')->groupBy('status');
                if (!$canSeeDrafts) {
                    $q->where('status', '!=', 'draft');
                }

                return $q->pluck('c', 'status')->toArray();
            });
        } catch (\Throwable $e) {
            $q = Note::selectRaw('status, COUNT(*) as c')->groupBy('status');
            if (!$canSeeDrafts) {
                $q->where('status', '!=', 'draft');
            }
            $rawCounts = $q->pluck('c', 'status')->toArray();
        }
        $globalStats = [
            'total' => array_sum($rawCounts),
            'pending' => $rawCounts['pending'] ?? 0,
            'accepted' => $rawCounts['accepted'] ?? 0,
            'rejected' => $rawCounts['rejected'] ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'monitors' => $monitors,
                'stats' => $globalStats,
            ],
        ]);
    }

    /**
     * Monitors directory — observer filter for the Flutter notes list.
     * Mirrors Web NotesList@observers (role = monitor).
     */
    public function observers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('search', ''));
        $query = User::where('role', 'monitor')->orderBy('name');
        if (mb_strlen($q) >= 2) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', '%'.$q.'%')
                    ->orWhere('name_ar', 'like', '%'.$q.'%')
                    ->orWhere('name_en', 'like', '%'.$q.'%')
                    ->orWhere('username', 'like', '%'.$q.'%');
            });
        }
        $observers = $query->limit(50)->get(['id', 'name', 'name_en', 'name_ar', 'username', 'avatar_path'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->localized_name,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $observers,
        ]);
    }

    /**
     * Report writers + GMs directory — recipients for general submissions.
     */
    public function writers(Request $request): JsonResponse
    {
        $writers = User::whereIn('role', ['report_writer', 'general_manager'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->localized_name,
                'username' => $u->username,
                'role' => $u->role,
                'avatar_url' => $u->avatar_url,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $writers,
        ]);
    }
}
