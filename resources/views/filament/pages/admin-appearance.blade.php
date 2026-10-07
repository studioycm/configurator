<x-filament-panels::page>
    <form wire:submit="save" class="space-y-2">
        {{ $this->form }}
        <div class="flex gap-2"><x-filament::button type="submit">Save shared defaults</x-filament::button><x-filament::button color="gray" wire:click="resetDefaults">Reset preview to defaults</x-filament::button></div>
    </form>
    <div style="{{ app(\App\Services\AdminAppearance::class)->style($this->data) }}" class="catalog-appearance-preview">
        <p class="text-sm mb-2">Unsaved preview · other users see saved defaults.</p>
        <div class="catalog-appearance-preview-header"><img src="{{ asset('images/logo-ari_dark.png') }}" alt="Aquestia logo preview" /><strong>Workspace</strong></div>
        <div class="catalog-appearance-preview-body">
            <aside><div>Library</div><div>Attributes</div><div>Options</div><div>Settings</div></aside>
            <main><div class="catalog-appearance-preview-table"><div>Records · Search · Filters · Columns</div><table><thead><tr><th>Name</th><th>Code</th><th>Items</th></tr></thead><tbody><tr><td>Flange Standard</td><td>A1</td><td>4</td></tr><tr><td>Kinetic Valve Body Material</td><td>DI</td><td>2</td></tr></tbody></table></div><div class="catalog-appearance-preview-narrow"><div>Search · Filters · Columns · Actions</div><table><tr><td>Flange Standard</td><td>A1</td></tr></table></div><div class="catalog-appearance-preview-dialog">Modal preview <div>Fields and controls</div><x-filament::button disabled>Save</x-filament::button></div><div class="catalog-appearance-preview-dialog catalog-appearance-preview-slide-over">Slide-over preview <div>Related records and controls</div></div></main>
        </div>
    </div>
</x-filament-panels::page>
