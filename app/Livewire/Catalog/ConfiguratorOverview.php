<?php

namespace App\Livewire\Catalog;

use App\Actions\SaveConfiguratorSettings;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\Configurator;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConfiguratorOverview extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public int $configuratorId;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(int $configuratorId): void
    {
        $this->configuratorId = $configuratorId;
        $this->form->fill($this->owner()->only(['name', 'description', 'context_schema', 'hidden_context_options']));
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
        $this->dispatch('configurator-updated');
        $this->dispatch('catalog-overview-saved');
        Notification::make()->title('Overview saved')->success()->send();
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
