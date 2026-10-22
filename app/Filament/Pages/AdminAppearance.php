<?php

namespace App\Filament\Pages;

use App\Actions\SaveAdminAppearanceSettings;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Services\AdminAppearance as Appearance;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class AdminAppearance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.admin-appearance';

    /** @var array<string, mixed> */
    public function getTitle(): string
    {
        return __('admin.appearance.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.appearance.title');
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
        $this->form->fill(app(Appearance::class)->current());
    }

    public function form(Schema $schema): Schema
    {
        $ranges = app(Appearance::class)->ranges();
        $number = fn (string $key): TextInput => TextInput::make($key)->label(__('admin.appearance.fields.'.$key))->integer()->required()->minValue($ranges[$key][0])->maxValue($ranges[$key][1])->suffix('px')->live(onBlur: true);
        $width = fn (string $key): Select => Select::make($key)->label(__('admin.appearance.fields.'.$key))->options(['small' => __('admin.appearance.labels.small_640px'), 'medium' => __('admin.appearance.labels.medium_960px'), 'large' => __('admin.appearance.labels.large_1280px')])->required()->live();

        return $schema->statePath('data')->columns(1)->components([
            View::make('filament.forms.validation-summary'),
            Tabs::make(__('admin.appearance.headings.appearance'))->key('appearance-settings')->columnSpanFull()->tabs([
                Tab::make(__('admin.appearance.headings.density'))->key(fn (Tab $component): string => 'density::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->columns(2)->schema(array_map($number, ['cell_padding_block', 'cell_padding_inline', 'workspace_gap', 'control_height'])),
                Tab::make(__('admin.appearance.headings.navigation'))->key(fn (Tab $component): string => 'navigation::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->columns(2)->schema(array_map($number, ['header_height', 'logo_height', 'page_title_size', 'sidebar_width', 'collapsed_sidebar_width', 'navigation_padding_block'])),
                Tab::make(__('admin.appearance.headings.dialogs'))->key(fn (Tab $component): string => 'dialogs::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->columns(2)->schema([$width('modal_width'), $width('slide_over_width'), Select::make('dialog_control_style')->label(__('admin-ux.dialog_control_style'))->options(['icons' => __('admin-ux.icons'), 'icons_text' => __('admin-ux.icons_text'), 'text' => __('admin-ux.text')])->required()->live(), TextInput::make('dialog_control_radius')->label(__('admin-ux.dialog_control_radius'))->integer()->required()->minValue(4)->maxValue(10)->suffix('px')->live(onBlur: true)]),
                Tab::make(__('configurator.resource.singular'))->key(fn (Tab $component): string => 'configurator::'.(filled($component->getStatePath()) ? $component->getStatePath().'::tab' : 'tab'), isInheritable: false)->schema([
                    Text::make(__('admin.appearance.copy.configurator_shared_scope'))->columnSpanFull(),
                    Toggle::make('show_configurator_loading_indicator')->label(__('admin.appearance.labels.show_configurator_loading_indicator'))->default(false)
                        ->helperText(__('admin.appearance.hints.product_pages_keep_this_off_by_default')),
                ]),
            ]),
        ]);
    }

    public function resetDefaults(): void
    {
        Gate::authorize('manage-catalog');
        $this->resetValidation();
        $this->form->fill(app(Appearance::class)->defaults());
    }

    public function savePersonal(): void
    {
        Gate::authorize('manage-catalog');
        $settings = ConfiguratorFormErrors::run(fn (): array => app(Appearance::class)->validate($this->form->getState()), $this->form, '/^settings\\./');
        unset($settings['show_configurator_loading_indicator']);
        $this->dispatch('admin-personal-appearance-saved', settings: $settings, variables: app(Appearance::class)->variables($settings));
        Notification::make()->title(__('admin.appearance.copy.personal_saved'))->success()->send();
    }

    public function resetPersonal(): void
    {
        Gate::authorize('manage-catalog');
        $this->resetValidation();
        $this->form->fill(app(Appearance::class)->current());
        $this->dispatch('admin-personal-appearance-saved', settings: null, variables: app(Appearance::class)->variables());
    }

    public function save(): void
    {
        Gate::authorize('manage-catalog');
        $settings = $this->form->getState();
        $record = ConfiguratorFormErrors::run(fn () => app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $settings), $this->form, '/^settings\./');
        $this->form->fill($record->settings);
        $this->dispatch('admin-appearance-saved', settings: $record->settings, variables: app(Appearance::class)->variables($record->settings));
        Notification::make()->title(__('admin.appearance.headings.shared_appearance_saved'))->success()->send();
    }
}
