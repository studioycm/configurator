<?php

namespace App\Filament;

use App\Services\ConfiguratorRuleImport;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait InteractsWithConfiguratorRuleImport
{
    /** @var array<string, mixed> */
    #[Locked]
    public array $ruleImportPreview = [];

    #[Locked]
    public ?string $ruleImportPreviewToken = null;

    #[Locked]
    public ?string $ruleImportPreviewFingerprint = null;

    protected function ruleImportScope(): ?int
    {
        return null;
    }

    public function resetRuleImportPreview(): void
    {
        Gate::authorize('manage-catalog');
        if ($this->getMountedAction()?->getName() === 'importRules') {
            $index = array_key_last($this->mountedActions);
            $this->mountedActions[$index]['data']['confirm_import'] = false;
        }
        $this->ruleImportPreview = [];
        $this->ruleImportPreviewToken = null;
        $this->ruleImportPreviewFingerprint = null;
    }

    public function previewRuleImport(): void
    {
        Gate::authorize('manage-catalog');
        $this->resetRuleImportPreview();
        [$file, $options] = $this->ruleImportInput($this->ruleImportState());
        try {
            $preview = app(ConfiguratorRuleImport::class)->previewFile(auth()->user(), $file->getRealPath(), $options, $this->ruleImportScope());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['mountedActions.0.data.file' => $exception->validator->errors()->all()]);
        }
        $this->ruleImportPreviewToken = $preview['token'];
        $this->ruleImportPreviewFingerprint = $this->ruleImportFingerprint($file, $options);
        unset($preview['token']);
        $this->ruleImportPreview = $preview;
    }

    public function assertRuleImportPreview(): void
    {
        Gate::authorize('manage-catalog');
        [$file, $options] = $this->ruleImportInput($this->ruleImportState());
        if ($this->ruleImportPreviewToken === null || ! ($this->ruleImportPreview['can_import'] ?? false) || $this->ruleImportPreviewFingerprint !== $this->ruleImportFingerprint($file, $options)) {
            throw ValidationException::withMessages(['mountedActions.0.data.file' => __('data_exchange.rules.preview_required')]);
        }
    }

    /** @param array<string, mixed> $data */
    public function applyRuleImport(array $data): void
    {
        Gate::authorize('manage-catalog');
        $this->assertRuleImportPreview();
        Validator::make($data, ['confirm_import' => ['accepted']])->validate();
        try {
            $result = app(ConfiguratorRuleImport::class)->apply(auth()->user(), $this->ruleImportPreviewToken, $this->ruleImportScope());
        } catch (ValidationException $exception) {
            $this->resetRuleImportPreview();
            throw ValidationException::withMessages(['mountedActions.0.data.confirm_import' => $exception->validator->errors()->all()]);
        }
        $this->ruleImportApplied();
        $this->resetRuleImportPreview();
        Notification::make()->title(__('data_exchange.rules.import_completed', $result))->success()->send();
    }

    protected function ruleImportApplied(): void {}

    /** @return array<string, mixed> */
    private function ruleImportState(): array
    {
        abort_unless($this->getMountedAction()?->getName() === 'importRules', 403);
        $state = $this->getSchema($this->getMountedActionSchemaName())->getRawState();

        return $state instanceof Arrayable ? $state->toArray() : $state;
    }

    /** @param array<string, mixed> $state @return array{TemporaryUploadedFile, array<string, mixed>} */
    private function ruleImportInput(array $state): array
    {
        $file = $state['file'] ?? null;
        if (is_array($file)) {
            $file = array_values($file)[0] ?? null;
        }
        if (! $file instanceof TemporaryUploadedFile || ! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['mountedActions.0.data.file' => __('data_exchange.rules.file_required')]);
        }
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:10240']])->validate();
        $options = [];
        foreach (['allow_create' => true, 'allow_update' => false, 'keep_new_disabled' => true, 'restore_spreadsheet_text' => false] as $key => $default) {
            $options[$key] = $state[$key] ?? $default;
        }
        if ($this->ruleImportScope() === null) {
            $options['configurator_ids'] = $state['configurator_ids'] ?? [];
        }

        return [$file, $options];
    }

    /** @param array<string, mixed> $options */
    private function ruleImportFingerprint(TemporaryUploadedFile $file, array $options): string
    {
        return hash('sha256', json_encode([$this->ruleImportScope(), hash_file('sha256', $file->getRealPath()), $options], JSON_THROW_ON_ERROR));
    }
}
