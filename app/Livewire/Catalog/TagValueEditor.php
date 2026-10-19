<?php

namespace App\Livewire\Catalog;

use App\Actions\SaveValueTags;
use App\Models\Value;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class TagValueEditor extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public int $valueId;

    #[Locked]
    public string $tag;

    /** @var list<string> */
    #[Locked]
    public array $originalTags = [];

    /** @var array{tags: list<string>} */
    public array $data = ['tags' => []];

    public function mount(int $valueId, string $tag): void
    {
        Gate::authorize('manage-catalog');
        $this->valueId = $valueId;
        $this->tag = $tag;
        $value = Value::query()->whereJsonContains('tags', $tag)->findOrFail($valueId);
        $this->originalTags = $value->tags ?? [];
        $this->form->fill(['tags' => $this->originalTags]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TagsInput::make('tags')->id('tag-value-'.$this->getId())->label(__('catalog.fields.tags'))->hiddenLabel()->trim()->default([])
                ->suggestions(fn (): array => array_keys(Value::tagOptions()))
                ->rules(['array', 'list', 'max:30'])->nestedRecursiveRules(['required', 'string', 'max:80', 'distinct:strict']),
        ]);
    }

    public function save(): void
    {
        app(SaveValueTags::class)->handle(auth()->user(), $this->valueId, $this->tag, $this->form->getState()['tags'] ?? [], $this->originalTags);
        $this->originalTags = Value::findOrFail($this->valueId)->tags ?? [];
        $this->form->fill(['tags' => $this->originalTags]);
        $this->dispatch('catalog-tags-updated');
        Notification::make()->title(__('tags.row_saved'))->success()->send();
    }

    public function cancel(): void
    {
        Gate::authorize('manage-catalog');
        $this->originalTags = Value::findOrFail($this->valueId)->tags ?? [];
        $this->form->fill(['tags' => $this->originalTags]);
        $this->resetValidation();
        $this->dispatch('catalog-tags-updated');
    }

    #[On('catalog-tags-updated')]
    public function refreshTags(): void
    {
        Gate::authorize('manage-catalog');
        if (($this->data['tags'] ?? []) !== $this->originalTags) {
            return;
        }
        $value = Value::find($this->valueId);
        if ($value === null) {
            return;
        }
        $this->originalTags = $value->tags ?? [];
        $this->form->fill(['tags' => $this->originalTags]);
    }

    public function render(): View
    {
        return view('livewire.catalog.tag-value-editor');
    }
}
