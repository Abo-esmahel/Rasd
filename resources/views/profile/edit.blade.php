@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('profile.show') }}" class="w-9 h-9 rounded-lg bg-white border border-surface-300 flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-surface-100 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-extrabold text-ink-800 leading-none">تعديل الملف الشخصي</h1>
            <p class="text-sm text-ink-400 mt-1">حدّث صورتك واسمك والرقم الشخصي وكلمة المرور</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-surface-300 overflow-hidden">
        <div class="px-6 py-4 border-b border-surface-300 flex items-center gap-3 bg-surface-50">
            <div class="w-9 h-9 rounded-lg bg-sage-100 text-sage-700 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <h2 class="text-sm font-extrabold text-ink-800">بيانات الحساب</h2>
                <p class="text-xs text-ink-400">الدور لا يمكن تغييره — يحدده النظام</p>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf @method('PUT')

            <div class="rounded-2xl border-2 border-dashed border-surface-300 bg-surface-50 p-6">
                <label class="block text-sm font-bold text-ink-800 mb-4 text-center sm:text-right">الصورة الشخصية </label>
                <div class="flex flex-col sm:flex-row items-center gap-6">
                    <div class="relative group shrink-0">
                        <div id="avatar-preview" class="w-28 h-28 sm:w-24 sm:h-24 rounded-[22px] overflow-hidden bg-white border-2 border-white shadow-md flex items-center justify-center">
                            @if($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-3xl sm:text-2xl font-extrabold text-sage-700">{{ $user->initial }}</span>
                            @endif
                        </div>
                        <label for="avatar" class="absolute -bottom-1.5 -right-1.5 w-8 h-8 rounded-full border-2 border-white flex items-center justify-center text-white shadow-md cursor-pointer transition" style="background-color:#1f6f4a" title="تغيير الصورة">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 13a3 3 0 100-6 3 3 0 000 6z"/></svg>
                        </label>
                    </div>
                    <div class="flex-1 w-full sm:text-right text-center">
                        <label for="avatar" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-white text-sm font-bold cursor-pointer transition shadow-sm" style="background-color:#1f6f4a">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            اختيار صورة جديدة
                        </label>
                        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden">
                        <p id="avatar-file-name" class="mt-1.5 text-xs font-bold text-sage-700 hidden"></p>
                        @error('avatar') <p class="mt-2 text-xs text-red-500 font-bold bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ $message }}</p> @enderror
                        @if($user->avatar_path)
                            <label class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-red-200 text-xs font-bold text-red-600 cursor-pointer hover:bg-red-50 transition">
                                <input type="checkbox" name="remove_avatar" value="1" class="rounded border-red-300 text-red-600 focus:ring-red-500/20 w-3.5 h-3.5">
                                حذف الصورة الحالية
                            </label>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-ink-700 mb-1.5">الاسم الكامل <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                        class="block w-full rounded-xl border border-surface-300 bg-white py-3 px-4 text-sm font-bold text-ink-800 placeholder:text-ink-300 focus:border-sage-500 focus:ring-2 focus:ring-sage-500/10 outline-none transition @error('name') border-red-400 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-ink-700 mb-1.5">اسم المستخدم <span class="text-red-500">*</span></label>
                    <input type="text" name="username" value="{{ old('username', $user->username) }}" required dir="ltr"
                        class="block w-full rounded-xl border border-surface-300 bg-white py-3 px-4 text-sm font-bold text-ink-800 placeholder:text-ink-300 focus:border-sage-500 focus:ring-2 focus:ring-sage-500/10 outline-none transition text-left @error('username') border-red-400 @enderror">
                    @error('username') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-bold text-ink-700 mb-1.5">رقم الجوال (واتساب)</label>
                    <input type="text" name="personal_number" value="{{ old('personal_number', $user->personal_number) }}" dir="ltr" maxlength="20" placeholder="09XXXXXXXX"
                        class="block w-full rounded-xl border border-surface-300 bg-white py-3 px-4 text-sm font-bold text-ink-800 placeholder:text-ink-300 focus:border-sage-500 focus:ring-2 focus:ring-sage-500/10 outline-none transition text-left tracking-widest @error('personal_number') border-red-400 @enderror">
                    @error('personal_number') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-xl bg-surface-50 border border-surface-300 p-4">
                <div class="text-xs font-bold text-ink-500 mb-2">الدور الحالي</div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold {{ $user->isMonitor() ? 'bg-sage-50 text-sage-700 border border-sage-200' : 'bg-white text-ink-600 border border-surface-300' }}">
                    {{ $user->isMonitor() ? 'مُراقب ميداني' : 'كاتب تقارير' }}
                </div>
                <p class="text-xs text-ink-400 mt-2">لتغيير الدور تواصل مع الإدارة.</p>
            </div>

            <div class="border-t border-surface-300 pt-5">
                <h3 class="text-sm font-bold text-ink-700 mb-1">تغيير كلمة المرور <span class="text-ink-300 font-medium text-xs">— اختياري</span></h3>
                <p class="text-xs text-ink-400 mb-3">اتركها فارغة إذا لا تريد التغيير</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-bold text-ink-600 mb-1.5">كلمة المرور الحالية</label>
                        <input type="password" name="current_password" autocomplete="current-password"
                            class="block w-full rounded-xl border border-surface-300 bg-white py-3 px-4 text-sm font-bold text-ink-800 placeholder:text-ink-300 focus:border-sage-500 focus:ring-2 focus:ring-sage-500/10 outline-none transition @error('current_password') border-red-400 @enderror"
                            placeholder="••••••••">
                        @error('current_password') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-bold text-ink-600 mb-1.5">كلمة المرور الجديدة</label>
                            <input type="password" name="password" autocomplete="new-password"
                                class="block w-full rounded-xl border border-surface-300 bg-white py-3 px-4 text-sm font-bold text-ink-800 placeholder:text-ink-300 focus:border-sage-500 focus:ring-2 focus:ring-sage-500/10 outline-none transition @error('password') border-red-400 @enderror"
                                placeholder="8 أحرف على الأقل">
                            @error('password') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-ink-600 mb-1.5">تأكيد الجديدة</label>
                            <input type="password" name="password_confirmation" autocomplete="new-password"
                                class="block w-full rounded-xl border border-surface-300 bg-white py-3 px-4 text-sm font-bold text-ink-800 placeholder:text-ink-300 focus:border-sage-500 focus:ring-2 focus:ring-sage-500/10 outline-none transition"
                                placeholder="أعد كتابتها">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t border-surface-300">
                <a href="{{ route('profile.show') }}" class="px-5 py-2.5 rounded-xl border border-surface-300 bg-white text-ink-500 font-bold text-sm hover:bg-surface-100 transition">إلغاء</a>
                <button type="submit" class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl text-white font-bold text-sm transition shadow-sm sm:mr-auto" style="background-color:#1f6f4a">
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
    const avatarInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarFileName = document.getElementById('avatar-file-name');
    const initialHTML = avatarPreview.innerHTML;
    avatarInput?.addEventListener('change', e=>{
        const f=e.target.files[0];
        if(!f){ avatarPreview.innerHTML=initialHTML; avatarFileName.classList.add('hidden'); return; }
        if(f.size>10*1024*1024){ alert('حجم الصورة كبير — الحد 10MB'); e.target.value=''; return; }
        avatarFileName.textContent=f.name+' ('+(f.size/1024).toFixed(0)+' KB)';
        avatarFileName.classList.remove('hidden');
        const r=new FileReader();
        r.onload=ev=>{ avatarPreview.innerHTML=`<img src="${ev.target.result}" class="w-full h-full object-cover">`; };
        r.readAsDataURL(f);
    });
</script>
@endpush
@endsection
