<div class="space-y-6">
    <div class="space-y-2">
        <h2 class="text-lg font-semibold">Group assignments</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">Each leaf Group can use one Configurator. This Configurator can serve several Groups; changes to its definition apply to every assigned Group.</p>
        <p class="text-sm text-gray-600 dark:text-gray-300">Groups assigned elsewhere are excluded from the available list. Change those assignments explicitly from their Group.</p>
    </div>
    @if ($errors->any())
        <div role="alert" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())" class="rounded-lg border border-danger-300 p-4 text-sm">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif
    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section :heading="'Assigned to '.$configurator->name" :description="$assigned->count().' Groups · '.$assigned->sum('products_count').' Products'">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($assigned as $group)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0" wire:key="assigned-group-{{ $group->id }}">
                        <div>
                            <a class="font-medium underline-offset-4 hover:underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a>
                            <p class="mt-1 text-sm text-gray-500">{{ $group->products_count }} Products</p>
                        </div>
                        <x-filament::button color="gray" size="sm" wire:click="unassignGroup({{ $group->id }})" wire:loading.attr="disabled" aria-label="Unassign {{ $group->name }}">Unassign</x-filament::button>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No Groups assigned.</li>
                @endforelse
            </ul>
        </x-filament::section>
        <x-filament::section heading="Available Groups" :description="$available->count().' unassigned leaf Groups'">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($available as $group)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0" wire:key="available-group-{{ $group->id }}">
                        <div>
                            <a class="font-medium underline-offset-4 hover:underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a>
                            <p class="mt-1 text-sm text-gray-500">{{ $group->products_count }} Products</p>
                        </div>
                        <x-filament::button size="sm" wire:click="assignGroup({{ $group->id }})" wire:loading.attr="disabled" aria-label="Assign {{ $group->name }}">Assign</x-filament::button>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No unassigned leaf Groups.</li>
                @endforelse
            </ul>
        </x-filament::section>
    </div>
</div>
