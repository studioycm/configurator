<?php

namespace App\Filament\Resources;

use App\Models\Attribute;
use App\Models\Group;
use App\Models\Option;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

trait InteractsWithRecordPresentation
{
    #[Locked]
    public bool $inWorkspace = false;

    public function recordHeadingLevel(): int
    {
        if (method_exists($this, 'isItemDrawerEditor') && $this->isItemDrawerEditor()) {
            return 3;
        }

        return $this->inWorkspace ? 2 : 1;
    }

    public function getHeader(): ?View
    {
        return $this->recordHeadingLevel() === 1 ? parent::getHeader() : view('filament.resources.record-header');
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->rootHeadingLevel($this->recordHeadingLevel() + 1);
    }

    public function getFormActionsContentComponent(): Component
    {
        return Flex::make([
            SchemaView::make('filament.resources.editor-save-state')->viewData(['dirty' => 'JSON.stringify($wire.data) !== savedDraft', 'scope' => $this->editorSaveScope()]),
            parent::getFormActionsContentComponent()->grow(false),
        ])->from('sm')->extraAttributes(['class' => 'catalog-editor-footer']);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('admin.workspace.editor.save_record', ['record' => static::getResource()::getModelLabel()]));
    }

    public function editorSaveScope(): string
    {
        if ($this instanceof CreateRecord) {
            return __('admin.workspace.editor.create_scope', ['record' => static::getResource()::getModelLabel()]);
        }

        return match (static::getResource()::getModel()) {
            Group::class => __('admin.workspace.editor.group_scope'),
            Attribute::class => __('admin.workspace.editor.attribute_scope'),
            Option::class => __('admin.workspace.editor.shared_value_scope'),
            default => __('admin.workspace.editor.record_scope', ['record' => static::getResource()::getModelLabel()]),
        };
    }
}
