<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SyrianPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{

    public function show(Request $request, ?int $id = null)
    {
        if ($id) {
            $user = \App\Models\User::findOrFail($id);
            $stats = $this->getStats($user);
            $isOwn = Auth::id() === $user->id;
            return view('profile.show', compact('user', 'stats', 'isOwn'));
        }
        $user = Auth::user();
        $stats = $this->getStats($user);
        $isOwn = true;
        return view('profile.show', compact('user', 'stats', 'isOwn'));
    }

    public function ranking()
    {
        $monitors = \App\Models\User::where('role', 'monitor')
            ->withCount([
                'notes as total_notes',
                'notes as draft_notes' => fn($q) => $q->where('status', 'draft'),
                'notes as pending_notes' => fn($q) => $q->where('status', 'pending'),
                'notes as accepted_notes' => fn($q) => $q->where('status', 'accepted'),
                'notes as rejected_notes' => fn($q) => $q->where('status', 'rejected'),
            ])
            ->orderByDesc('accepted_notes')
            ->orderByDesc('total_notes')
            ->get();
        $writers = \App\Models\User::where('role', 'report_writer')
            ->withCount([
                'notes as total_notes' => fn($q) => $q->where('user_id', \Illuminate\Support\Facades\DB::raw('users.id')),
            ])
            ->get();
        $rawCounts = \Illuminate\Support\Facades\Cache::remember('ranking-global-stats', 300, fn () => \App\Models\Note::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray());
        $globalStats = [
            'total' => array_sum($rawCounts),
            'draft' => $rawCounts['draft'] ?? 0,
            'pending' => $rawCounts['pending'] ?? 0,
            'accepted' => $rawCounts['accepted'] ?? 0,
            'rejected' => $rawCounts['rejected'] ?? 0,
        ];

        return view('profile.ranking', compact('monitors', 'writers', 'globalStats'));
    }

    private function getStats(\App\Models\User $user): array
    {
        return \Illuminate\Support\Facades\Cache::remember('profile-stats:'.$user->id, 120, function () use ($user) {
            $counts = $user->notes()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();

            return [
                'total' => array_sum($counts),
                'draft' => $counts['draft'] ?? 0,
                'pending' => $counts['pending'] ?? 0,
                'accepted' => $counts['accepted'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
            ];
        });
    }

    public function edit()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'personal_number' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'remove_avatar' => ['nullable', 'boolean'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'max:50', 'confirmed'],
        ]);
        $rawPhone = $request->input('personal_number');
        $normalized = null;
        if ($rawPhone !== null && trim($rawPhone) !== '') {
            $normalized = SyrianPhone::normalize($rawPhone);
            if (!SyrianPhone::isValidNormalized($normalized)) {
                return back()->withErrors(['personal_number' => __('api.profile_phone_invalid')])->withInput();
            }
            $exists = User::where('personal_number', $normalized)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return back()->withErrors(['personal_number' => __('api.profile_phone_taken')])->withInput();
            }
        }
        if (!empty($validated['password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => __('api.profile_password_wrong')])->withInput();
            }
            $user->password = $validated['password'];
        }
        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->personal_number = $normalized;
        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
                }
            } catch (\Throwable $e) {
                Log::warning('Avatar delete failed: '.$e->getMessage(), ['userId' => $user->id]);
            }
            $user->avatar_path = null;
        }
        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            try {
                $localPath = $avatarFile->store('avatars', 'public');
            } catch (\Throwable $e) {
                Log::error('Avatar upload failed', [
                    'userId' => $user->id,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);

                return back()
                    ->withErrors(['avatar' => __('api.profile_avatar_failed')])
                    ->withInput();
            }
            if ($user->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path)) {
                try { \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path); } catch (\Throwable $e) {}
            }
            $user->avatar_path = $localPath;
        }
        $user->save();
        return redirect()->route('profile.show')->with('success', __('ui.profile_updated'));
    }
}
