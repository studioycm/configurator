<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Actions\AddAttributeValues;
use App\Actions\DeleteCanonicalDefinition;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Attributes\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\DependencyActions;
use App\Filament\Resources\InteractsWithBatchEditor;
use App\Models\Attribute;
use App\Services\CanonicalUsage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** @extends EditRecord<Attribute> */
class EditAttribute extends EditRecord
{
    use InteractsWithBatchEditor;

    protected string $view = 'filament.resources.record-editor';

    protected static string $resource = AttributeResource::class;

    public function getRelationManagers(): array
    {
        $managers = parent::getRelationManagers();
        foreach ($managers as $key => $manager) {
            if ($manager === OptionsRelationManager::class) {
                $managers[$key] = OptionsRelationManager::make(['headingLevel' => $this->recordHeadingLevel() + 1]);
            }
        }

        return $managers;
    }

    /** @return array<string, mixed> */
    protected function batchEditorComparisonState(): array
    {
        return AttributeForm::editorDraft($this->data ?? []);
    }

    public function editorDraftStateExpression(): string
    {
        return 'attributeEditorDraft($wire.data)';
    }

    protected function beforeValidate(): void
    {
        Gate::authorize('manage-catalog');
        AttributeForm::assertValuesStaged($this->form);
    }

    protected function afterSave(): void
    {
        foreach (['pending_options', 'master_values', '_master_composer', '_selected_value_ids', '_selected_pending'] as $field) {
            $this->data[$field] = [];
        }
        $this->data['_active_owner'] = '';
        $this->rememberBatchEditor();
        $this->dispatch('catalog-record-saved');
        $this->dispatch('catalog-editor-saved');
    }

    /**
     * @param  Attribute  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {

        try {
            $pool = array_values($data['master_values'] ?? []);
            unset($data['master_values']);
            $data['pending_options'] = array_map(fn (array $row): array => array_intersect_key($row, array_flip(['ref', 'value_id', 'value_ref', 'code'])), $data['pending_options'] ?? []);

            return app(AddAttributeValues::class)->saveDefinition(auth()->user(), $record, $data, $pool);
        } catch (ValidationException $exception) {
            ConfiguratorFormErrors::rethrow($exception, $this->form, '/^definition\./');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('usage')->label(__('catalog.usage.actions.view'))->authorize('manage-catalog')
                ->modalHeading(__('catalog.attributes.headings.shared_definition_usage'))->modalSubmitAction(false)->modalCancelActionLabel(__('ui.actions.close'))
                ->modalContent(fn () => view('filament.resources.canonical-usage', ['usage' => app(CanonicalUsage::class)->report($this->getRecord())])),
            DependencyActions::canonical(Action::make('remove'), $this->getRecord())->label(__('catalog.attributes.labels.delete_attribute'))->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                ->modalDescription(__('catalog.attributes.help.referenced_definitions_cannot_be_removed_use_view'))
                ->action(function (): void {
                    ConfiguratorFormErrors::run(fn () => app(DeleteCanonicalDefinition::class)->handle(auth()->user(), $this->getRecord()), $this->getMountedActionSchema());
                    $this->redirect(AttributeResource::getUrl());
                }),
        ];
    }
}
