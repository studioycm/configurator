<?php

namespace App\Filament\Resources\Configurators\Pages;

use App\Actions\DeleteConfigurator;
use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Livewire\Catalog\ConfiguratorGroups;
use App\Livewire\Catalog\ConfiguratorOverview;
use App\Models\Configurator;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

class EditConfigurator extends EditRecord
{
    protected static string $resource = ConfiguratorResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function getBreadcrumb(): string
    {
        return 'Manage';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('Configurator')->key('configurator-editor')->persistTabInQueryString('tab')->columnSpanFull()->tabs([
                Tab::make('Overview')->schema([Livewire::make(ConfiguratorOverview::class, fn (): array => ['configuratorId' => $this->getRecord()->id])->key('overview-'.$this->getRecord()->id)]),
                Tab::make('Groups')->schema([Livewire::make(ConfiguratorGroups::class, fn (): array => ['configuratorId' => $this->getRecord()->id])->key('groups-'.$this->getRecord()->id)]),
                Tab::make('Attributes')->schema([Livewire::make(AttributesRelationManager::class, fn (): array => ['ownerRecord' => $this->getRecord(), 'pageClass' => static::class])->key('attributes-'.$this->getRecord()->id)]),
                Tab::make('Rules')->schema([Livewire::make(RulesRelationManager::class, fn (): array => ['ownerRecord' => $this->getRecord(), 'pageClass' => static::class])->key('rules-'.$this->getRecord()->id)]),
                Tab::make('Preview & Test')->schema([Text::make('Preview and testing tools will be added here later.')]),
            ]),
        ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        Gate::authorize('manage-catalog');
        abort(405, 'Use the scoped Overview, inclusion or rule action.');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort(405, 'Use a scoped Configurator action.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('duplicate')->label('Duplicate')->authorize('manage-catalog')->schema([TextInput::make('name')->required()->maxLength(255)])
                ->fillForm(fn (): array => ['name' => $this->getRecord()->name.' copy'])
                ->action(function (array $data): void {
                    $copy = ConfiguratorFormErrors::run(fn () => app(SaveConfiguratorDefinition::class)->duplicate(auth()->user(), $this->getRecord(), $data['name']), $this->getMountedActionSchema());
                    $this->redirect(ConfiguratorResource::getUrl('edit', ['record' => $copy]));
                }),
            Action::make('remove')->label('Delete')->color('danger')->authorize('manage-catalog')->requiresConfirmation()
                ->schema([View::make('filament.forms.validation-summary')])
                ->modalDescription('Unassign Groups before deleting this Configurator. Local inclusions and rules will be removed; shared canonical records remain available.')
                ->action(function (): void {
                    ConfiguratorFormErrors::run(fn () => app(DeleteConfigurator::class)->handle(auth()->user(), $this->getRecord()), $this->getMountedActionSchema());
                    $this->redirect(ConfiguratorResource::getUrl());
                }),
        ];
    }

    #[On('configurator-updated')]
    public function refreshConfigurator(): void
    {
        Gate::authorize('manage-catalog');
        $this->record = Configurator::findOrFail($this->getRecord()->id);
    }
}
