<div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,3fr)_minmax(15rem,1fr)]" x-data="{ savedDraft: JSON.stringify($wire.data) }" x-bind:data-draft-dirty="JSON.stringify($wire.data) !== savedDraft" x-on:catalog-overview-saved="savedDraft = JSON.stringify($wire.data)">
    <div class="min-w-0 space-y-3">
        {{ $this->form }}
        <x-filament::button wire:click="saveOverview" wire:loading.attr="disabled" wire:target="saveOverview">Save overview</x-filament::button>
    </div>
    <aside class="min-w-0" aria-label="Configurator summary">
        <x-filament::section heading="Definition summary">
            <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                <dt class="text-gray-500">Internal ID</dt><dd>{{ $configurator->id }}</dd>
                <dt class="text-gray-500">Attributes</dt><dd>{{ $configurator->attributes_count }}</dd>
                <dt class="text-gray-500">Rules</dt><dd>{{ $configurator->rules_count }}</dd>
                <dt class="text-gray-500">Created</dt><dd>{{ $configurator->created_at?->format('d M Y') ?? '—' }}</dd>
                <dt class="text-gray-500">Details updated</dt><dd>{{ $configurator->updated_at?->format('d M Y H:i') ?? '—' }}</dd>
            </dl>
        </x-filament::section>
    </aside>
</div>
