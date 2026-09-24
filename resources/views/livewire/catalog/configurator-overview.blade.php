<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-5 xl:col-span-2">
        {{ $this->form }}
        <x-filament::button wire:click="saveOverview" wire:loading.attr="disabled" wire:target="saveOverview">Save overview</x-filament::button>
    </div>
    <aside class="space-y-4" aria-label="Configurator summary">
        <x-filament::section heading="Definition summary">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500">Internal ID</dt><dd>{{ $configurator->id }}</dd>
                <dt class="text-gray-500">Attributes</dt><dd>{{ $configurator->attributes_count }}</dd>
                <dt class="text-gray-500">Rules</dt><dd>{{ $configurator->rules_count }}</dd>
                <dt class="text-gray-500">Created</dt><dd>{{ $configurator->created_at?->format('d M Y') ?? '—' }}</dd>
                <dt class="text-gray-500">Details updated</dt><dd>{{ $configurator->updated_at?->format('d M Y H:i') ?? '—' }}</dd>
            </dl>
        </x-filament::section>
    </aside>
</div>
