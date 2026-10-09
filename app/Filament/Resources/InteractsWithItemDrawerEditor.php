<?php

namespace App\Filament\Resources;

use App\Livewire\Catalog\ItemListDrawer;
use App\Models\Group;
use App\Models\Option;
use App\Services\ItemLists;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

trait InteractsWithItemDrawerEditor
{
    #[Locked]
    public ?string $listKey = null;

    #[Locked]
    public ?int $parentId = null;

    #[Locked]
    public ?string $initialTab = null;

    #[Locked]
    public bool $embedded = false;

    public function mount(int|string $record, ?string $listKey = null, ?int $parentId = null, ?string $initialTab = null): void
    {
        $this->listKey = $listKey;
        $this->parentId = $parentId;
        $this->initialTab = $initialTab;
        $this->embedded = $listKey !== null || $parentId !== null;
        abort_unless($initialTab === null || ($listKey === 'group-card-properties' && $initialTab === 'form.group-settings.presentation::data::tab'), 404);
        $this->assertItemDrawerMembership($record);
        parent::mount($record);
    }

    public function hydrate(): void
    {
        parent::hydrate();
        $this->assertItemDrawerMembership($this->getRecord()->getKey());
    }

    public function isItemDrawerEditor(): bool
    {
        return $this->embedded;
    }

    protected function assertItemDrawerMembership(int|string $record): void
    {
        if (! $this->isItemDrawerEditor()) {
            return;
        }

        abort_unless($this->listKey !== null && $this->parentId !== null, 404);
        $definition = app(ItemLists::class)->definition(auth()->user(), $this->listKey, $this->parentId);
        $model = static::getResource()::getModel();
        abort_unless(in_array($model, [Option::class, Group::class], true), 404);

        if ($this->listKey === 'group-card-properties') {
            abort_unless($model === Group::class && (string) $record === (string) $this->parentId, 404);
            abort_unless(Group::query()->whereKey($record)->doesntHave('children')->exists(), 404);

            return;
        }

        abort_unless($definition->query instanceof Builder && $definition->query->getModel()::class === $model, 404);
        abort_unless((clone $definition->query)->whereKey($record)->exists(), 404);
    }

    protected function notifyItemDrawerSaved(): void
    {
        if (! $this->isItemDrawerEditor()) {
            return;
        }

        $this->dispatch('item-drawer-record-saved', listKey: $this->listKey, parentId: $this->parentId, record: (string) $this->getRecord()->getKey())
            ->to(ItemListDrawer::class);
    }

    protected function getCancelFormAction(): Action
    {
        if (! $this->isItemDrawerEditor()) {
            return parent::getCancelFormAction();
        }

        return Action::make('cancel')->label('Close editor')->color('gray')
            ->extraAttributes(['data-item-editor-transition' => true])
            ->action(fn () => $this->dispatch('item-drawer-editor-closed', listKey: $this->listKey, parentId: $this->parentId)
                ->to(ItemListDrawer::class));
    }
}
