@if (method_exists($this, 'getTable') && ($this->getTable()->getExtraAttributes()['data-workspace-table'] ?? false))
    @php
        $table = $this->getTable();
        $wireModelAttribute = $table->isSearchOnBlur() ? 'wire:model.live.blur' : 'wire:model.live.debounce.'.$table->getSearchDebounce();
    @endphp
    <div x-id="['input']" class="fi-ta-search-field catalog-table-search" wire:key="{{ $this->getId() }}.workspace-search">
        <label x-bind:for="$id('input')" class="fi-sr-only">Search</label>
        <x-filament::input.wrapper inline-prefix :prefix-icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass" wire:target="tableSearch">
            <x-filament::input
                :attributes="(new \Filament\Support\View\ComponentAttributeBag)->merge([
                    'autocomplete' => 'off',
                    'inlinePrefix' => true,
                    'maxlength' => 1000,
                    'placeholder' => $table->getSearchPlaceholder(),
                    'type' => 'search',
                    'disabled' => $this->isTableReordering(),
                    'wire:key' => $this->getId().'.table.tableSearch.field.input',
                    $wireModelAttribute => 'tableSearch',
                    'x-bind:id' => '$id(\'input\')',
                    'x-on:keyup' => 'if ($event.key === \'Enter\') { $wire.$refresh() }',
                ], escape: false)"
            />
            <x-slot name="suffix">
                @include('filament.resources.table-toolbar-scope')
            </x-slot>
        </x-filament::input.wrapper>
    </div>
@endif
