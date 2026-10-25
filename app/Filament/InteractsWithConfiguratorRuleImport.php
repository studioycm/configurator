<?php

namespace App\Filament;

use App\CatalogWriteFailure;
use App\RuleImportPreviewUnavailable;
use App\Services\ConfiguratorRuleImport;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

trait InteractsWithConfiguratorRuleImport
{
    /** @var array<string, mixed> */
    #[Locked]
    public array $ruleImportPreview = [];

    #[Locked]
    public ?string $ruleImportPreviewToken = null;

    #[Locked]
    public ?string $ruleImportPreviewFingerprint = null;

    /** @var array<string,mixed> */
    #[Locked]
    public array $ruleImportRecovery = [];

    protected function ruleImportScope(): ?int
    {
        return null;
    }

    public function resetRuleImportPreview(): void
    {
        Gate::authorize('manage-catalog');
        if ($this->ruleImportRecovery === [] && $this->ruleImportPreviewToken !== null && ($this->ruleImportPreview['can_import'] ?? false)
            && ! app(ConfiguratorRuleImport::class)->retirePreview(auth()->user(), $this->ruleImportPreviewToken, $this->ruleImportScope())) {
            $this->beginRuleImportRecovery($this->ruleImportPreview, $this->ruleImportScope());
        }
        $this->clearRuleImportPreview();
    }

    private function clearRuleImportPreview(): void
    {
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
        $this->assertRuleImportRecoveryComplete();
        [$file, $options] = $this->ruleImportInput($this->ruleImportState());
        try {
            $preview = app(ConfiguratorRuleImport::class)->previewFile(auth()->user(), $file->getRealPath(), $options, $this->ruleImportScope());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['mountedActions.0.data.file' => $exception->validator->errors()->all()]);
        }
        $this->resetErrorBag('mountedActions.0.data.file');
        $this->resetErrorBag('mountedActions.0.data.confirm_import');
        $this->ruleImportPreviewToken = $preview['token'];
        $this->ruleImportPreviewFingerprint = $this->ruleImportFingerprint($file, $options);
        unset($preview['token']);
        $this->ruleImportPreview = $preview;
    }

    public function assertRuleImportPreview(): void
    {
        Gate::authorize('manage-catalog');
        $this->assertRuleImportRecoveryComplete();
        if ($this->ruleImportPreviewToken !== null && ($this->ruleImportPreview['can_import'] ?? false)
            && ! app(ConfiguratorRuleImport::class)->previewAvailable(auth()->user(), $this->ruleImportPreviewToken, $this->ruleImportScope())) {
            $this->beginRuleImportRecovery($this->ruleImportPreview, $this->ruleImportScope());
            $this->clearRuleImportPreview();
            $this->assertRuleImportRecoveryComplete();
        }
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
        $preview = $this->ruleImportPreview;
        $scope = $this->ruleImportScope();
        try {
            $result = app(ConfiguratorRuleImport::class)->apply(auth()->user(), $this->ruleImportPreviewToken, $scope);
        } catch (RuleImportPreviewUnavailable $exception) {
            $this->beginRuleImportRecovery($preview, $scope);
            $this->clearRuleImportPreview();
            $this->assertRuleImportRecoveryComplete();
        } catch (ValidationException $exception) {
            $this->clearRuleImportPreview();
            throw ValidationException::withMessages(['mountedActions.0.data.confirm_import' => $exception->validator->errors()->all()]);
        } catch (CatalogWriteFailure $exception) {
            report($exception);
            if (! $exception->rolledBack) {
                $this->beginRuleImportRecovery($preview, $scope);
            }
            $this->clearRuleImportPreview();
            throw ValidationException::withMessages(['mountedActions.0.data.confirm_import' => __($exception->rolledBack ? 'data_exchange.rules.import_rolled_back' : 'data_exchange.rules.import_unknown')]);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->beginRuleImportRecovery($preview, $scope);
            $this->clearRuleImportPreview();
            $this->assertRuleImportRecoveryComplete();
        }
        $this->clearRuleImportPreview();
        try {
            $this->ruleImportApplied($preview);
            Notification::make()->title(__('data_exchange.rules.import_completed', $result))->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title(__('data_exchange.rules.import_completed', $result))->body(__('data_exchange.rules.import_refresh_failed'))->warning()->send();
        }
    }

    /** @param array<string,mixed> $preview */
    protected function ruleImportApplied(array $preview): void {}

    /** @param array<string,mixed> $preview */
    private function beginRuleImportRecovery(array $preview, ?int $scope): void
    {
        if ($this->ruleImportRecovery === []) {
            $this->ruleImportRecovery = ['configurator_ids' => array_map('intval', array_column($preview['configurators'], 'id')), 'scope' => $scope, 'inspected' => false, 'current' => []];
        }
    }

    private function assertRuleImportRecoveryComplete(): void
    {
        if ($this->ruleImportRecovery !== []) {
            throw ValidationException::withMessages(['mountedActions.0.data.confirm_import' => __('data_exchange.rules.import_unknown')]);
        }
    }

    public function inspectRuleImportOutcome(): void
    {
        Gate::authorize('manage-catalog');
        abort_if($this->ruleImportRecovery === [], 403);
        $this->resetErrorBag();
        $this->ruleImportRecovery['inspected'] = false;
        $this->ruleImportRecovery['current'] = [];
        try {
            $this->ruleImportRecovery['current'] = app(ConfiguratorRuleImport::class)->inspectCurrent(auth()->user(), $this->ruleImportRecovery['configurator_ids'], $this->ruleImportRecovery['scope']);
            $this->ruleImportRecovery['inspected'] = true;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['mountedActions.0.data.confirm_import' => __('data_exchange.rules.import_inspection_failed')]);
        }
    }

    public function finishRuleImportReview(): void
    {
        Gate::authorize('manage-catalog');
        if (! ($this->ruleImportRecovery['inspected'] ?? false)) {
            throw ValidationException::withMessages(['mountedActions.0.data.confirm_import' => __('data_exchange.rules.import_review_required')]);
        }
        $this->clearRuleImportPreview();
        $this->ruleImportRecovery = [];
        $this->resetErrorBag();
    }

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
