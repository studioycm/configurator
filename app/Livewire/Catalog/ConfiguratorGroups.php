<?php

namespace App\Livewire\Catalog;

use App\Actions\AssignConfiguratorGroups;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\GroupSelectionTable;
use App\Models\Configurator;
use App\Models\Group;
use App\Services\ItemLists;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TableSelect;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConfiguratorGroups extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public int $configuratorId;

    /** @var list<int> */
    #[Locked]
    public array $expectedGroupIds = [];

    public function mount(int $configuratorId): void
    {
        $this->configuratorId = $configuratorId;
        $this->owner();
    }

    public function membershipAction(): Action
    {
        return Action::make('membership')->label(__('configurator.labels.manage_group_assignments'))->authorize('manage-catalog')->slideOver()->modalHeading(fn (): string => __('configurator.groups.assignment_heading', ['name' => $this->owner()->name]))->modalSubmitActionLabel(__('configurator.groups.save_assignments'))
            ->schema([TableSelect::make('selected')->label(__('configurator.labels.final_assigned_groups'))->multiple()->tableConfiguration(GroupSelectionTable::class)->tableArguments(fn (): array => ['owner_id' => $this->configuratorId])])
            ->fillForm(function (): array {
                $this->expectedGroupIds = $this->owner()->groups()->orderBy('id')->pluck('id')->all();

                return ['selected' => array_map('strval', $this->expectedGroupIds)];
            })
            ->action(function (array $data): void {
                ConfiguratorFormErrors::run(fn () => app(AssignConfiguratorGroups::class)->handle(auth()->user(), $this->owner(), $data['selected'] ?? [], $this->expectedGroupIds), $this->getMountedActionSchema());
                $this->saved(__('configurator.groups.notifications.saved'));
            });
    }

    public function productsAction(): Action
    {
        return Action::make('products')->label(__('configurator.labels.view_products'))->authorize('manage-catalog')->slideOver()->modalHeading(fn (array $arguments): string => app(ItemLists::class)->modalHeading('group-products', Group::findOrFail((int) $arguments['group'])))->modalSubmitAction(false)->modalCancelActionLabel(__('ui.actions.close'))
            ->modalContent(fn (array $arguments) => view('filament.resources.item-list-content', ['listKey' => 'group-products', 'parentId' => (int) $arguments['group'], 'allowLocalEditing' => true, 'showTableHeading' => false]));
    }

    public function assignGroup(int $groupId): void
    {
        $this->resetErrorBag();
        ConfiguratorFormErrors::run(fn () => app(AssignConfiguratorGroups::class)->assign(auth()->user(), $this->owner(), $groupId));
        $this->saved(__('configurator.groups.notifications.assigned'));
    }

    public function unassignGroup(int $groupId): void
    {
        $this->resetErrorBag();
        ConfiguratorFormErrors::run(fn () => app(AssignConfiguratorGroups::class)->unassign(auth()->user(), $this->owner(), $groupId));
        $this->saved(__('configurator.groups.notifications.unassigned'));
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
