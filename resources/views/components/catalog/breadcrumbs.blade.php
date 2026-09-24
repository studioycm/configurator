@props(['group', 'includeCurrent' => false, 'ancestors' => null])
<nav aria-label="Breadcrumb" class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">
    <ol class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <li><a class="underline underline-offset-4" href="{{ route('catalog.index') }}">{{ __('Catalog') }}</a></li>
        @foreach ($ancestors ?? $group->ancestorTrail() as $ancestor)
            <li aria-hidden="true">/</li>
            <li><a class="underline underline-offset-4" href="{{ route('catalog.groups.show', $ancestor) }}">{{ $ancestor->name }}</a></li>
        @endforeach
        <li aria-hidden="true">/</li>
        <li>@if ($includeCurrent)<a class="underline underline-offset-4" href="{{ route('catalog.groups.show', $group) }}">{{ $group->name }}</a>@else<span aria-current="page">{{ $group->name }}</span>@endif</li>
    </ol>
</nav>
