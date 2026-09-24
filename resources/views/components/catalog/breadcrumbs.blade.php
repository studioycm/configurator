@props(['group', 'includeCurrent' => false, 'ancestors' => null, 'compact' => false])
<nav aria-label="Breadcrumb" {{ $attributes->class(['text-zinc-600 dark:text-zinc-400', 'mb-0.5 text-xs' => $compact, 'mb-3 text-sm' => ! $compact]) }}>
    <ol @class(['flex flex-wrap items-center', 'gap-x-2 gap-y-0.5' => $compact, 'gap-x-3 gap-y-2' => ! $compact])>
        <li><a class="underline underline-offset-4" href="{{ route('catalog.index') }}">{{ __('Catalog') }}</a></li>
        @foreach ($ancestors ?? $group->ancestorTrail() as $ancestor)
            <li aria-hidden="true">/</li>
            <li><a class="underline underline-offset-4" href="{{ route('catalog.groups.show', $ancestor) }}">{{ $ancestor->name }}</a></li>
        @endforeach
        <li aria-hidden="true">/</li>
        <li>@if ($includeCurrent)<a class="underline underline-offset-4" href="{{ route('catalog.groups.show', $group) }}">{{ $group->name }}</a>@else<span aria-current="page">{{ $group->name }}</span>@endif</li>
    </ol>
</nav>
