@props([
    'user' => null,
    'size' => 'xl',
    'shape' => 'rounded-xl',
    'skin' => 'note',
    'imgExtraClass' => 'border border-[#eceee9] dark:border-[#2e352e] shadow-sm',
    'weight' => 'font-semibold',
    'gap' => 'gap-2.5',
    'wrapperClass' => '',
    'nameClass' => '',
    'showName' => false,
    'stop' => false,
    'placeholder' => '—',
])

@php
    $sizes = [
        'xs'  => 'w-4 h-4 text-[9px]',
        'sm'  => 'w-6 h-6 text-[10px]',
        'md'  => 'w-8 h-8 text-xs',
        'lg'  => 'w-9 h-9 text-sm',
        'xl'  => 'w-10 h-10 text-[13px]',
        '2xl' => 'w-12 h-12 text-lg',
    ];
    $skins = [
        'note'  => 'bg-[#f5f7f5] dark:bg-[#1e2320] border border-[#eceee9] dark:border-[#2e352e] text-[#0e6a38] dark:text-[#5cb87a]',
        'soft'  => 'bg-[#eef4f0] dark:bg-[#1e3328] border border-[#e6e9e1] dark:border-[#2e352e] text-[#0e6a38] dark:text-[#4ade80]',
        'sage'  => 'bg-sage-50 text-sage-700',
        'solid' => 'bg-sage-600 text-white',
        'gray'  => 'bg-[#eceee9] text-[#525252]',
    ];

    $target = $user instanceof \App\Models\User ? $user : null;
    $dimension = ($sizes[$size] ?? $sizes['xl']).' '.$shape;
    $palette = $skins[$skin] ?? $skins['note'];
    $href = $target
        ? ($target->id === auth()->id() ? route('profile.show') : route('profile.showUser', $target->id))
        : null;
    $label = $target?->localized_name ?? $placeholder;
    $wrapper = trim(($showName ? 'inline-flex items-center '.$gap.' min-w-0 ' : 'inline-flex shrink-0 ').$wrapperClass);
    $tag = $href ? 'a' : 'span';
    $linkAttributes = $href
        ? ' href="'.e($href).'" title="'.e($label).'" aria-label="'.e(__('ui.profile_aria')).' ('.e($label).')"'
            .($stop ? ' onclick="event.stopPropagation()"' : '')
        : '';
@endphp

<{{ $tag }} {!! $linkAttributes !!} class="{{ $wrapper }} hover:opacity-80 transition">
    @if($target)
        @if($target->avatar_url)
            <img src="{{ $target->avatar_url }}" alt="{{ $label }}" loading="lazy" class="{{ $dimension }} object-cover {{ $imgExtraClass }}">
        @else
            <span class="{{ $dimension }} {{ $palette }} flex items-center justify-center {{ $weight }}" aria-hidden="true">{{ $target->initial }}</span>
        @endif
    @endif
    @if($showName)
        <span class="truncate {{ $nameClass }}">{{ $label }}</span>
    @endif
</{{ $tag }}>