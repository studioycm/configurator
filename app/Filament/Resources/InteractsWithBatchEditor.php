<?php

namespace App\Filament\Resources;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

trait InteractsWithBatchEditor
{
    #[Locked]
    public bool $batchEditorStale = false;

    #[Locked]
    public string $batchEditorRecordOriginal = '';

    #[Locked]
    public string $batchEditorFormOriginal = '';

    protected function afterFill(): void
    {
        $this->rememberBatchEditor();
    }

    protected function rememberBatchEditor(): void
    {
        $this->batchEditorRecordOriginal = $this->batchRecordFingerprint();
        $this->batchEditorFormOriginal = hash('sha256', json_encode($this->data, JSON_THROW_ON_ERROR));
        $this->batchEditorStale = false;
    }

    protected function beforeSave(): void
    {
        if ($this->batchEditorStale || $this->batchEditorRecordOriginal !== $this->batchRecordFingerprint()) {
            $this->batchEditorStale = true;
            throw ValidationException::withMessages(['data.record' => 'This record changed after your editor was opened. Your draft was kept. Reload the saved record before editing again.']);
        }
    }

    /** @param list<int> $ids */
    #[On('catalog-batch-applied')]
    public function reconcileBatchEditor(string $model, array $ids): void
    {
        Gate::authorize('manage-catalog');
        if ($this->getRecord()::class !== $model || ! in_array((int) $this->getRecord()->getKey(), $ids, true)) {
            return;
        }
        if (hash('sha256', json_encode($this->data, JSON_THROW_ON_ERROR)) !== $this->batchEditorFormOriginal) {
            $this->batchEditorStale = true;

            return;
        }
        $this->reloadBatchEditor();
    }

    public function reloadBatchEditor(): void
    {
        Gate::authorize('manage-catalog');
        $this->getRecord()->refresh();
        $this->resetValidation();
        $this->fillForm();
        $this->dispatch('catalog-editor-saved');
    }

    private function batchRecordFingerprint(): string
    {
        return hash('sha256', json_encode($this->getRecord()->fresh()?->getAttributes(), JSON_THROW_ON_ERROR));
    }
}
