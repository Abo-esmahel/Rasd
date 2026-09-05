<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SyrianPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request, ?int $id = null)
    {
        // إذا تم تمرير id → عرض بروفايل مستخدم آخر (مسموح للجميع)
        if ($id) {
            $user = \App\Models\User::findOrFail($id);
            // السماح برؤية بروفايلات بعض
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

        // إحصائيات عامة
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
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'max:50', 'confirmed'],
        ]);

        // — Syrian phone normalization + validation —
        $rawPhone = $request->input('personal_number');
        $normalized = null;
        if ($rawPhone !== null && trim($rawPhone) !== '') {
            $normalized = SyrianPhone::normalize($rawPhone);
            if (!SyrianPhone::isValidNormalized($normalized)) {
                return back()->withErrors(['personal_number' => 'رقم الجوال غير صحيح'])->withInput();
            }
            // منع التكرار بعد التطبيع (مقارنة بالقيمة المخزنة المطبّعة)
            $exists = User::where('personal_number', $normalized)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return back()->withErrors(['personal_number' => 'هذا الرقم مستخدم مسبقاً'])->withInput();
            }
        }

        // إذا أراد تغيير كلمة المرور يجب تأكيد الحالية
        if (!empty($validated['password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة'])->withInput();
            }
            $user->password = $validated['password'];
        }

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->personal_number = $normalized;

        // — avatar handling: Cloudinary فقط — ممنوع الحفظ المحلي حسب طلب المالك —
        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            try {
                Storage::disk('cloudinary')->delete($user->avatar_path);
            } catch (\Throwable $e) {
                Log::warning('Cloudinary avatar delete failed: '.$e->getMessage(), ['userId' => $user->id]);
            }
            $user->avatar_path = null;
        }
        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            $avatarName = Str::uuid()->toString();
            try {
                // بدون امتداد لتجنب تكرار الامتداد cat.jpg.jpg على Cloudinary
                $path = $avatarFile->storeAs('avatars', $avatarName, 'cloudinary');
                // حذف القديمة فقط بعد نجاح الرفع الجديد
                if ($user->avatar_path && $user->avatar_path !== $path) {
                    try {
                        Storage::disk('cloudinary')->delete($user->avatar_path);
                    } catch (\Throwable $e) {}
                }
                $user->avatar_path = $path;
            } catch (\Throwable $e) {
                // التفاصيل التقنية في السجلات فقط — لا تُعرض للمستخدم (قد تحوي مسارات أو تفاصيل SSL)
                Log::error('Cloudinary avatar upload failed', [
                    'userId' => $user->id,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
                return back()
                    ->withErrors(['avatar' => 'تعذّر رفع الصورة إلى التخزين السحابي (Cloudinary). تحقق من الاتصال وحاول مجدداً.'])
                    ->withInput();
            }
        }

        $user->save();

        return redirect()->route('profile.show')->with('success', 'تم تحديث الملف الشخصي بنجاح');
    }
}
