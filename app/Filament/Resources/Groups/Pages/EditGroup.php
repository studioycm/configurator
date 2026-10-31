<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveGroupAttributes;
use App\Actions\SaveGroupSettings;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Schemas\GroupForm;
use App\Filament\Resources\InteractsWithBatchEditor;
use App\Filament\Resources\InteractsWithItemDrawerEditor;
use App\Services\CatalogRevisions;
use App\Services\GroupAttributes;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditGroup extends EditRecord
{
    use InteractsWithBatchEditor {
        beforeSave as protected beforeSaveBatchEditor;
    }
    use InteractsWithItemDrawerEditor;

    protected string $view = 'filament.resources.record-editor';

    protected static string $resource = GroupResource::class;

    #[Locked]
    public ?string $groupAttributeFingerprint = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openCatalog')->label(__('catalog.groups.labels.open_catalog_page'))
                ->url(fn (): string => route('catalog.groups.show', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['catalog_settings'] = GroupForm::settingsState($this->getRecord());
        $attributes = app(GroupAttributes::class);
        $data['catalog_attributes'] = $attributes->formState($this->getRecord())['values'];
        $this->groupAttributeFingerprint = $attributes->fingerprint($this->getRecord());

        return $data;
    }

    protected function afterSave(): void
    {
        $this->dispatch('catalog-record-saved');
        $this->dispatch('catalog-editor-saved')->self();
        $this->getRecord()->refresh();
        $this->fillForm();
        $this->notifyItemDrawerSaved();
    }

    protected function beforeSave(): void
    {
        $this->assertItemDrawerMembership($this->getRecord()->getKey());
        $this->beforeSaveBatchEditor();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(fn (): Model => app(CatalogRevisions::class)->batch(function () use ($record, $data): Model {
                $settings = $data['catalog_settings'] ?? null;
                $hasAttributes = array_key_exists('catalog_attributes', $data);
                $attributeChanges = $hasAttributes ? $data['catalog_attributes'] : [];
                unset($data['catalog_settings'], $data['catalog_attributes']);
                if ($hasAttributes && ! is_array($attributeChanges)) {
                    throw ValidationException::withMessages(['catalog_attributes' => __('catalog.group_attributes.invalid_values')]);
                }
                $group = app(SaveCatalogGroup::class)->handle(auth()->user(), $record, $data);
                if ($settings !== null) {
                    if (isset($settings['result_settings']['page_size_options'])) {
                        $settings['result_settings']['page_size_options'] = array_column($settings['result_settings']['page_size_options'], 'size');
                    }
                    $group = app(SaveGroupSettings::class)->handle(auth()->user(), $group, $settings);
                }
                if ($hasAttributes && $attributeChanges !== []) {
                    $group = app(SaveGroupAttributes::class)->handle(auth()->user(), $group, $attributeChanges, $this->groupAttributeFingerprint ?? '');
                }

                return $group;
            }), attempts: 3);
        } catch (ValidationException $exception) {
            if (array_key_exists('catalog_attribute_fingerprint', $exception->errors())) {
                $this->batchEditorStale = true;
                throw ValidationException::withMessages(['data.record' => $exception->errors()['catalog_attribute_fingerprint']]);
            }
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $key = str_starts_with($key, 'settings.') ? 'catalog_settings.'.substr($key, 9) : preg_replace('/^group\./', '', $key);
                $errors['data.'.$key] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
