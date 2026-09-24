<?php

namespace App\Livewire\Catalog;

use App\Models\Group;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.catalog')]
#[Title('Product catalog')]
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.catalog.index', ['groups' => Group::whereNull('parent_id')->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'description'])]);
    }
}
