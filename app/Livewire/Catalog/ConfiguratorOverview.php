<?php

namespace App\Livewire\Catalog;

use App\Actions\SaveConfiguratorSettings;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Configurator;
use App\Services\ConfiguratorEditorChanges;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class ConfiguratorOverview extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public int $configuratorId;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $previewOriginal = [];

    public function mount(int $configuratorId): void
    {
        $this->configuratorId = $configuratorId;
        $this->form->fill($this->owner()->only(['name', 'description', 'context_schema', 'hidden_context_options']));
        $this->previewOriginal = $this->data;
    }

    /** @param list<array<string, mixed>> $captures @param array<string, array<string, mixed>> $newRules */
    #[On('configurator-preview-saved')]
    public function acceptPreviewSave(int $configuratorId, array $captures, array $newRules = []): void
    {
        Gate::authorize('manage-catalog');
        if ($configuratorId !== $this->configuratorId) {
            return;
        }
        $changes = app(ConfiguratorEditorChanges::class);
        foreach ($changes->savedRows($configuratorId, $captures) as $row) {
            if ($row['type'] !== 'overview') {
                continue;
            }
            $current = $this->data;
            $saved = $this->owner()->only(ConfiguratorEditorChanges::OVERVIEW_FIELDS);
            $this->form->fill($saved);
            $this->previewOriginal = $this->data;
            if ($changes->normalize('overview', $current) == $changes->normalize('overview', $row['after'])) {
                $this->dispatch('catalog-overview-saved');
            } else {
                $this->form->fill($changes->rebase('overview', $current, $row['after'], $this->previewOriginal));
            }
        }
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function exportPreviewChanges(): array
    {
        Gate::authorize('manage-catalog');
        $rows = $this->data == $this->previewOriginal ? [] : [['type' => 'overview', 'id' => 'overview', 'before' => $this->previewOriginal, 'after' => $this->data]];

        return app(ConfiguratorEditorChanges::class)->capture($this->configuratorId, $rows);
    }

    public function form(Schema $schema): Schema
    {
        Gate::authorize('manage-catalog');

        return $schema->statePath('data')->columns(2)->components(ConfiguratorForm::overview());
    }

    public function saveOverview(): void
    {
        Gate::authorize('manage-catalog');
        try {
            $record = app(SaveConfiguratorSettings::class)->handle(auth()->user(), $this->owner(), $this->form->getState());
        } catch (\Throwable $exception) {
            ConfiguratorFormErrors::rethrow($exception, $this->form);
        }
        $this->form->fill($record->only(['name', 'description', 'context_schema', 'hidden_context_options']));
        $this->previewOriginal = $this->data;
        $this->dispatch('configurator-updated');
        $this->dispatch('catalog-overview-saved');
        Notification::make()->title(__('configurator.headings.overview_saved'))->success()->send();
    }

    private function owner(): Configurator
    {
        Gate::authorize('manage-catalog');

        return Configurator::findOrFail($this->configuratorId);
    }

    public function render(): View
    {
        return view('livewire.catalog.configurator-overview', ['configurator' => $this->owner()->loadCount(['attributes', 'rules'])]);
    }
}
