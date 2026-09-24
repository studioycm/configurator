<?php

namespace App\Livewire\Catalog;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ContextSelector extends Component
{
    /** @var array{territory: string, application: string} */
    #[Reactive]
    public array $context = ['territory' => 'All', 'application' => 'All'];

    /** @var array{territory: list<array{value: string, label: string}>, application: list<array{value: string, label: string}>} */
    #[Reactive]
    public array $schema = ['territory' => [], 'application' => []];

    #[Locked]
    public string $summaryTarget = '#catalog-product-context';

    public function render(): View
    {
        $selectedLabels = [];

        foreach (['territory', 'application'] as $dimension) {
            $selectedLabels[$dimension] = $this->context[$dimension] === 'All'
                ? __('All')
                : (array_column($this->schema[$dimension], 'label', 'value')[$this->context[$dimension]] ?? $this->context[$dimension]);
        }

        return view('livewire.catalog.context-selector', compact('selectedLabels'));
    }
}
