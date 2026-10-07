<div class="space-y-2">
    {{ $this->membershipAction }}
    <x-filament-actions::modals />
    <div class="flex items-center gap-2">
        <h2 class="text-base font-semibold">Group assignments</h2>
        {{ \App\Filament\Resources\FormHints::make('Each leaf Group can use one Configurator. Its definition applies to every assigned Group. Groups assigned elsewhere are excluded; change those assignments from their Group.') }}
    </div>
    @if ($errors->any())
        <div role="alert" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())" class="rounded-lg border border-danger-300 p-4 text-sm">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif
    <div class="grid gap-2 md:grid-cols-2">
        <x-filament::section :heading="'Assigned to '.$configurator->name" :description="$assigned->count().' Groups · '.$assigned->sum('products_count').' Products'">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($assigned as $group)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0" wire:key="assigned-group-{{ $group->id }}">
                        <div>
                            <a class="font-medium underline-offset-4 hover:underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a>
                            <div class="mt-1 text-sm">{{ ($this->productsAction)(['group' => $group->id])->label($group->products_count.' Products')->link() }}</div>
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
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0" wire:key="available-group-{{ $group->id }}">
                        <div>
                            <a class="font-medium underline-offset-4 hover:underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a>
                            <div class="mt-1 text-sm">{{ ($this->productsAction)(['group' => $group->id])->label($group->products_count.' Products')->link() }}</div>
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
