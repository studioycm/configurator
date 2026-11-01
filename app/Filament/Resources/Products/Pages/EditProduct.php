<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\SaveCatalogProduct;
use App\DTO\CatalogAssetChange;
use App\Filament\Resources\InteractsWithBatchEditor;
use App\Filament\Resources\InteractsWithCatalogMainImage;
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
        reloadBatchEditor as protected reloadCatalogMainImageEditor;
    }
    use InteractsWithCatalogMainImage;
    use InteractsWithItemDrawerEditor;

    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.record-editor';

    #[Locked]
    public string $productGroupOriginal = '';

    public function reloadBatchEditor(): void
    {
        $this->reloadCatalogMainImageEditor();
        if ($this->catalogMainImageEnabled()) {
            $this->dispatch('catalog-image-draft-discarded', hostId: $this->getId());
        }
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->beginCatalogMainImageWrite();
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } finally {
            $this->catalogMainImageWriting = false;
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->getRecord()->unsetRelation('group');
        $data = array_replace($data, ProductEditorData::formData($this->getRecord()));
        $this->productGroupOriginal = ProductEditorData::groupFingerprint($this->getRecord());
        unset($data['profile_fields']);

        if ($this->catalogMainImageEnabled()) {
            $data['image_intent'] = $this->catalogMainImageInitialIntent();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveCatalogProduct::class)->handle(auth()->user(), $record, $this->stripCatalogMainImageTransport($data), $this->batchEditorRecordOriginal, $this->productGroupOriginal, null, $this->catalogMainImageWriting ? fn (): CatalogAssetChange => $this->resolveCatalogMainImageChange() : null);
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

        $imageWrite = $this->catalogMainImageWriting;
        $this->catalogMainImageCompletion(function () use ($imageWrite): void {
            $this->getRecord()->refresh();
            $this->fillForm();
            $this->dispatch('catalog-record-saved');
            $this->dispatch('catalog-editor-saved')->self();
            $this->notifyItemDrawerSaved();
            if ($imageWrite) {
                $this->dispatch('catalog-image-save-committed', hostId: $this->getId(), recordId: (int) $this->getRecord()->getKey());
            }
        });
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('openCatalog')->label(__('catalog.products.labels.open_catalog'))->url(fn (): string => route('catalog.products.show', $this->getRecord()))->openUrlInNewTab()];
    }
}
