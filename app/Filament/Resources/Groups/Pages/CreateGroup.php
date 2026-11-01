<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveGroupAttributes;
use App\DTO\CatalogAssetChange;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\InteractsWithCatalogMainImage;
use App\Filament\Resources\SplitCreateRecord;
use App\Services\CatalogRevisions;
use App\Services\GroupAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGroup extends SplitCreateRecord
{
    use InteractsWithCatalogMainImage;

    protected static string $resource = GroupResource::class;

    public function create(bool $another = false): void
    {
        if ($this->catalogMainImageEnabled() && $this->embedded && $another) {
            throw ValidationException::withMessages(['data.image_intent' => 'Create another is unavailable inside the list editor.']);
        }
        $this->beginCatalogMainImageWrite();
        try {
            parent::create($another);
        } catch (\Throwable $exception) {
            if ($this->catalogMainImageWriting && DB::transactionLevel() === 0
                && $this->getRecord() !== null && ! $this->getRecord()->newQuery()->whereKey($this->getRecord()->getKey())->exists()) {
                $this->record = null;
            }
            throw $exception;
        } finally {
            $this->catalogMainImageWriting = false;
        }
    }

    protected function afterCreate(): void
    {
        if ($this->catalogMainImageWriting) {
            $this->catalogMainImageCompletion(fn () => $this->dispatch('catalog-image-save-committed', hostId: $this->getId(), recordId: (int) $this->getRecord()->getKey()));
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(fn (): Model => app(CatalogRevisions::class)->batch(function () use ($data): Model {
                $resolveImage = $this->catalogMainImageWriting ? fn (): CatalogAssetChange => $this->resolveCatalogMainImageChange() : null;
                $data = $this->stripCatalogMainImageTransport($data);
                $hasAttributes = array_key_exists('catalog_attributes', $data);
                $attributeChanges = $hasAttributes ? $data['catalog_attributes'] : [];
                unset($data['catalog_attributes']);
                if ($hasAttributes && ! is_array($attributeChanges)) {
                    throw ValidationException::withMessages(['catalog_attributes' => __('catalog.group_attributes.invalid_values')]);
                }
                $group = app(SaveCatalogGroup::class)->handle(auth()->user(), null, $data, null, null, $resolveImage);
                if ($hasAttributes && $attributeChanges !== []) {
                    $group = app(SaveGroupAttributes::class)->handle(auth()->user(), $group, $attributeChanges, app(GroupAttributes::class)->fingerprint($group));
                }

                return $group;
            }), attempts: 3);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.'.preg_replace('/^group\./', '', $key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
