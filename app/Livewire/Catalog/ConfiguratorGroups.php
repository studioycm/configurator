<?php

namespace App\Livewire\Catalog;

use App\Actions\AssignConfiguratorGroups;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Configurator;
use App\Models\Group;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConfiguratorGroups extends Component
{
    #[Locked]
    public int $configuratorId;

    public function mount(int $configuratorId): void
    {
        $this->configuratorId = $configuratorId;
        $this->owner();
    }

    public function assignGroup(int $groupId): void
    {
        $this->resetErrorBag();
        ConfiguratorFormErrors::run(fn () => app(AssignConfiguratorGroups::class)->assign(auth()->user(), $this->owner(), $groupId));
        $this->saved('Group assigned');
    }

    public function unassignGroup(int $groupId): void
    {
        $this->resetErrorBag();
        ConfiguratorFormErrors::run(fn () => app(AssignConfiguratorGroups::class)->unassign(auth()->user(), $this->owner(), $groupId));
        $this->saved('Group unassigned');
    }

    private function owner(): Configurator
    {
        Gate::authorize('manage-catalog');

        return Configurator::findOrFail($this->configuratorId);
    }

    private function saved(string $message): void
    {
        $this->dispatch('configurator-updated');
        Notification::make()->title($message)->success()->send();
    }

    public function render(): View
    {
        $configurator = $this->owner();
        $groups = Group::doesntHave('children')->where(fn ($query) => $query->whereNull('configurator_id')->orWhere('configurator_id', $configurator->id))->withCount('products')->orderBy('name')->get();

        return view('livewire.catalog.configurator-groups', ['configurator' => $configurator, 'assigned' => $groups->where('configurator_id', $configurator->id), 'available' => $groups->whereNull('configurator_id')]);
    }
}
