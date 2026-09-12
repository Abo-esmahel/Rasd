@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-extrabold text-ink-800">{{ __('ui.subs_title') }}</h1>
        </div>
        @can('create', App\Models\GeneralSubmission::class)
        <a href="{{ route('general-submissions.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm transition">
            {{ __('ui.sub_new_plus') }}
        </a>
        @endcan
    </div>

    @php
        $periodTabs = [
            ['key' => null, 'label' => __('ui.all')],
            ['key' => 'today', 'label' => __('ui.today')],
            ['key' => 'yesterday', 'label' => __('ui.yesterday')],
            ['key' => 'week', 'label' => __('ui.last7')],
            ['key' => 'month', 'label' => __('ui.last30')],
        ];
    @endphp
    <div class="flex gap-2 mb-4 overflow-x-auto pb-1" role="group" aria-label="{{ __('ui.filter_time') }}">
        @foreach($periodTabs as $tab)
            @php
                $isActive = ($period ?? null) === $tab['key'];
                $url = $tab['key'] === null
                    ? route('general-submissions.index', request()->except(['period', 'page']))
                    : route('general-submissions.index', array_merge(request()->except(['period', 'page']), ['period' => $tab['key']]));
            @endphp
            <a href="{{ $url }}" class="shrink-0 px-4 py-1.5 rounded-full text-[13px] border transition {{ $isActive ? 'bg-[#0e6a38] border-[#0e6a38] text-white font-bold shadow-sm' : 'bg-white border-[#e6e9e1] text-ink-600 font-medium hover:border-[#0e6a38] hover:text-[#0e6a38]' }}">{{ $tab['label'] }}</a>
        @endforeach
    </div>

    @if($submissions->isEmpty())
        <div class="bg-white rounded-2xl border border-[#e6e9e1] p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-[#f5f7f5] flex items-center justify-center mx-auto mb-3">📋</div>
            <h3 class="font-bold text-ink-700">{{ __('ui.no_submissions') }}</h3>
        </div>
    @else
        <div class="space-y-3">
            @foreach($submissions as $submission)
                <a href="{{ route('general-submissions.show', $submission) }}" class="block bg-white rounded-xl border border-[#e6e9e1] p-5 hover:border-[#cde7d6] hover:shadow-sm transition" data-i18n-entity="submission" data-i18n-id="{{ $submission->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
@if($submission->status === 'draft')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-ink-100 text-ink-600"><span data-status="draft">{{ __('ui.draft') }}</span></span>
                @elseif($submission->status === 'pending')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><span data-status="pending">{{ __('ui.pending') }}</span></span>
                @elseif($submission->status === 'accepted')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#eef4f0] text-[#0e6a38]"><span data-status="accepted">{{ __('ui.accepted') }}</span></span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700"><span data-status="rejected">{{ __('ui.rejected') }}</span></span>
                @endif
                                <span class="text-xs text-ink-400">{{ $submission->created_at->diffForHumans() }}</span>
                                <span class="hidden md:inline text-xs text-ink-200">·</span>
                                <span class="hidden md:inline text-[11px] text-ink-400/80 font-mono" dir="ltr" title="{{ __('ui.created_at_label') }}">{{ $submission->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                            <p class="text-sm text-ink-700 mt-2 line-clamp-2" data-i18n-field="description">{{ Str::limit(l10n_text('submission', $submission->id, 'description', $submission->description), 140) }}</p>
                            <div class="flex items-center gap-3 mt-2 text-xs text-ink-400">
                                @if($submission->floor_number > 0)<span>{{ __('ui.floor') }} {{ $submission->floor_number }}</span><span>•</span>@endif
                                <span>{{ __('ui.camera') }} {{ $submission->camera_number }}</span>
                                <span>•</span>
                                <span>{{ $submission->observed_at->format('Y-m-d H:i') }}</span>
                                @if($submission->attachments->count() > 0)
                                    <span>•</span>
                                    <span class="font-bold">📎 {{ $submission->attachments->count() }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 mt-3">
                                <span class="text-xs text-ink-400">{{ __('ui.sender') }}</span>
                                <span class="text-xs font-bold text-ink-700">{{ $submission->owner->name }}</span>
                                <span class="text-xs text-ink-300">→</span>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($submission->reportWriters as $w)
                                        <span class="px-2 py-0.5 rounded-full bg-[#f5f7f5] border border-[#e6e9e1] text-[11px] font-bold text-ink-600">{{ $w->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
