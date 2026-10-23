<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\SaveCatalogProduct;
use App\Filament\Resources\InteractsWithBatchEditor;
use App\Filament\Resources\InteractsWithItemDrawerEditor;
use App\Filament\Resources\Products\ProductResource;
use App\Services\ProductEditorData;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditProduct extends EditRecord
{
    use InteractsWithBatchEditor {
        beforeSave as protected beforeSaveBatchEditor;
    }
    use InteractsWithItemDrawerEditor;

    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.record-editor';

    #[Locked]
    public string $productGroupOriginal = '';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->getRecord()->unsetRelation('group');
        $data = array_replace($data, ProductEditorData::formData($this->getRecord()));
        $this->productGroupOriginal = ProductEditorData::groupFingerprint($this->getRecord());
        unset($data['profile_fields']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveCatalogProduct::class)->handle(auth()->user(), $record, $data, $this->batchEditorRecordOriginal, $this->productGroupOriginal);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.'.preg_replace('/^product\./', '', $key)] = $messages;
            }
            if (isset($errors['data.record']) || isset($errors['data.group_classification'])) {
                $this->batchEditorStale = true;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    protected function beforeSave(): void
    {
        $this->assertItemDrawerMembership($this->getRecord()->getKey());
        $this->beforeSaveBatchEditor();
    }

    protected function afterSave(): void
    {
        $this->getRecord()->refresh();
        $this->fillForm();
        $this->dispatch('catalog-record-saved');
        $this->dispatch('catalog-editor-saved')->self();
        $this->notifyItemDrawerSaved();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('openCatalog')->label(__('catalog.products.labels.open_catalog'))->url(fn (): string => route('catalog.products.show', $this->getRecord()))->openUrlInNewTab()];
    }
}
