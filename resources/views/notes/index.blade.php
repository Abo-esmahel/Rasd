@extends('layouts.app')

@section('content')
@php
    $isMonitor = auth()->user()->isMonitor();
    $isWriter = !$isMonitor;
    $userId = auth()->id();

    $currentStatus = request('status');
    $counts = \App\Models\Note::query()->whereNull('general_submission_id')
        ->where(function($q) use ($userId){ $q->where('user_id',$userId)->orWhere('status','!=','draft'); })
        ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) as accepted, SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected")
        ->first();
    $totalCount = $counts->total ?? 0;
    $pendingCount = $counts->pending ?? 0;
    $acceptedCount = $counts->accepted ?? 0;
    $rejectedCount = $counts->rejected ?? 0;
    $draftCount = \App\Models\Note::where('user_id',$userId)->whereNull('general_submission_id')->where('status','draft')->count();
@endphp


<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-xl font-extrabold text-ink-800 leading-tight">الملاحظات</h1>
        <p class="text-sm text-[#737373] mt-1">
            @if($isMonitor) تابع ملاحظاتك الميدانية وإدارتها @else ملاحظاتك — المسودات والمقبولة @endif
        </p>
    </div>
    @if($isMonitor || $isWriter)
        <a href="{{ route('notes.create') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm shadow-sm transition shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            ملاحظة جديدة
        </a>
    @endif
    </div>


    @if(isset($observerUser) && $observerUser)
        <div class="mt-4 p-4 bg-white border border-surface-300 rounded-xl flex items-center gap-3 shadow-sm">
            @if($observerUser->avatar_url)
                <a href="{{ route('profile.showUser', $observerUser->id) }}" class="shrink-0 hover:opacity-80 transition" aria-label="عرض الملف الشخصي"><img src="{{ $observerUser->avatar_url }}" alt="{{ $observerUser->name }}" class="w-10 h-10 rounded-xl object-cover border border-surface-300 shrink-0"></a>
            @else
                <div class="w-10 h-10 rounded-xl bg-sage-100 text-sage-700 flex items-center justify-center font-bold shrink-0">{{ $observerUser->initial }}</div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="text-sm font-extrabold text-ink-800">ملاحظات {{ $observerUser->name }}</div>
                <div class="text-xs text-ink-400 mt-0.5">يُعرض فقط ملاحظات هذا المراقب</div>
            </div>
            <a href="{{ route('notes.index', request()->except(['observer','user','page'])) }}" class="shrink-0 px-3 py-2 rounded-lg bg-surface-100 border border-surface-300 text-ink-600 text-xs font-bold hover:bg-white transition">مسح الفلتر ✕</a>
        </div>
    @endif


<div class="border-b border-[#e6e9e1] mb-6 -mx-4 sm:mx-0 px-4 sm:px-0 overflow-x-auto scrollbar-hide">
    <nav class="flex gap-6 min-w-max" aria-label="حالات الملاحظات">
        @php
            $tabs = [
                ['key'=>null, 'label'=>'الكل', 'count'=>$totalCount],
                ['key'=>'draft', 'label'=>'مسودات', 'count'=>$draftCount],
                ['key'=>'pending', 'label'=>'قيد المراجعة', 'count'=>$pendingCount],
                ['key'=>'accepted', 'label'=>'مقبولة', 'count'=>$acceptedCount],
                ['key'=>'rejected', 'label'=>'مرفوضة', 'count'=>$rejectedCount],
            ];
        @endphp
        @foreach($tabs as $tab)
            @php
                $isActive = $currentStatus === $tab['key'];
                $url = $tab['key'] === null
                    ? route('notes.index')
                    : route('notes.index', array_merge(request()->except('status','page'), ['status' => $tab['key']]));
            @endphp
            <a href="{{ $url }}" data-ajax-tab class="relative flex items-center gap-1.5 py-3 text-[13px] whitespace-nowrap border-b-2 transition {{ $isActive ? 'border-[#0e6a38] text-ink-800 font-bold' : 'border-transparent text-[#737373] hover:text-ink-600 font-medium' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="text-[11px] font-mono tabular-nums {{ $isActive ? 'text-[#0e6a38]' : 'text-ink-300' }}">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </nav>
</div>


<div class="mb-5">
    <details class="group">
        <summary class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-[#737373] hover:text-ink-700 hover:bg-[#eceee9] cursor-pointer transition list-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            @if(request()->hasAny(['date','floor_number','camera_number','observer','sort']))
                <span class="w-1.5 h-1.5 rounded-full bg-[#0e6a38]"></span>
            @endif
            <svg class="w-3.5 h-3.5 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="mt-3 p-4 bg-surface-50 rounded-xl border border-surface-300">
            <form method="GET" action="{{ route('notes.index') }}">
                @if($currentStatus)
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-[#525252] mb-1.5">المراقب</label>
                        <select name="observer" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                            <option value="">الكل</option>
                            @foreach($observers as $obs)
                                @if(is_object($obs) && isset($obs->id))
                                    <option value="{{ $obs->id }}" {{ request('observer') == $obs->id ? 'selected' : '' }}>{{ $obs->name }}</option>
                                @elseif(is_array($obs) && isset($obs['id']))
                                    <option value="{{ $obs['id'] }}" {{ request('observer') == $obs['id'] ? 'selected' : '' }}>{{ $obs['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#525252] mb-1.5">ترتيب حسب</label>
                        <select name="sort" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                            <option value="" {{ !request('sort') ? 'selected' : '' }}>الأحدث</option>
                            <option value="observer" {{ request('sort')=='observer' ? 'selected' : '' }}>المراقب (أ-ي)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#525252] mb-1.5">تاريخ الملاحظة</label>
                        <input type="date" name="date" value="{{ request('date') }}" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                    <div>
                        <label class="block text-xs font-bold text-[#525252] mb-1.5">رقم الطابق</label>
                        <input type="number" name="floor_number" value="{{ request('floor_number') }}" min="1" placeholder="مثال: 3" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#525252] mb-1.5">رقم الكاميرا</label>
                        <input type="number" name="camera_number" value="{{ request('camera_number') }}" min="1" placeholder="مثال: 12" class="w-full rounded-lg border border-[#e6e9e1] bg-white py-2 px-3 text-sm text-ink-800 placeholder:text-ink-300 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition">
                    </div>
                    <div class="flex gap-2 items-end">
                        <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm py-2 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            تطبيق
                        </button>
                        <a href="{{ route('notes.index', request()->has('status') ? ['status' => request('status')] : []) }}" class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-[#e6e9e1] text-[#737373] font-medium text-sm hover:bg-[#f5f7f5] transition">مسح</a>
                    </div>
                </div>
            </form>
        </div>
    </details>
</div>


@if($notes->count() === 0)
    <div class="bg-surface-50 rounded-xl border border-surface-300 p-10 text-center">
        <div class="w-14 h-14 rounded-2xl bg-surface-100 flex items-center justify-center mx-auto">
            <svg class="w-7 h-7 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="mt-4 text-base font-bold text-ink-700">لا توجد ملاحظات</h3>
        <p class="mt-1.5 text-sm text-[#737373] max-w-sm mx-auto">
            @if(request()->hasAny(['date','floor_number','camera_number']))
                لا توجد نتائج مطابقة للبحث — جرّب تغيير معايير الفلترة.
            @elseif($isMonitor)
                لم تُنشئ أي ملاحظات بعد. ابدأ بإنشاء أول ملاحظة ميدانية.
            @else
                لا توجد ملاحظات قيد المراجعة حالياً.
            @endif
        </p>
        <div class="mt-5 flex items-center justify-center gap-2">
            @if(request()->hasAny(['date','floor_number','camera_number']))
                <a href="{{ route('notes.index', request()->has('status') ? ['status' => request('status')] : []) }}" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-[#525252] font-medium text-sm hover:bg-[#f5f7f5] transition">مسح الفلاتر</a>
            @endif
            @if($isMonitor)
                <a href="{{ route('notes.create') }}" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white font-bold text-sm hover:bg-[#0a4d28] transition">+ ملاحظة جديدة</a>
            @endif
        </div>
    </div>
@else

    <div class="bg-white rounded-xl border border-[#e6e9e1] shadow-sm overflow-hidden hidden sm:block">

        <div class="grid grid-cols-[auto_1fr_auto_auto] gap-4 items-center px-5 py-2.5 bg-[#f5f7f5] border-b border-[#e6e9e1] text-xs font-bold text-ink-400">
            <div class="w-20">#</div>
            <div>الملاحظة</div>
            <div class="w-28 text-center">الحالة</div>
            <div class="w-36 text-center">إجراءات</div>
        </div>

        @foreach($notes as $note)
            <div class="note-row grid grid-cols-[auto_1fr_auto_auto] gap-4 items-center px-5 py-3.5 border-b border-surface-300 last:border-b-0 cursor-pointer" onclick="openModal('detail-{{ $note->id }}')">

                <div class="w-14">
                    <span class="text-[11px] font-mono text-ink-300">#{{ $note->id }}</span>
                </div>


                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 text-sm font-extrabold text-ink-800">
                            <span class="w-2 h-2 rounded-full {{ $note->isAccepted() ? 'bg-sage-600' : ($note->isRejected() ? 'bg-red-400' : ($note->isPending() ? 'bg-amber-400' : 'bg-ink-300')) }}"></span>
                            كاميرا {{ $note->camera_number }} · طابق {{ $note->floor_number }}
                        </span>
                        <span class="text-xs px-1.5 py-0.5 rounded bg-surface-100 border border-surface-300 text-ink-500 font-mono">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? '→'.$note->observed_end_at->toTime12() : '' }}</span>
                        <span class="text-xs text-ink-300">{{ $note->created_at->diffForHumans() }}</span>
                        <span class="hidden md:inline text-xs text-ink-200">·</span>
                        <span class="hidden md:inline text-[11px] text-ink-400/80 font-mono" dir="ltr" title="وقت الإنشاء">{{ $note->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-ink-700 line-clamp-1 leading-relaxed">{{ \Illuminate\Support\Str::limit($note->description, 90) }}</p>
                    <div class="mt-1.5 flex items-center gap-2 text-xs text-ink-300">
                        <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="inline-flex items-center gap-1.5 hover:opacity-80 hover:text-ink-700 transition">
                            @if($note->owner->avatar_url)
                                <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->name }}" class="w-4 h-4 rounded-full object-cover border border-[#e6e9e1]">
                            @else
                                <span class="w-4 h-4 rounded-full bg-sage-50 text-sage-700 flex items-center justify-center text-[9px] font-bold">{{ $note->owner->initial }}</span>
                            @endif
                            {{ $note->owner->name }}
                        </a>
                        @if($note->attachments->count() > 0)
                            <span class="text-ink-200">·</span>
                            <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>{{ $note->attachments->count() }} مرفق</span>
                        @endif
                    </div>
                </div>


                <div class="w-28 flex justify-center">
                    @if($note->isDraft())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-ink-100 text-ink-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-ink-300"></span>مسودة
                        </span>
                    @elseif($note->isPending())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>قيد المراجعة
                        </span>
                    @elseif($note->isAccepted())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-sage-50 text-sage-700 border border-sage-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-sage-600"></span>مقبولة
                        </span>
                    @elseif($note->isRejected())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>مرفوضة
                        </span>
                    @endif
                </div>


                <div class="w-36 flex justify-center" onclick="event.stopPropagation()">
                @if(!$note->isRejected() && $note->owner->whatsapp_number)
                    @php
                        $statusLabel = $note->isDraft() ? 'مسودة' : ($note->isPending() ? 'قيد المراجعة' : 'مقبولة');
                        $shareText = "ملاحظة #".str_pad($note->id, 4, '0', STR_PAD_LEFT)."\n";
                        $shareText .= "الطابق: {$note->floor_number} — الكاميرا: {$note->camera_number}\n";
                        $shareText .= "الملاحظة: ".$note->observed_at->toDatetime12();
                        if($note->observed_end_at) $shareText .= ' — '.$note->observed_end_at->toTime12();
                        $shareText .= "\n".$note->description;
                        $shareText .= "\nالحالة: ".$statusLabel;
                        $shareAttachments = $note->attachments->map(fn($a) => ['id'=>$a->id, 'name'=>$a->original_name, 'mime'=>$a->mime_type, 'url'=>'/s/attachments/'.$a->id])->toArray();
                    @endphp
                    <button type="button" onclick='openShareModal(@json($shareText), @json($shareAttachments), "{{ $note->owner->whatsapp_number }}")' class="px-3 py-1.5 rounded-lg bg-[#25D366] text-white text-xs font-bold hover:bg-[#1da851] transition inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </button>
                @endif
                    @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-lg bg-surface-50 border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">تعديل</a>
                            <form method="POST" action="{{ route('notes.send', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-sage-600 text-white text-xs font-bold hover:bg-sage-700 transition">إرسال</button>
                            </form>
                        </div>
                    @elseif($isMonitor && $note->user_id === $userId && $note->isPending())
                        <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-lg bg-surface-50 border border-surface-300 text-ink-600 text-xs font-bold hover:bg-surface-100 transition">تعديل</a>
                    @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('notes.edit', $note) }}" class="px-3 py-1.5 rounded-lg bg-ink-800 text-white text-xs font-bold hover:bg-ink-900 transition">تصحيح</a>
                            <form method="POST" action="{{ route('notes.resend', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs font-bold hover:bg-amber-600 transition">إعادة إرسال</button>
                            </form>
                        </div>
                    @elseif($isWriter && $note->isPending())
                        <div class="flex items-center gap-1.5">
                            <form method="POST" action="{{ route('notes.accept', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-sage-600 text-white text-xs font-bold hover:bg-sage-700 transition">قبول</button>
                            </form>
                            <button type="button" onclick="openModal('reject-{{ $note->id }}')" class="px-3 py-1.5 rounded-lg bg-red-500 text-white text-xs font-bold hover:bg-red-600 transition">رفض</button>
                        </div>
                    @elseif($note->isAccepted())
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-sage-600">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            مُعتمدة
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>


    <div class="sm:hidden space-y-3">
        @foreach($notes as $note)
            <div class="bg-white rounded-xl border border-[#e6e9e1] shadow-sm p-4 hover:shadow-md hover:border-[#d4ddd3] transition" onclick="openModal('detail-{{ $note->id }}')" role="button">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-mono text-ink-300">#{{ $note->id }}</span>
                        @if($note->isDraft())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-ink-100 text-[#525252]">مسودة</span>
                        @elseif($note->isPending())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">قيد المراجعة</span>
                        @elseif($note->isAccepted())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#eef4f0] text-[#0e6a38] border border-[#cde7d6]">مقبولة</span>
                        @elseif($note->isRejected())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 border border-red-200">مرفوضة</span>
                        @endif
                    </div>
                    <span class="text-xs text-ink-300">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? ' — '.$note->observed_end_at->toTime12() : '' }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-ink-600 mb-1.5">
                    <span class="font-semibold">كاميرا {{ $note->camera_number }}</span>
                    <span class="text-ink-200">·</span>
                    <span>الطابق {{ $note->floor_number }}</span>
                    <span class="text-ink-200">·</span>
                    <span class="text-[11px] text-ink-400/80 font-mono" dir="ltr" title="وقت الإنشاء: {{ $note->created_at->format('Y-m-d H:i') }}">{{ $note->created_at->format('Y-m-d') }}</span>
                </div>
                <p class="text-sm text-[#737373] line-clamp-2 leading-relaxed">{{ \Illuminate\Support\Str::limit($note->description, 100) }}</p>
                <div class="mt-2.5 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs text-ink-300">
                        <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="inline-flex items-center gap-1 hover:opacity-80 hover:text-ink-700 transition">
                            @if($note->owner->avatar_url)
                                <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->name }}" class="w-4 h-4 rounded-full object-cover border border-surface-300">
                            @else
                                <span class="w-4 h-4 rounded-full bg-sage-100 text-sage-700 flex items-center justify-center text-[9px] font-bold">{{ $note->owner->initial }}</span>
                            @endif
                            {{ $note->owner->name }}
                        </a>
                        @if($note->attachments->count() > 0)
                            <span class="text-ink-200">·</span>
                            <span>📎 {{ $note->attachments->count() }}</span>
                        @endif
                    </div>
                    <div onclick="event.stopPropagation()">
                        @if(!$note->isRejected() && $note->owner->whatsapp_number)
                            @php
                                $statusLabel = $note->isDraft() ? 'مسودة' : ($note->isPending() ? 'قيد المراجعة' : 'مقبولة');
                                $shareText = "ملاحظة #".str_pad($note->id, 4, '0', STR_PAD_LEFT)."\n";
                                $shareText .= "الطابق: {$note->floor_number} — الكاميرا: {$note->camera_number}\n";
                                $shareText .= "الملاحظة: ".$note->observed_at->toDatetime12();
                                if($note->observed_end_at) $shareText .= ' — '.$note->observed_end_at->toTime12();
                                $shareText .= "\n".$note->description;
                                $shareText .= "\nالحالة: ".$statusLabel;
                                $shareAttachments = $note->attachments->map(fn($a) => ['id'=>$a->id, 'name'=>$a->original_name, 'mime'=>$a->mime_type, 'url'=>'/s/attachments/'.$a->id])->toArray();
                            @endphp
                            <button type="button" onclick='openShareModal(@json($shareText), @json($shareAttachments), "{{ $note->owner->whatsapp_number }}")' class="px-2.5 py-1 rounded-lg bg-[#25D366] text-white text-xs font-bold inline-flex items-center gap-1">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </button>
                        @endif
                        @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('notes.edit', $note) }}" class="px-2.5 py-1 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#525252] text-xs font-bold">تعديل</a>
                                <form method="POST" action="{{ route('notes.send', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-[#0e6a38] text-white text-xs font-bold">إرسال</button>
                                </form>
                            </div>
                        @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('notes.edit', $note) }}" class="px-2.5 py-1 rounded-lg bg-ink-800 text-white text-xs font-bold">تصحيح</a>
                                <form method="POST" action="{{ route('notes.resend', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-amber-500 text-white text-xs font-bold">إعادة</button>
                                </form>
                            </div>
                        @elseif($isWriter && $note->isPending())
                            <div class="flex items-center gap-1.5">
                                <form method="POST" action="{{ route('notes.accept', $note) }}" class="inline" data-ajax data-note-id="{{ $note->id }}">@csrf
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-[#0e6a38] text-white text-xs font-bold">قبول</button>
                                </form>
                                <button type="button" onclick="openModal('reject-{{ $note->id }}')" class="px-2.5 py-1 rounded-lg bg-red-500 text-white text-xs font-bold">رفض</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    @if($notes->hasPages())
        <div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-[#737373]">
                عرض <span class="font-bold text-ink-700">{{ $notes->firstItem() ?? 0 }}–{{ $notes->lastItem() ?? 0 }}</span> من <span class="font-bold text-ink-700">{{ $notes->total() }}</span>
            </div>
            <div>{{ $notes->withQueryString()->links() }}</div>
        </div>
    @endif
@endif


@foreach($notes as $note)
    @php
        $notePrintData = [
            'id' => $note->id,
            'floor_number' => $note->floor_number,
            'camera_number' => $note->camera_number,
            'observed_date' => $note->observed_at->format('Y-m-d'),
            'observed_time_start' => $note->observed_at->toTime12(),
            'observed_time_end' => $note->observed_end_at ? $note->observed_end_at->toTime12() : '—',
            'description' => $note->description,
            'status' => $note->status,
            'status_label' => $note->isDraft() ? 'مسودة' : ($note->isPending() ? 'قيد المراجعة' : ($note->isAccepted() ? 'مقبولة' : 'مرفوضة')),
            'owner_name' => $note->owner->name,
            'attachments' => $note->attachments->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'mime' => $a->mime_type,
                'file_size' => $a->file_size,
                'url' => route('notes.attachments.view', $a)
            ])->toArray(),
        ];
    @endphp
    <div id="detail-{{ $note->id }}" data-modal class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" onclick="closeModal('detail-{{ $note->id }}')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

            <div class="px-6 py-4 border-b border-[#e6e9e1] flex items-start justify-between gap-4 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="shrink-0 hover:opacity-80 transition">
                        @if($note->owner->avatar_url)
                            <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->name }}" class="w-10 h-10 rounded-xl object-cover border border-[#e6e9e1] shadow-sm shrink-0">
                        @else
                            <div class="w-10 h-10 rounded-xl bg-[#eef4f0] text-[#0e6a38] flex items-center justify-center font-bold text-sm shrink-0">
                                {{ $note->owner->initial }}
                            </div>
                        @endif
                    </a>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base font-extrabold text-ink-800">كاميرا {{ $note->camera_number }} · طابق {{ $note->floor_number }} <span class="text-xs font-mono text-ink-300">#{{ $note->id }}</span></h2>
                            @if($note->isDraft())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-ink-100 text-ink-600">مسودة</span>
                            @elseif($note->isPending())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">قيد المراجعة</span>
                            @elseif($note->isAccepted())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-sage-50 text-sage-700 border border-sage-200">مقبولة</span>
                            @elseif($note->isRejected())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 border border-red-200">مرفوضة</span>
                            @endif
                        </div>
                        <div class="text-xs text-[#737373] mt-0.5"><a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="hover:text-[#0e6a38] hover:underline transition">{{ $note->owner->name }}</a> — أُنشئت {{ $note->created_at->toDatetime12() }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('notes.show', $note) }}" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-400 hover:text-ink-700 transition" title="فتح صفحة التفاصيل المستقلة">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                    <button type="button" onclick="closeModal('detail-{{ $note->id }}')" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-300 hover:text-ink-700 transition shrink-0" aria-label="إغلاق">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>


            <div class="p-6 overflow-y-auto flex-1 space-y-5">

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">رقم الكاميرا</div>
                        <div class="text-lg font-extrabold text-ink-800">{{ $note->camera_number }}</div>
                    </div>
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">الطابق</div>
                        <div class="text-lg font-extrabold text-ink-800">{{ $note->floor_number }}</div>
                    </div>
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">وقت الملاحظة</div>
                        <div class="text-sm font-bold text-ink-800">{{ $note->observed_at->toTime12() }}{{ $note->observed_end_at ? ' — '.$note->observed_end_at->toTime12() : '' }}</div>
                        <div class="text-[11px] text-[#737373]">{{ $note->observed_at->format('Y-m-d') }}</div>
                    </div>
                    <div class="rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] p-3 text-center">
                        <div class="text-[11px] font-bold text-ink-300 mb-1">المرفقات</div>
                        <div class="text-lg font-extrabold text-ink-800">{{ $note->attachments->count() }}</div>
                        <div class="text-[11px] text-[#737373]">ملف</div>
                    </div>
                </div>


                <div class="flex items-center gap-3 p-3 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1]">
                    <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="shrink-0 hover:opacity-80 transition">
                        @if($note->owner->avatar_url)
                            <img src="{{ $note->owner->avatar_url }}" alt="{{ $note->owner->name }}" class="w-9 h-9 rounded-lg object-cover border border-[#e6e9e1] shadow-sm shrink-0">
                        @else
                            <div class="w-9 h-9 rounded-lg bg-[#eef4f0] text-[#0e6a38] flex items-center justify-center font-bold text-sm shrink-0">{{ $note->owner->initial }}</div>
                        @endif
                    </a>
                    <div>
                        <div class="text-[11px] font-bold text-ink-300">المُلاحظ</div>
                        <a href="{{ route('profile.showUser', $note->owner->id) }}" onclick="event.stopPropagation()" class="text-sm font-bold text-ink-800 hover:text-[#0e6a38] transition">{{ $note->owner->name }}</a>
                    </div>
                    <div class="mr-auto text-left">
                        <div class="text-[11px] font-bold text-ink-300">تاريخ الإنشاء</div>
                        <div class="text-xs font-medium text-[#525252]">{{ $note->created_at->toDatetime12() }}</div>
                    </div>
                </div>


                <div>
                    <h3 class="text-sm font-bold text-ink-700 mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        وصف الملاحظة
                    </h3>
                    <div class="p-4 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] text-sm leading-[1.9] text-ink-700 whitespace-pre-wrap break-words">{{ $note->description }}</div>
                </div>


                @if($note->isRejected() && $note->rejection_reason)
                    <div class="p-4 rounded-xl bg-red-50 border border-red-200">
                        <h3 class="text-sm font-bold text-red-700 mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            سبب الرفض
                        </h3>
                        <p class="text-sm leading-7 text-red-600">{{ $note->rejection_reason }}</p>
                        @if($note->processor)
                            <div class="mt-2 text-xs font-bold text-red-500">بواسطة {{ $note->processor->name }} — {{ $note->processed_at?->toDatetime12() }}</div>
                        @endif
                    </div>
                @endif


                @if($note->attachments->count() > 0)
                    <div>
                        <h3 class="text-sm font-bold text-ink-700 mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            المرفقات ({{ $note->attachments->count() }})
                        </h3>
                        <div class="space-y-2">
                            @foreach($note->attachments as $attachment)
                                <div class="flex items-center gap-3 p-3 rounded-xl border border-[#e6e9e1] hover:border-[#cde7d6] hover:bg-[#eef4f0]/30 transition group">
                                    <div class="w-9 h-9 rounded-lg bg-[#f5f7f5] border border-[#e6e9e1] flex items-center justify-center shrink-0">
                                        @if(str_contains($attachment->mime_type, 'video'))
                                            <svg class="w-4 h-4 text-[#737373]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 13a3 3 0 100-6 3 3 0 000 6z"/></svg>
                                        @elseif(str_contains($attachment->mime_type, 'audio'))
                                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                        @else
                                            <svg class="w-4 h-4 text-[#0e6a38]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-bold text-ink-700 truncate">{{ $attachment->original_name }}</div>
                                        <div class="text-xs text-[#737373]">{{ $attachment->mime_type }} — {{ number_format($attachment->file_size/1024, 1) }} KB</div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button type="button" onclick="openAttachmentView('{{ route('notes.attachments.view', $attachment) }}', '{{ $attachment->mime_type }}', '{{ addslashes($attachment->original_name) }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 text-xs font-bold hover:bg-[#f5f7f5] hover:border-[#d4ddd3] transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            عرض
                                        </button>
                                        @if(auth()->user()->isReportWriter())
                                            <a href="{{ route('notes.attachments.download', $attachment) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition" title="تنزيل (للمدير فقط)">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif


                @if($note->processor)
                    <div class="p-3 rounded-xl bg-[#f5f7f5] border border-[#e6e9e1] flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-[#eceee9] flex items-center justify-center text-xs font-bold text-[#525252]">{{ mb_substr($note->processor->name, 0, 1) }}</div>
                        <div>
                            <div class="text-[11px] font-bold text-ink-300">{{ $note->isAccepted() ? 'تم الاعتماد بواسطة' : 'تم الرفض بواسطة' }}</div>
                            <div class="text-sm font-bold text-ink-700">{{ $note->processor->name }} — {{ $note->processed_at?->toDatetime12() }}</div>
                        </div>
                    </div>
                @endif
            </div>


            <div class="px-6 py-4 border-t border-[#e6e9e1] flex flex-wrap items-center gap-2 shrink-0 bg-[#f5f7f5]">
                @if(!$note->isRejected() && $note->owner->whatsapp_number)
                    @php
                        $statusLabel = $note->isDraft() ? 'مسودة' : ($note->isPending() ? 'قيد المراجعة' : 'مقبولة');
                        $shareText = "ملاحظة #".str_pad($note->id, 4, '0', STR_PAD_LEFT)."\n";
                        $shareText .= "الطابق: {$note->floor_number} — الكاميرا: {$note->camera_number}\n";
                        $shareText .= "الملاحظة: ".$note->observed_at->toDatetime12();
                        if($note->observed_end_at) $shareText .= ' — '.$note->observed_end_at->toTime12();
                        $shareText .= "\n".$note->description;
                        $shareText .= "\nالحالة: ".$statusLabel;
                        $shareAttachments = $note->attachments->map(fn($a) => ['id'=>$a->id, 'name'=>$a->original_name, 'mime'=>$a->mime_type, 'url'=>'/s/attachments/'.$a->id])->toArray();
                    @endphp
                    <button type="button" onclick='openShareModal(@json($shareText), @json($shareAttachments), "{{ $note->owner->whatsapp_number }}")' class="px-4 py-2 rounded-lg bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        واتساب
                    </button>
                @endif
                <button type="button" onclick='openPrintModal(@json($notePrintData))' class="px-3.5 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition inline-flex items-center gap-1.5 shadow-sm" title="طباعة الملاحظة كوثيقة رسمية A4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>طباعة الملاحظة</span>
                </button>
                @if($isMonitor && $note->user_id === $userId && $note->isDraft())
                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">تعديل</a>
                    <form method="POST" action="{{ route('notes.send', $note) }}" class="inline">@csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">إرسال للمراجعة</button>
                    </form>
                    <form method="POST" action="{{ route('notes.destroy', $note) }}" class="inline mr-auto" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 text-sm font-bold hover:bg-red-50 transition">حذف</button>
                    </form>
                @elseif($isMonitor && $note->user_id === $userId && $note->isPending())
                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">تعديل</a>
                    <span class="mr-auto text-xs font-bold text-amber-600 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>بانتظار القرار</span>
                @elseif($isMonitor && $note->user_id === $userId && $note->isRejected())
                    <a href="{{ route('notes.edit', $note) }}" class="px-4 py-2 rounded-lg bg-ink-800 text-white text-sm font-bold hover:bg-ink-900 transition">تصحيح وإعادة إرسال</a>
                    <form method="POST" action="{{ route('notes.resend', $note) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition">إعادة الإرسال الآن</button>
                    </form>
                @elseif($isWriter && $note->isPending())
                    <form method="POST" action="{{ route('notes.accept', $note) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">قبول واعتماد</button>
                    </form>
                    <button type="button" onclick="closeModal('detail-{{ $note->id }}');setTimeout(()=>openModal('reject-{{ $note->id }}'),200)" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition">رفض مع سبب</button>
                @elseif($note->isAccepted())
                    <span class="inline-flex items-center gap-1.5 text-sm font-bold text-[#0e6a38]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        مُعتمدة نهائياً
                    </span>
                @endif
                <button type="button" onclick="closeModal('detail-{{ $note->id }}')" class="mr-auto px-4 py-2 rounded-lg border border-[#e6e9e1] text-[#737373] text-sm font-medium hover:bg-white transition">إغلاق</button>
            </div>
        </div>
    </div>
@endforeach


@foreach($notes as $note)
    @if($isWriter && $note->isPending())
        <div id="reject-{{ $note->id }}" data-modal class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" onclick="closeModal('reject-{{ $note->id }}')"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e6e9e1]">
                    <h3 class="text-base font-extrabold text-ink-800">رفض الملاحظة #{{ str_pad($note->id, 4, '0', STR_PAD_LEFT) }}</h3>
                    <p class="text-xs text-[#737373] mt-1">اكتب سبباً واضحاً لكي يتمكن المراقب من التصحيح.</p>
                </div>
                <form method="POST" action="{{ route('notes.reject', $note) }}" class="p-6 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-bold text-ink-700 mb-2">سبب الرفض <span class="text-red-500">*</span></label>
                        <textarea name="rejection_reason" rows="4" required maxlength="2000" class="w-full rounded-xl border border-[#e6e9e1] bg-white py-3 px-4 text-sm leading-6 text-ink-800 placeholder:text-ink-300 focus:border-red-400 focus:ring-2 focus:ring-red-400/10 outline-none transition resize-none" placeholder="مثال: الصورة غير واضحة — يرجى إعادة التصوير مع توضيح رقم الكاميرا..."></textarea>
                        <div class="mt-1 text-xs text-ink-300">الحد الأقصى 2000 حرف</div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('reject-{{ $note->id }}')" class="px-4 py-2.5 rounded-xl border border-[#e6e9e1] text-[#525252] font-bold text-sm hover:bg-[#f5f7f5] transition">إلغاء</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white font-bold text-sm transition">تأكيد الرفض</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach


<div id="attachment-view-modal" data-modal class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-ink-900/70 backdrop-blur-sm" onclick="closeModal('attachment-view-modal')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-surface-300 flex items-center justify-between gap-3 shrink-0">
            <h3 id="attachment-view-title" class="text-sm font-bold text-ink-800 truncate"></h3>
            <button type="button" onclick="closeModal('attachment-view-modal')" class="w-8 h-8 rounded-lg hover:bg-surface-100 flex items-center justify-center text-ink-400 hover:text-ink-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 min-h-0 bg-ink-900 flex items-center justify-center p-4 overflow-auto">
            <img id="attachment-view-image" class="hidden max-w-full max-h-[70vh] rounded-lg object-contain" oncontextmenu="return false;" draggable="false" alt="معاينة الصورة">
            <video id="attachment-view-video" class="hidden max-w-full max-h-[70vh] rounded-lg" controls controlsList="nodownload" oncontextmenu="return false;" disablePictureInPicture></video>
            <audio id="attachment-view-audio" class="hidden w-full max-w-md" controls controlsList="nodownload" oncontextmenu="return false;"></audio>
            <div id="attachment-view-fallback" class="hidden text-center text-white/70 text-sm">لا يمكن معاينة هذا النوع</div>
        </div>
        <div class="px-4 py-3 border-t border-surface-300 bg-surface-50 flex items-center justify-between gap-3 shrink-0">
            <p class="text-xs text-ink-400">العرض فقط — التنزيل لكاتب التقرير فقط</p>
            <button type="button" onclick="closeModal('attachment-view-modal')" class="px-4 py-2 rounded-lg bg-white border border-surface-300 text-ink-600 text-sm font-bold hover:bg-surface-100 transition">إغلاق</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openAttachmentView(url, mime, name){
    const modal = document.getElementById('attachment-view-modal');
    const img = document.getElementById('attachment-view-image');
    const video = document.getElementById('attachment-view-video');
    const audio = document.getElementById('attachment-view-audio');
    const fallback = document.getElementById('attachment-view-fallback');
    const title = document.getElementById('attachment-view-title');
    if(title) title.textContent = name;
    img.classList.add('hidden'); img.src='';
    video.classList.add('hidden'); video.pause(); video.src=''; video.load();
    audio.classList.add('hidden'); audio.pause(); audio.src=''; audio.load();
    fallback.classList.add('hidden');
    if(mime.startsWith('image/')){
        img.src = url;
        img.classList.remove('hidden');
    } else if(mime.startsWith('video/')){
        video.src = url;
        video.classList.remove('hidden');
        video.load();
    } else if(mime.startsWith('audio/')){
        audio.src = url;
        audio.classList.remove('hidden');
        audio.load();
    } else {
        fallback.classList.remove('hidden');
        fallback.textContent = 'لا يمكن معاينة هذا النوع — ' + mime;
    }
    modal.classList.remove('hidden');
    document.body.style.overflow='hidden';
    img.oncontextmenu = () => false;
    video.oncontextmenu = () => false;
}

document.addEventListener('contextmenu', e=>{
    const modal = document.getElementById('attachment-view-modal');
    if(modal && !modal.classList.contains('hidden') && (e.target.tagName==='IMG' || e.target.tagName==='VIDEO')){
        e.preventDefault();
    }
});


function openShareModal(text, attachments, phone) {
    const modal = document.getElementById('share-modal');
    const textEl = document.getElementById('share-text');
    const listEl = document.getElementById('share-attachments');
    const sectionEl = document.getElementById('share-attachments-section');
    textEl.value = text;
    if (!attachments || attachments.length === 0) {
        sectionEl.style.display = 'none';
        listEl.innerHTML = '';
    } else {
        sectionEl.style.display = '';
        let html = '';
        attachments.forEach(a => {
            const isImage = a.mime.includes('image');
            const isVideo = a.mime.includes('video');
            const icon = isVideo
                ? '<svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>'
                : '<svg class="w-4 h-4 text-sage-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
            html += '<label class="flex items-center gap-3 p-2.5 rounded-xl border border-[#e6e9e1] hover:bg-[#f5f7f5] cursor-pointer transition">' +
                '<input type="checkbox" class="share-file-cb rounded border-[#c2cbc1] text-[#0e6a38] focus:ring-[#0e6a38]/20 w-4 h-4" data-url="' + a.url + '" data-name="' + a.name + '" data-mime="' + a.mime + '" checked onchange="updateShareSelectedCount()">' +
                '<span class="shrink-0">' + icon + '</span>' +
                '<span class="text-sm text-ink-700 truncate">' + a.name + '</span>' +
                '</label>';
        });
        listEl.innerHTML = html;
    }
    modal.dataset.phone = phone;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeShareModal() {
    document.getElementById('share-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
function updateShareSelectedCount() {
    const cbs = document.querySelectorAll('.share-file-cb:checked');
    const allCbs = document.querySelectorAll('.share-file-cb');
    const countEl = document.getElementById('share-selected-count');
    const selectAllEl = document.getElementById('share-select-all');
    if (countEl) countEl.textContent = cbs.length + ' من ' + allCbs.length;
    if (selectAllEl) selectAllEl.checked = allCbs.length > 0 && cbs.length === allCbs.length;
}
function toggleShareSelectAll(cb) {
    document.querySelectorAll('.share-file-cb').forEach(c => c.checked = cb.checked);
    updateShareSelectedCount();
}
async function doShare() {
    const btn = document.getElementById('share-send-btn');
    const btnText = document.getElementById('share-send-text');
    const btnLoader = document.getElementById('share-send-loader');
    const textEl = document.getElementById('share-text');
    const text = textEl ? textEl.value.trim() : '';
    const hasWebShare = !!navigator.share;
    btn.disabled = true;
    btnText.textContent = hasWebShare ? 'جاري التجهيز...' : 'جاري النسخ...';
    btnLoader.classList.remove('hidden');
    const finish = () => { btn.disabled = false; btnText.textContent = 'مشاركة'; btnLoader.classList.add('hidden'); };
    const cbs = document.querySelectorAll('.share-file-cb:checked');
    const items = [];
    for (const cb of cbs) {
        try {
            const res = await fetch(cb.dataset.url);
            if (!res.ok) continue;
            const blob = await res.blob();
            items.push({file: new File([blob], cb.dataset.name, {type: cb.dataset.mime}), url: cb.dataset.url, name: cb.dataset.name});
        } catch(e) {}
    }
    if (!text && items.length === 0) {
        finish();
        alert('اختر رسالة أو وسائط للمشاركة');
        return;
    }
    if (hasWebShare) {
        const shareData = {};
        if (text) shareData.text = text;
        const webFiles = items.map(i => i.file);
        const canShareFiles = webFiles.length > 0 && !!navigator.canShare && navigator.canShare({files: webFiles});
        if (canShareFiles) shareData.files = webFiles;
        if (webFiles.length > 0 && !canShareFiles) {
            try { if (text) await navigator.share({text}); } catch(e) {}
            finish();
            showShareFallback(items, text);
            return;
        }
        try {
            await navigator.share(shareData);
            closeShareModal();
        } catch(e) {
            if (e.name !== 'AbortError') alert('تعذرت المشاركة');
        } finally {
            finish();
        }
    } else {
        const phone = document.getElementById('share-modal').dataset.phone || '';
        const cleanPhone = phone.replace(/^\+/,'');
        var counters={img:0,vid:0,aud:0,other:0};
        var shortName=function(it){
            var m=it.mime||'';
            if(m.indexOf('audio/')===0) return 'مقطع صوتي '+ (++counters.aud);
            if(m.indexOf('video/')===0) return 'فيديو '+ (++counters.vid);
            if(m.indexOf('image/')===0) return 'صورة '+ (++counters.img);
            return 'مرفق '+ (++counters.other);
        };
        const filesText = items.length ? '\n\n*الملفات المرفقة ('+items.length+')*\n' + items.map(function(it){ return shortName(it)+':\n\u200E'+shareBase()+it.url; }).join('\n\n') : '';
        const fullText = text + filesText;
        const waUrl = 'https://wa.me/?text=' + encodeURIComponent(fullText);
        if (waUrl) {
            window.open(waUrl, '_blank');
            finish();
            if (items.length) setTimeout(function(){ showShareFallback(items, text); }, 800);
            else closeShareModal();
        } else {
            try { if (fullText) await navigator.clipboard.writeText(fullText); } catch(e){}
            finish();
            showShareFallback(items, text);
        }
    }
}
function escShareHtml(s) {
    return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showShareFallback(items, text) {
    items = items || [];
    text = text || '';
    const modal = document.getElementById('share-modal');
    const body = modal.querySelector('.flex-1');
    if (!body) return;
    const phone = modal.dataset.phone || '';
    const cleanPhone = phone.replace(/^\+/,'');
    const waUrl = text ? 'https://wa.me/?text=' + encodeURIComponent(text) : null;
    let html = '<div class="p-4 bg-[#f5f7f5] rounded-xl border border-[#e6e9e1] space-y-3">';
    if (waUrl) {
        html += '<a href="' + waUrl + '" target="_blank" rel="noopener" class="block w-full text-center px-4 py-3 rounded-xl bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] transition">فتح واتساب بالنص</a>';
    }
    if (text) {
        html += '<div class="space-y-2"><label class="block text-xs font-bold text-ink-500">النص</label>';
        html += '<textarea id="share-fallback-text" readonly class="w-full h-24 p-3 rounded-lg border border-[#e6e9e1] text-sm text-ink-700 bg-white" onclick="this.select()">' + escShareHtml(text) + '</textarea>';
        html += '<button onclick="copyShareFallbackText()" id="share-fallback-copy" class="px-4 py-2 rounded-lg bg-[#0e6a38] text-white text-sm font-bold hover:bg-[#0a4d28] transition">نسخ النص</button>';
        html += '</div>';
    }
    if (items.length > 0) {
        html += '<div class="space-y-2 mt-3"><label class="block text-xs font-bold text-ink-500">روابط المشاهدة — دائمة (' + items.length + ')</label>';
        items.forEach(it => {
            var absUrl = shareBase() + it.url;
            html += '<div class="p-2.5 rounded-xl border border-[#e6e9e1] bg-white space-y-2">';
            html += '<div class="text-sm text-ink-700 font-bold truncate">📎 ' + escShareHtml(it.name) + '</div>';
            html += '<div class="flex items-center gap-2"><input readonly onclick="this.select()" value="' + escShareHtml(absUrl) + '" dir="ltr" class="flex-1 min-w-0 text-xs font-mono text-ink-500 bg-[#f5f7f5] border border-[#e6e9e1] rounded-lg px-2 py-1.5">';
            html += '<button data-url="' + escShareHtml(absUrl) + '" onclick="copyShareLink(this)" class="shrink-0 px-3 py-1.5 rounded-lg bg-[#0e6a38] text-white text-xs font-bold hover:bg-[#0a4d28] transition">نسخ الرابط</button></div></div>';
        });
        html += '</div>';
    }
    html += '</div><div class="mt-4 text-center"><button onclick="closeShareModal()" class="px-6 py-2.5 rounded-lg bg-white border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-[#f5f7f5] transition">إغلاق</button></div>';
    body.innerHTML = html;
}
function copyShareFallbackText() {
    const ta = document.getElementById('share-fallback-text');
    if (!ta) return;
    copyShareValue(ta.value, 'share-fallback-copy', 'نسخ النص');
}
function copyShareLink(btn) {
    if (!btn || !btn.dataset.url) return;
    copyShareValue(btn.dataset.url, null, null, btn);
}
function copyShareValue(value, btnId, btnIdleText, btnEl) {
    const done = (ok) => {
        const b = btnEl || (btnId ? document.getElementById(btnId) : null);
        if (!b) return;
        const orig = btnIdleText || b.textContent;
        b.textContent = ok ? 'تم النسخ' : 'تعذر النسخ';
        setTimeout(() => { b.textContent = orig; }, 1500);
    };
    window.copyTextToClipboard(value).then(done);
}
function copyShareText() {
    const text = document.getElementById('share-text');
    window.copyTextToClipboard(text.value).then((ok) => {
        const btn = document.getElementById('share-copy-btn');
        if (btn) btn.textContent = ok ? 'تم النسخ' : 'تعذر النسخ';
        setTimeout(() => { if (btn) btn.textContent = 'نسخ'; }, 1500);
    });
}

</script>


<div id="share-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" onclick="closeShareModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[85vh] flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-[#e6e9e1] flex items-center justify-between shrink-0">
            <h3 class="text-base font-extrabold text-ink-800">مشاركة</h3>
            <button onclick="closeShareModal()" class="w-8 h-8 rounded-lg hover:bg-[#f5f7f5] flex items-center justify-center text-ink-300 hover:text-ink-700 transition" aria-label="إغلاق">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 overflow-y-auto flex-1 space-y-4">
            <div>
                <label for="share-text" class="block text-xs font-bold text-ink-500 mb-1.5">الرسالة</label>
                <textarea id="share-text" rows="4" class="w-full rounded-xl border border-[#e6e9e1] bg-[#f5f7f5] py-3 px-4 text-sm leading-7 text-ink-800 resize-none focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none transition" placeholder="اكتب رسالة..."></textarea>
            </div>
            <div id="share-attachments-section">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-ink-500">الوسائط</label>
                    <div class="flex items-center gap-2">
                        <span id="share-selected-count" class="text-xs text-ink-400"></span>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" id="share-select-all" onchange="toggleShareSelectAll(this)" class="rounded border-[#c2cbc1] text-[#0e6a38] focus:ring-[#0e6a38]/20 w-3.5 h-3.5">
                            <span class="text-xs text-ink-500">الكل</span>
                        </label>
                    </div>
                </div>
                <div id="share-attachments" class="space-y-2"></div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-[#e6e9e1] flex items-center gap-2 shrink-0 bg-[#f5f7f5]">
            <button id="share-copy-btn" onclick="copyShareText()" class="px-4 py-2 rounded-lg border border-[#e6e9e1] text-ink-600 text-sm font-bold hover:bg-white transition">نسخ</button>
            <button id="share-send-btn" onclick="doShare()" class="flex-1 px-4 py-2.5 rounded-xl bg-[#25D366] text-white text-sm font-bold hover:bg-[#1da851] active:scale-[0.98] transition flex items-center justify-center gap-2">
                <svg id="share-send-loader" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span id="share-send-text">مشاركة</span>
            </button>
        </div>
    </div>
</div>

<script>

document.querySelectorAll('form[data-ajax]').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>'; }
        try {
            const fd = new FormData(this);
            const res = await fetch(this.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: fd
            });
            const data = await res.json();
            if (res.ok && data.success) {

                if(window.fetchNotifications) window.fetchNotifications(false);

                const newStatus = data.status || (data.data && data.data.status);
                const noteId = this.dataset.noteId;

                let card = this.closest('[data-note-card]');
                if (!card && noteId) {
                    const candidates = Array.from(document.querySelectorAll('[data-note-id="'+noteId+'"]'));
                    card = candidates.find(el => el !== this && (el.querySelector('.note-badge') || el.querySelector('.note-actions'))) || null;
                }
                let updated = false;
                if (card && newStatus) {
                    const statusMap = { draft:'مسودة', pending:'قيد المراجعة', accepted:'مقبولة', rejected:'مرفوضة' };
                    const clsMap = { draft:'bg-ink-100 text-ink-500 border-[#e6e9e1]', pending:'bg-amber-50 text-amber-700 border-amber-200', accepted:'bg-[#eef4f0] text-[#0e6a38] border-[#cde7d6]', rejected:'bg-red-50 text-red-700 border-red-200' };
                    const dotMap = { draft:'bg-ink-300', pending:'bg-amber-400', accepted:'bg-[#0e6a38]', rejected:'bg-red-400' };
                    const badge = card.querySelector('.note-badge');
                    if (badge && clsMap[newStatus]) {
                        badge.className = 'note-badge inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border ' + clsMap[newStatus];
                        badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full ' + dotMap[newStatus] + (newStatus==='pending'?' animate-pulse':'') + '"></span>' + statusMap[newStatus];
                        updated = true;
                    }

                    const actionsEl = card.querySelector('.note-actions');
                    if (actionsEl) {
                        let newActions = '';
                        if (newStatus === 'pending') {
                            newActions = '<a href="/notes/'+noteId+'/edit" class="px-2.5 py-1 rounded-lg bg-[#fdfcfa] border border-[#e6e9e1] text-[#525252] text-xs font-bold">تعديل</a>';
                        } else if (newStatus === 'accepted') {
                            newActions = '<span class="text-[11px] text-[#0e6a38] font-bold">مقبولة</span>';
                        } else if (newStatus === 'rejected') {
                            newActions = '<a href="/notes/'+noteId+'/edit" class="px-2.5 py-1 rounded-lg bg-ink-800 text-white text-xs font-bold">تصحيح</a>';
                        }
                        actionsEl.innerHTML = newActions;
                        updated = true;
                    }
                }
                if (data.deleted && card) {
                    card.style.transition = 'opacity .2s, transform .2s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    setTimeout(() => card.remove(), 250);
                    updated = true;
                }

                if (!updated) { location.reload(); return; }
            } else {
                alert(data.message || 'حدث خطأ');
                if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
            }
        } catch(err) {
            this.submit();
        }
    });
});


document.querySelectorAll('[data-ajax-tab]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.href;
        const listEl = document.getElementById('notes-list');
        const tabsNav = this.closest('nav');
        if (!listEl) { window.location.href = url; return; }
        listEl.style.opacity = '0.4';
        listEl.style.transition = 'opacity .15s';
        tabsNav.querySelectorAll('a').forEach(a => { a.classList.remove('border-[#0e6a38]','text-ink-800','font-bold'); a.classList.add('border-transparent','text-[#737373]'); });
        this.classList.add('border-[#0e6a38]','text-ink-800','font-bold');
        this.classList.remove('border-transparent','text-[#737373]');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newList = doc.getElementById('notes-list');
                if (newList) listEl.innerHTML = newList.innerHTML;
                listEl.style.opacity = '1';
                history.pushState(null, '', url);
            })
            .catch(() => { window.location.href = url; });
    });
});
</script>
@endpush
@push('scripts')
<script>
/* Silent live sync — tiny 1s signature check, list re-renders only on change. */
(function(){
    if (!document.getElementById('notes-list') || typeof window.ajaxFilter !== 'function') return;
    var lastSig = null, busy = false;
    function buildCheckUrl(){
        var q = new URLSearchParams(window.location.search);
        q.set('live', '1');
        return window.location.pathname + '?' + q.toString();
    }
    function userBusy(){
        var a = document.activeElement;
        if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT' || a.isContentEditable)) return true;
        if (document.querySelector('[data-modal]:not(.hidden),#share-modal:not(.hidden),#camera-modal:not(.hidden),#camera-modal-edit:not(.hidden),#print-selection-modal:not(.hidden),#print-preview-modal:not(.hidden)')) return true;
        return false;
    }
    function tick(){
        if (document.hidden || busy || userBusy()) return;
        busy = true;
        fetch(buildCheckUrl(), {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}})
            .then(function(r){ if (!r.ok) throw 0; return r.json(); })
            .then(function(d){
                if (lastSig === null) { lastSig = d.sig; return; }
                if (d.sig !== lastSig) {
                    lastSig = d.sig;
                    window.ajaxFilter(window.location.pathname + window.location.search);
                }
            })
            .catch(function(){ /* silent — retry next second */ })
            .finally(function(){ busy = false; });
    }
    document.addEventListener('visibilitychange', function(){ if (!document.hidden) tick(); });
    setInterval(tick, 1000);
})();
</script>
@endpush
@include('notes.partials.print_modal')
@endsection
