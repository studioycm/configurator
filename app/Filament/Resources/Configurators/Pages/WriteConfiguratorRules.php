<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Filament\Resources\Configurators\ConfiguratorResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class WriteConfiguratorRules extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ConfiguratorResource::class;

    protected string $view = 'filament.resources.configurators.writing';

    #[Locked]
    public ?string $requestedRule = null;

    public static function canAccess(array $parameters = []): bool
    {
        return config('configurator-writing.enabled') === true && Gate::allows('manage-catalog');
    }

    public function mount(int|string $record): void
    {
        abort_unless(config('configurator-writing.enabled') === true, 404);
        Gate::authorize('manage-catalog');
        $this->record = $this->resolveRecord($record);
        $rule = request()->query('rule');
        if ($rule !== null) {
            abort_unless(is_string($rule) && ctype_digit($rule) && (string) (int) $rule === $rule && $this->getRecord()->rules()->whereKey($rule)->exists(), 404);
            $this->requestedRule = $rule;
        }
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name.' · '.__('configurator.writing.title');
    }

    /** @return array<string,mixed> */
    protected function getViewData(): array
    {
        return ['writingConfig' => ['operationUrl' => route('configurators.writing.operation', ['configurator' => $this->getRecord()->id, 'operation' => '__operation__']), 'requestedRule' => $this->requestedRule, 'csrf' => csrf_token(), 'backUrl' => ConfiguratorResource::getUrl('edit', ['record' => $this->getRecord(), 'tab' => 'rules::data::tab']), 'labels' => __('configurator.writing')]];
    }
}
