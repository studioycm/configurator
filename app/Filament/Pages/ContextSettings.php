<?php

namespace App\Filament\Pages;

use App\Actions\SaveCatalogContextSettings;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Models\CatalogContextSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ContextSettings extends Page
{
    protected static ?string $title = 'Territory & Application';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.context-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage-catalog');
    }

    public function mount(): void
    {
        Gate::authorize('manage-catalog');
        $this->form->fill(['context_schema' => CatalogContextSettings::current()->choices]);
    }

    public function getSubheading(): ?string
    {
        return 'Global choices inherited by every configurator. All is always available.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(1)->components([
            View::make('filament.forms.validation-summary')->columnSpanFull(),
            Section::make('Territories')->schema([ConfiguratorForm::contextChoices('territory', 'Territory options')]),
            Section::make('Applications')->schema([ConfiguratorForm::contextChoices('application', 'Application options')]),
        ]);
    }

    public function save(): void
    {
        Gate::authorize('manage-catalog');
        $data = $this->form->getState();
        $settings = ConfiguratorFormErrors::run(fn () => app(SaveCatalogContextSettings::class)->handle(auth()->user(), $data['context_schema']), $this->form);
        $this->form->fill(['context_schema' => $settings->choices]);
        Notification::make()->title('Global choices saved')->success()->send();
    }
}
