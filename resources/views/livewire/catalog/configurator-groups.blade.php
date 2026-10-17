<div class="space-y-2">
    {{ $this->membershipAction }}
    <x-filament-actions::modals />
    <div class="flex items-center gap-2">
        <h2 class="text-base font-semibold">{{ __('configurator.copy.group_assignments') }}</h2>
        {{ \App\Filament\Resources\FormHints::make(__('configurator.groups.assignment_help')) }}
    </div>
    @if ($errors->any())
        <div role="alert" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())" class="rounded-lg border border-danger-300 p-4 text-sm">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif
    <div class="grid gap-2 md:grid-cols-2">
        <x-filament::section :heading="__('configurator.groups.assigned_to', ['name' => $configurator->name])" :description="__('configurator.groups.assignment_counts', ['groups' => $assigned->count(), 'products' => $assigned->sum('products_count')])">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($assigned as $group)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0" wire:key="assigned-group-{{ $group->id }}">
                        <div>
                            <a class="font-medium underline-offset-4 hover:underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a>
                            <div class="mt-1 text-sm">{{ ($this->productsAction)(['group' => $group->id])->label(trans_choice('configurator.groups.product_count', $group->products_count))->link() }}</div>
                        </div>
                        <x-filament::button color="gray" size="sm" wire:click="unassignGroup({{ $group->id }})" wire:loading.attr="disabled" aria-label="{{ __('configurator.groups.unassign_named', ['name' => $group->name]) }}">{{ __('configurator.copy.unassign') }}</x-filament::button>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">{{ __('configurator.copy.no_groups_assigned') }}</li>
                @endforelse
            </ul>
        </x-filament::section>
        <x-filament::section heading="{{ __('configurator.copy.available_groups') }}" :description="trans_choice('configurator.groups.available_count', $available->count())">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($available as $group)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0" wire:key="available-group-{{ $group->id }}">
                        <div>
                            <a class="font-medium underline-offset-4 hover:underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a>
                            <div class="mt-1 text-sm">{{ ($this->productsAction)(['group' => $group->id])->label(trans_choice('configurator.groups.product_count', $group->products_count))->link() }}</div>
                        </div>
                        <x-filament::button size="sm" wire:click="assignGroup({{ $group->id }})" wire:loading.attr="disabled" aria-label="{{ __('configurator.groups.assign_named', ['name' => $group->name]) }}">{{ __('configurator.copy.assign') }}</x-filament::button>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">{{ __('configurator.copy.no_unassigned_leaf_groups') }}</li>
                @endforelse
            </ul>
        </x-filament::section>
    </div>
</div>
