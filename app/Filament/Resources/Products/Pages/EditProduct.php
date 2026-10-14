<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\SaveCatalogProduct;
use App\Filament\Resources\InteractsWithBatchEditor;
use App\Filament\Resources\Products\ProductResource;
use App\Services\ProductEditorData;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    use InteractsWithBatchEditor;

    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.record-editor';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['properties', 'parts', 'extra_data'] as $bucket) {
            $data[$bucket] = ProductEditorData::rows($data[$bucket] ?? []);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveCatalogProduct::class)->handle(auth()->user(), $record, $data, $this->batchEditorRecordOriginal);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.'.preg_replace('/^product\./', '', $key)] = $messages;
            }
            if (isset($errors['data.record'])) {
                $this->batchEditorStale = true;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    protected function afterSave(): void
    {
        $this->getRecord()->refresh();
        $this->fillForm();
        $this->dispatch('catalog-record-saved');
        $this->dispatch('catalog-editor-saved')->self();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('openCatalog')->label(__('catalog.products.labels.open_catalog'))->url(fn (): string => route('catalog.products.show', $this->getRecord()))->openUrlInNewTab()];
    }
}
