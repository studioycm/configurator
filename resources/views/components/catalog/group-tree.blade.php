@props(['groups'])
<ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($groups as $child)
        <li wire:key="group-{{ $child->id }}">
            <a class="block h-full rounded-xl border border-zinc-300 p-6 transition hover:border-teal-600 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:border-zinc-700" href="{{ route('catalog.groups.show', $child) }}">
                <h2 class="text-xl font-semibold">{{ $child->name }} <span aria-hidden="true">→</span></h2>
                @if ($child->description)<p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $child->description }}</p>@endif
                <span class="mt-5 inline-block text-sm text-teal-700 dark:text-teal-300">{{ __('Browse group') }}</span>
            </a>
        </li>
    @endforeach
</ul>
