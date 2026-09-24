<?php

namespace App\Livewire\Catalog;

use App\DTO\CatalogDiscoveryResult;
use App\Models\Group;
use App\Services\CatalogDiscovery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.catalog')]
class GroupShow extends Component
{
    #[Locked]
    public int $groupId;

    /** @var array<string, mixed> */
    #[Url(as: 'd', history: true)]
    public array $discovery = [];

    private ?CatalogDiscoveryResult $prepared = null;

    public function mount(Group $group): void
    {
        $this->groupId = $group->id;
        if (request()->query->has('d') && $this->discovery === []) {
            $this->discovery = ['version' => 0];
        }
    }

    public function updatedDiscovery(): void
    {
        $this->settle();
    }

    public function selectFilter(string $propertyKey, string $value): void
    {
        $this->settle('filter', [$propertyKey, $value]);
    }

    public function selectSubGroup(?int $subGroupId): void
    {
        $this->settle('subgroup', $subGroupId);
    }

    public function clearFilters(): void
    {
        $this->settle('clear');
    }

    public function resetAll(): void
    {
        $this->settle('reset');
    }

    public function goToPage(int $page): void
    {
        $this->settle('page', $page);
    }

    public function changePageSize(int $perPage): void
    {
        $this->settle('size', $perPage);
    }

    private function settle(?string $action = null, mixed $argument = null): void
    {
        $this->prepared = app(CatalogDiscovery::class)->prepare($this->groupId, $this->discovery, $action, $argument);
        $this->discovery = $this->prepared->state->toArray();
    }

    public function render(): View
    {
        $group = Group::findOrFail($this->groupId);
        $children = $group->children()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'description']);
        if ($children->isEmpty() && $this->prepared === null) {
            $this->settle();
        }

        return view('livewire.catalog.group-show', ['group' => $group, 'ancestors' => $group->ancestorTrail(), 'children' => $children, 'result' => $this->prepared])->title($group->name);
    }
}
