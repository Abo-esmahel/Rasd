<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Media\CloudinaryMediaService;
use App\Support\SyrianPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(private CloudinaryMediaService $media) {}

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
        $globalStats = [
            'total' => \App\Models\Note::count(),
            'draft' => \App\Models\Note::where('status', 'draft')->count(),
            'pending' => \App\Models\Note::where('status', 'pending')->count(),
            'accepted' => \App\Models\Note::where('status', 'accepted')->count(),
            'rejected' => \App\Models\Note::where('status', 'rejected')->count(),
        ];
        return view('profile.ranking', compact('monitors', 'writers', 'globalStats'));
    }

    private function getStats(\App\Models\User $user): array
    {
        return [
            'total' => $user->notes()->count(),
            'draft' => $user->notes()->where('status', 'draft')->count(),
            'pending' => $user->notes()->where('status', 'pending')->count(),
            'accepted' => $user->notes()->where('status', 'accepted')->count(),
            'rejected' => $user->notes()->where('status', 'rejected')->count(),
        ];
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
                return back()->withErrors(['personal_number' => 'رقم الجوال غير صحيح'])->withInput();
            }
            $exists = User::where('personal_number', $normalized)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return back()->withErrors(['personal_number' => 'هذا الرقم مستخدم مسبقاً'])->withInput();
            }
        }
        if (!empty($validated['password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة'])->withInput();
            }
            $user->password = $validated['password'];
        }
        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->personal_number = $normalized;
        if ($request->boolean('remove_avatar') && ($user->avatar_path || $user->avatar_public_id)) {
            try {
                $publicId = $user->avatar_public_id ?: $user->getCloudinaryPath();
                $resourceType = $user->avatar_resource_type ?: 'image';
                $this->media->delete($publicId, $resourceType);
            } catch (\Throwable $e) {
                Log::warning('Cloudinary avatar delete failed: '.$e->getMessage(), ['userId' => $user->id]);
            }
            $user->avatar_path = null;
            $user->avatar_public_id = null;
            $user->avatar_resource_type = null;
            $user->avatar_secure_url = null;
        }
        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            // محلي أولاً (يعمل بدون إنترنت) - fallback إلى Cloudinary إذا توفر
            $localPath = null;
            try {
                $localPath = $avatarFile->store('avatars', 'public');
            } catch (\Throwable $e) {
                Log::warning('Local avatar store failed, trying Cloudinary', ['message'=>$e->getMessage()]);
            }
            if ($localPath) {
                // حذف القديم المحلي إن وجد
                if ($user->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path)) {
                    try { \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path); } catch (\Throwable $e) {}
                }
                // حذف القديم السحابي إن وجد
                if ($user->avatar_public_id) {
                    try { $this->media->delete($user->avatar_public_id, $user->avatar_resource_type ?: 'image'); } catch (\Throwable $e) {}
                }
                $user->avatar_path = $localPath;
                $user->avatar_public_id = null;
                $user->avatar_resource_type = null;
                $user->avatar_secure_url = null;
            } else {
                try {
                    $result = $this->media->upload($avatarFile, 'avatars');
                    $oldPublicId = $user->avatar_public_id ?: $user->getCloudinaryPath();
                    $oldResourceType = $user->avatar_resource_type ?: 'image';
                    if ($oldPublicId && $oldPublicId !== $result->publicId) {
                        try { $this->media->delete($oldPublicId, $oldResourceType); } catch (\Throwable $e) {}
                    }
                    $user->avatar_path = $result->publicId;
                    $user->avatar_public_id = $result->publicId;
                    $user->avatar_resource_type = $result->resourceType;
                    $user->avatar_secure_url = $result->secureUrl;
                } catch (\Throwable $e) {
                    Log::error('Avatar upload failed (local+cloudinary)', [
                        'userId' => $user->id,
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);
                    return back()
                        ->withErrors(['avatar' => 'تعذّر رفع الصورة. حاول بصورة أصغر (حتى 10MB) بصيغة JPG/PNG/WEBP.'])
                        ->withInput();
                }
            }
        }
        $user->save();
        return redirect()->route('profile.show')->with('success', 'تم تحديث الملف الشخصي بنجاح');
    }
}
