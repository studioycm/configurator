<?php

namespace App\Filament\Pages;

use App\Actions\SaveAdminAppearanceSettings;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorFormErrors;
use App\Services\AdminAppearance as Appearance;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class AdminAppearance extends Page
{
    protected static ?string $title = 'Admin Appearance';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.admin-appearance';

    /** @var array<string, mixed> */
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
        $number = fn (string $key): TextInput => TextInput::make($key)->label(str($key)->replace('_', ' ')->title()->toString())->integer()->required()->minValue($ranges[$key][0])->maxValue($ranges[$key][1])->suffix('px')->live(onBlur: true);
        $width = fn (string $key): Select => Select::make($key)->options(['small' => 'Small · 640px', 'medium' => 'Medium · 960px', 'large' => 'Large · 1280px'])->required()->live();

        return $schema->statePath('data')->columns(1)->components([
            View::make('filament.forms.validation-summary'),
            Tabs::make('Appearance')->key('appearance-settings')->columnSpanFull()->tabs([
                Tab::make('Density')->columns(2)->schema(array_map($number, ['cell_padding_block', 'cell_padding_inline', 'workspace_gap', 'control_height'])),
                Tab::make('Navigation')->columns(2)->schema(array_map($number, ['header_height', 'logo_height', 'sidebar_width', 'collapsed_sidebar_width', 'navigation_padding_block'])),
                Tab::make('Dialogs')->columns(2)->schema([$width('modal_width'), $width('slide_over_width')]),
            ]),
        ]);
    }

    public function resetDefaults(): void
    {
        Gate::authorize('manage-catalog');
        $this->resetValidation();
        $this->form->fill(app(Appearance::class)->defaults());
    }

    public function save(): void
    {
        Gate::authorize('manage-catalog');
        $record = ConfiguratorFormErrors::run(fn () => app(SaveAdminAppearanceSettings::class)->handle(auth()->user(), $this->form->getState()), $this->form, '/^settings\./');
        $this->form->fill($record->settings);
        $this->dispatch('admin-appearance-saved', variables: app(Appearance::class)->variables($record->settings));
        Notification::make()->title('Shared appearance saved')->success()->send();
    }
}
