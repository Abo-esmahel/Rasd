@auth
@php
    $u = auth()->user();
    $isGM = $u->isGeneralManager();
    $isWriter = $u->isReportWriter();
    // الرئيسية حسب الدور
    $homeRoute = $isGM ? route('reports.index') : route('notes.index');
    $canCreate = !$isGM;
    $createRoute = request()->routeIs('reports.*') && $isWriter ? route('reports.create') : route('notes.create');
@endphp
<nav id="m-bottom-nav" class="md:hidden" aria-label="{{ __('ui.main_nav') }}">
    @if(!$isGM)
    <a href="{{ route('notes.index') }}"
       class="m-nav-item {{ request()->routeIs('notes.index','notes.show','notes.my') && !request()->routeIs('notes.create') ? 'is-active' : '' }}"
       aria-current="{{ request()->routeIs('notes.index','notes.my') ? 'page' : 'false' }}">
        <span class="m-nav-ic"><x-phosphor-binoculars-duotone class="w-6 h-6" /></span>
        <span class="m-nav-lb">{{ __('ui.nav_notes') }}</span>
    </a>
    @else
    <a href="{{ route('gallery.index') }}"
       class="m-nav-item {{ request()->routeIs('gallery.*') ? 'is-active' : '' }}">
        <span class="m-nav-ic"><x-phosphor-images-duotone class="w-6 h-6" /></span>
        <span class="m-nav-lb">{{ __('ui.nav_gallery') }}</span>
    </a>
    @endif

    <a href="{{ route('reports.index') }}"
       class="m-nav-item {{ request()->routeIs('reports.*') ? 'is-active' : '' }}">
        <span class="m-nav-ic"><x-phosphor-files-duotone class="w-6 h-6" /></span>
        <span class="m-nav-lb">{{ __('ui.nav_reports') }}</span>
    </a>

    @if($canCreate)
    <a href="{{ $createRoute }}" class="m-nav-fab" aria-label="{{ __('ui.new_note') }}">
        <span class="m-fab-btn"><x-phosphor-plus-duotone class="w-7 h-7" /></span>
    </a>
    @else
    <a href="{{ $homeRoute }}" class="m-nav-item {{ request()->routeIs('ranking') ? '' : '' }}">
        <span class="m-nav-ic"><x-phosphor-house-duotone class="w-6 h-6" /></span>
        <span class="m-nav-lb">{{ __('ui.redirect_home') }}</span>
    </a>
    @endif

    @if(!$isGM)
    <a href="{{ route('gallery.index') }}"
       class="m-nav-item {{ request()->routeIs('gallery.*') ? 'is-active' : '' }}">
        <span class="m-nav-ic"><x-phosphor-images-duotone class="w-6 h-6" /></span>
        <span class="m-nav-lb">{{ __('ui.nav_gallery') }}</span>
    </a>
    @else
    <a href="{{ route('ranking') }}"
       class="m-nav-item {{ request()->routeIs('ranking') ? 'is-active' : '' }}">
        <span class="m-nav-ic"><x-phosphor-trophy-duotone class="w-6 h-6" /></span>
        <span class="m-nav-lb">{{ __('ui.nav_ranking') }}</span>
    </a>
    @endif

    <a href="{{ route('profile.show') }}"
       class="m-nav-item {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
        <span class="m-nav-ic relative">
            @if($u->avatar_url)
                <img src="{{ $u->avatar_url }}" alt="" class="w-6 h-6 rounded-full object-cover ring-2 ring-transparent">
            @else
                <x-phosphor-user-circle-duotone class="w-6 h-6" />
            @endif
            @php $unread = $unreadCount ?? 0; @endphp
        </span>
        <span class="m-nav-lb">{{ __('ui.nav_account') }}</span>
    </a>
</nav>
@endauth
