<?php

namespace App\Filament\Pages;

use App\Actions\SaveCatalogContextSettings;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorForm;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Filament\Resources\FormHints;
use App\Models\CatalogContextSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ContextSettings extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.context-settings';

    /** @var array<string, mixed> */
    public function getTitle(): string
    {
        return __('configurator.context.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('configurator.context.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

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

    protected function getHeaderActions(): array
    {
        return [FormHints::make(__('configurator.context.hints.reserved_for_the_future_public_catalog_these'))];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(1)->components([
            View::make('filament.forms.validation-summary')->columnSpanFull(),
            Tabs::make(__('configurator.context.headings.context_choices'))->key('context-choices')->columnSpanFull()->tabs([
                Tab::make(__('configurator.context.headings.territories'))->key(fn (Tab $component): string => 'territories::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([ConfiguratorForm::contextChoices('territory', __('configurator.context.global_choices'))->helperText(null)]),
                Tab::make(__('configurator.context.headings.applications'))->key(fn (Tab $component): string => 'applications::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([ConfiguratorForm::contextChoices('application', __('configurator.context.global_choices'))->helperText(null)]),
            ]),
        ]);
    }

    public function save(): void
    {
        Gate::authorize('manage-catalog');
        $data = $this->form->getState();
        $settings = ConfiguratorFormErrors::run(fn () => app(SaveCatalogContextSettings::class)->handle(auth()->user(), $data['context_schema']), $this->form);
        $this->form->fill(['context_schema' => $settings->choices]);
        Notification::make()->title(__('configurator.context.headings.global_choices_saved'))->success()->send();
    }
}
